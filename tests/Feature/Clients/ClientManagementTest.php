<?php

namespace Tests\Feature\Clients;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\User;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'company',
            'name' => 'Empresa ABC S.A.C.',
            'trade_name' => '',
            'document_type' => 'RUC',
            'document_number' => '20131312955',
            'phone' => '',
            'whatsapp' => '987 654 321',
            'email' => '  Ventas@ABC.pe ',
            'address' => 'Av. Ejército 123, Arequipa',
            'contact_name' => 'Luis Rojas',
            'notes' => '',
            'status' => 'active',
            ...$overrides,
        ];
    }

    public function test_creates_a_client_normalizing_input_and_logging_it(): void
    {
        $response = $this->post(route('clients.store'), $this->payload());

        $client = Client::sole();
        $response->assertRedirect(route('clients.show', $client));

        $this->assertSame('987654321', $client->whatsapp);
        $this->assertSame('ventas@abc.pe', $client->email);
        $this->assertNull($client->trade_name);
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'client.created', 'subject_id' => $client->id, 'subject_type' => 'client']);
    }

    public function test_rejects_invalid_ruc_and_dni(): void
    {
        $this->post(route('clients.store'), $this->payload(['document_number' => '20131312956']))
            ->assertSessionHasErrors('document_number');

        $this->post(route('clients.store'), $this->payload(['type' => 'person', 'document_type' => 'DNI', 'document_number' => '1234567']))
            ->assertSessionHasErrors(['document_number' => 'El DNI debe tener 8 dígitos.']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_document_is_optional_when_type_is_none(): void
    {
        $this->post(route('clients.store'), $this->payload(['document_type' => 'NONE', 'document_number' => '123']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Client::sole()->document_number);
    }

    public function test_rejects_duplicated_document_but_allows_reusing_a_deleted_one(): void
    {
        $existing = Client::factory()->create(['document_number' => '20131312955']);

        $this->post(route('clients.store'), $this->payload())
            ->assertSessionHasErrors(['document_number' => 'Ya existe un cliente registrado con ese documento.']);

        $existing->delete();

        $this->post(route('clients.store'), $this->payload())->assertSessionHasNoErrors();
    }

    public function test_updating_keeps_own_document_and_logs_changed_fields(): void
    {
        $client = Client::factory()->create(['document_number' => '20131312955', 'name' => 'Nombre anterior']);

        $this->put(route('clients.update', $client), $this->payload(['name' => 'Nombre nuevo']))
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame('Nombre nuevo', $client->fresh()->name);

        $log = ActivityLog::where('action', 'client.updated')->sole();
        $this->assertSame(['from' => 'Nombre anterior', 'to' => 'Nombre nuevo'], $log->properties['changes']['name']);
    }

    public function test_status_cannot_be_an_arbitrary_value_and_unknown_fields_are_ignored(): void
    {
        $this->post(route('clients.store'), $this->payload(['status' => 'vip']))
            ->assertSessionHasErrors('status');

        $this->post(route('clients.store'), $this->payload(['id' => 999, 'deleted_at' => now()]))
            ->assertSessionHasNoErrors();

        $client = Client::sole();
        $this->assertNotSame(999, $client->id);
        $this->assertNull($client->deleted_at);
    }

    public function test_delete_is_soft_and_logged(): void
    {
        $client = Client::factory()->create();

        $this->delete(route('clients.destroy', $client))->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted($client);
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'client.deleted', 'subject_id' => $client->id]);
        $this->get(route('clients.show', $client))->assertNotFound();
    }

    public function test_index_searches_filters_and_paginates(): void
    {
        Client::factory()->create(['name' => 'Clínica San Pedro', 'document_number' => ClientFactory::validRuc()]);
        Client::factory()->create(['name' => 'Ferretería Andina']);
        Client::factory()->inactive()->create(['name' => 'Clínica Inactiva']);
        Client::factory()->count(20)->create();

        $this->get(route('clients.index', ['search' => 'clínica']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/Index')
                ->has('clients.data', 2)
                ->where('filters.search', 'clínica'));

        $this->get(route('clients.index', ['search' => 'clínica', 'status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.name', 'Clínica San Pedro'));

        $this->get(route('clients.index'))
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 15)->where('clients.meta.total', 23)->where('clients.meta.last_page', 2));
    }

    public function test_index_ignores_unknown_sort_and_filter_values(): void
    {
        Client::factory()->count(3)->create();

        $this->get(route('clients.index', ['sort' => 'password; drop table', 'status' => 'whatever', 'per_page' => 1000]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.status', null)
                ->where('filters.per_page', 15));
    }

    public function test_show_renders_summary_with_activity(): void
    {
        $this->post(route('clients.store'), $this->payload());
        $client = Client::sole();

        $this->get(route('clients.show', [$client, 'tab' => 'charges']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/Show')
                ->where('client.document', 'RUC 20131312955')
                ->where('tab', 'charges')
                ->has('activity', 1)
                ->where('activity.0.label', 'Cliente registrado'));
    }

    public function test_guests_cannot_access_clients(): void
    {
        auth()->logout();
        $client = Client::factory()->create();

        $this->get(route('clients.index'))->assertRedirect(route('login'));
        $this->post(route('clients.store'), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('clients.destroy', $client))->assertRedirect(route('login'));
        $this->assertNotSoftDeleted($client);
    }
}
