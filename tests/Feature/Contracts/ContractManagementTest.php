<?php

namespace Tests\Feature\Contracts;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-04 15:00:00', 'UTC'));
        $this->actingAs(User::factory()->create());
        $this->client = Client::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'client_id' => $this->client->id,
            'service_id' => null,
            'name' => 'Hosting empresarial',
            'description' => '',
            'currency' => 'PEN',
            'price' => '350',
            'billing_type' => 'recurring',
            'interval_unit' => 'year',
            'interval_count' => '1',
            'start_date' => '2026-10-10',
            'has_term' => true,
            'term_unit' => 'year',
            'term_count' => '5',
            'first_cycle' => '1',
            'first_charge_date' => '2026-10-10',
            'notes' => '',
            ...$overrides,
        ];
    }

    public function test_registers_a_five_year_annual_contract(): void
    {
        $this->post(route('contracts.store'), $this->payload())->assertSessionHasNoErrors();

        $contract = ClientService::sole();
        $this->assertSame(BillingType::Recurring, $contract->billing_type);
        $this->assertSame(IntervalUnit::Year, $contract->interval_unit);
        $this->assertSame(60, $contract->term_months);
        $this->assertSame(5, $contract->totalCycles());
        $this->assertSame('2031-10-09', $contract->end_date->toDateString());
        // El ciclo 1 (10/10/2026) entra en el horizonte de 45 días y se generó al guardar.
        $this->assertSame('2026-10-10', $contract->charges()->sole()->due_date->toDateString());
        $this->assertSame(2, $contract->next_cycle_number);
        $this->assertSame('2027-10-10', $contract->next_charge_date->toDateString());
        $this->assertSame('350.00', (string) $contract->price->getAmount());
        $this->assertSame(ContractStatus::Active, $contract->status);
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'contract.created', 'subject_type' => 'contract']);
    }

    public function test_term_must_be_an_exact_number_of_cycles(): void
    {
        $this->post(route('contracts.store'), $this->payload(['term_unit' => 'month', 'term_count' => '18']))
            ->assertSessionHasErrors(['term_count' => 'La duración debe ser un número exacto de ciclos (Anual).']);

        $this->post(route('contracts.store'), $this->payload(['interval_unit' => 'month', 'interval_count' => '6', 'term_unit' => 'month', 'term_count' => '18']))
            ->assertSessionHasNoErrors();
    }

    public function test_first_cycle_must_be_inside_the_term(): void
    {
        $this->post(route('contracts.store'), $this->payload(['first_cycle' => '6']))
            ->assertSessionHasErrors(['first_cycle' => 'El primer cobro debe estar dentro de la duración del contrato.']);
    }

    public function test_twelve_month_frequency_is_stored_as_annual(): void
    {
        $this->post(route('contracts.store'), $this->payload(['interval_unit' => 'month', 'interval_count' => '12', 'has_term' => false]));

        $contract = ClientService::sole();
        $this->assertSame(IntervalUnit::Year, $contract->interval_unit);
        $this->assertSame(1, $contract->interval_count);
        $this->assertNull($contract->term_months);
        $this->assertNull($contract->totalCycles());
    }

    public function test_one_time_contract_ignores_frequency_and_term(): void
    {
        $this->post(route('contracts.store'), $this->payload([
            'name' => 'Desarrollo web',
            'price' => '2500',
            'billing_type' => 'one_time',
            'first_cycle' => '9',
            'first_charge_date' => '2026-10-20',
        ]))->assertSessionHasNoErrors();

        $contract = ClientService::sole();
        $this->assertNull($contract->interval_unit);
        $this->assertNull($contract->term_months);
        // Su único cobro se generó con la fecha indicada y ya no quedan ciclos.
        $this->assertSame('2026-10-20', $contract->charges()->sole()->due_date->toDateString());
        $this->assertNull($contract->next_charge_date);
    }

    public function test_contract_started_in_the_past_starts_billing_from_the_chosen_cycle(): void
    {
        $this->post(route('contracts.store'), $this->payload([
            'start_date' => '2021-10-10',
            'has_term' => false,
            'first_cycle' => '6',
            'first_charge_date' => '2026-10-15', // Movida a mano.
        ]))->assertSessionHasNoErrors();

        $contract = ClientService::sole();
        $this->get(route('contracts.show', $contract))
            ->assertInertia(fn (Assert $page) => $page
                ->component('contracts/Show')
                // El ciclo 6 ya se generó como cobro, con la fecha movida a mano.
                ->where('charges.0.cycle_number', 6)
                ->where('charges.0.due_date', '2026-10-15')
                // Los siguientes vuelven a la fecha ancla.
                ->where('upcoming.0.cycle', 7)
                ->where('upcoming.0.due_date', '2027-10-10')
                ->where('upcoming.0.adjusted', false));
    }

    public function test_schedule_preview_suggests_the_next_cycle_on_or_after_today(): void
    {
        $this->postJson(route('contracts.schedule'), [
            'start_date' => '2021-10-10',
            'billing_type' => 'recurring',
            'interval_unit' => 'year',
            'interval_count' => 1,
            'term_months' => null,
        ])
            ->assertOk()
            ->assertJsonPath('label', 'Anual')
            ->assertJsonPath('suggested_first_cycle', 6)
            ->assertJsonPath('cycles.0.cycle', 1)
            ->assertJsonPath('cycles.5.due_date', '2026-10-10');
    }

    public function test_schedule_preview_rejects_term_that_does_not_fit(): void
    {
        $this->postJson(route('contracts.schedule'), [
            'start_date' => '2026-10-10',
            'billing_type' => 'recurring',
            'interval_unit' => 'year',
            'interval_count' => 1,
            'term_months' => 18,
        ])->assertStatus(422);
    }

    public function test_catalog_service_can_be_linked_and_its_deletion_does_not_affect_the_contract(): void
    {
        $service = Service::factory()->recurring()->create(['name' => 'Hosting', 'default_price' => '300.00']);

        $this->post(route('contracts.store'), $this->payload(['service_id' => $service->id, 'price' => '350']));
        $service->delete();

        $contract = ClientService::sole();
        $this->assertSame($service->id, $contract->service_id);
        $this->assertSame('350.00', (string) $contract->price->getAmount());
        $this->get(route('contracts.show', $contract))->assertOk();
    }

    public function test_update_changes_price_and_keeps_client(): void
    {
        $contract = ClientService::factory()->create(['client_id' => $this->client->id]);
        $other = Client::factory()->create();

        $this->put(route('contracts.update', $contract), $this->payload(['client_id' => $other->id, 'price' => '400']))
            ->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame('400.00', (string) $contract->price->getAmount());
        $this->assertSame($this->client->id, $contract->client_id);

        $log = ActivityLog::where('action', 'contract.updated')->sole();
        $this->assertSame(['from' => '350.00', 'to' => '400.00'], $log->properties['changes']['price']);
    }

    public function test_cancel_stops_future_charges_and_blocks_edits(): void
    {
        $contract = ClientService::factory()->create(['client_id' => $this->client->id]);

        $this->post(route('contracts.cancel', $contract), ['reason' => 'Cliente migró a otro proveedor', 'cancel_pending_charges' => true])->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame(ContractStatus::Cancelled, $contract->status);
        $this->assertNull($contract->next_charge_date);
        $this->assertSame('2026-10-04 15:00:00', $contract->getRawOriginal('cancelled_at'));
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'contract.cancelled', 'subject_id' => $contract->id]);

        $this->put(route('contracts.update', $contract), $this->payload())->assertSessionHasErrors('contract');
        $this->post(route('contracts.cancel', $contract), ['cancel_pending_charges' => true])->assertSessionHasErrors('contract');
    }

    public function test_client_with_active_contracts_cannot_be_deleted(): void
    {
        $contract = ClientService::factory()->create(['client_id' => $this->client->id]);

        $this->delete(route('clients.destroy', $this->client))->assertSessionHasErrors('client');
        $this->assertNotSoftDeleted($this->client);

        $contract->forceFill(['status' => ContractStatus::Cancelled])->save();

        $this->delete(route('clients.destroy', $this->client))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($this->client);
    }

    public function test_client_page_shows_contracts_tab_and_active_count(): void
    {
        ClientService::factory()->create(['client_id' => $this->client->id]);
        ClientService::factory()->cancelled()->create(['client_id' => $this->client->id, 'name' => 'Dominio']);

        $this->get(route('clients.show', [$this->client, 'tab' => 'services']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.active_contracts', 1)
                ->has('contracts', 2)
                ->where('contracts.0.status', 'active'));
    }

    public function test_index_defaults_to_active_contracts_ordered_by_next_charge(): void
    {
        ClientService::factory()->create(['client_id' => $this->client->id, 'name' => 'Tarde', 'next_charge_date' => '2027-01-01']);
        ClientService::factory()->create(['client_id' => $this->client->id, 'name' => 'Pronto', 'next_charge_date' => '2026-10-20']);
        ClientService::factory()->cancelled()->create(['client_id' => $this->client->id]);

        $this->get(route('contracts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('contracts/Index')
                ->has('contracts.data', 2)
                ->where('contracts.data.0.name', 'Pronto'));

        $this->get(route('contracts.index', ['status' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 3));
    }

    public function test_client_search_returns_only_active_clients(): void
    {
        Client::factory()->create(['name' => 'Ferretería Andina']);
        Client::factory()->inactive()->create(['name' => 'Ferretería Cerrada']);

        $this->getJson(route('clients.search', ['q' => 'ferre']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ferretería Andina');
    }
}
