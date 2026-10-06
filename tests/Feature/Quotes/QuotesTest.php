<?php

namespace Tests\Feature\Quotes;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuotesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:00:00', 'UTC'));
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
            'currency' => 'PEN',
            'issue_date' => '2026-10-06',
            'valid_until' => '2026-10-21',
            'delivery_date' => '2026-11-15',
            'tax_rate' => '18',
            'global_discount_type' => '',
            'global_discount_value' => '',
            'intro' => 'Estimado cliente:',
            'observations' => 'Incluye capacitación.',
            'terms' => '50% de adelanto.',
            'internal_notes' => 'Cliente pidió descuento.',
            'items' => [
                ['service_id' => null, 'name' => 'Desarrollo web', 'description' => 'Sitio corporativo', 'quantity' => '1', 'unit_price' => '2500', 'discount_type' => '', 'discount_value' => ''],
                ['service_id' => null, 'name' => 'Hosting', 'description' => '', 'quantity' => '1', 'unit_price' => '350', 'discount_type' => '', 'discount_value' => ''],
                ['service_id' => null, 'name' => 'Dominio', 'description' => '', 'quantity' => '1', 'unit_price' => '80', 'discount_type' => '', 'discount_value' => ''],
            ],
            ...$overrides,
        ];
    }

    public function test_creates_a_numbered_draft_with_server_side_totals(): void
    {
        $this->post(route('quotes.store'), $this->payload())->assertSessionHasNoErrors();

        $quote = Quote::sole();
        $this->assertSame('COT-2026-0001', $quote->number);
        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertSame('2930.00', (string) $quote->total->getAmount());
        $this->assertSame('2483.05', (string) $quote->tax_base->getAmount());
        $this->assertSame('446.95', (string) $quote->tax_amount->getAmount());
        $this->assertSame('2026-11-15', $quote->delivery_date->toDateString());
        $this->assertSame('Incluye capacitación.', $quote->observations);
        $this->assertSame(48, strlen($quote->public_token));
        $this->assertSame(['Desarrollo web', 'Hosting', 'Dominio'], $quote->items->pluck('name')->all());
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'quote.created', 'subject_type' => 'quote']);
    }

    public function test_numbers_are_correlative_per_year_and_never_reused(): void
    {
        $this->post(route('quotes.store'), $this->payload());
        $this->post(route('quotes.store'), $this->payload());
        $this->post(route('quotes.cancel', Quote::where('number', 'COT-2026-0002')->sole()));
        $this->post(route('quotes.store'), $this->payload());
        $this->post(route('quotes.store'), $this->payload(['issue_date' => '2027-01-05', 'valid_until' => '2027-01-20']));

        $this->assertSame(['COT-2026-0001', 'COT-2026-0002', 'COT-2026-0003', 'COT-2027-0001'], Quote::orderBy('id')->pluck('number')->all());
    }

    public function test_the_number_is_independent_of_the_database_id(): void
    {
        $this->post(route('quotes.store'), $this->payload());
        $quote = Quote::sole();

        $this->put(route('quotes.update', $quote), $this->payload(['issue_date' => '2027-02-01', 'valid_until' => '2027-02-15']))->assertSessionHasNoErrors();

        $this->assertSame('COT-2026-0001', $quote->fresh()->number); // Editar la fecha no renumera.
    }

    public function test_totals_are_recalculated_on_the_server_ignoring_client_values(): void
    {
        $this->post(route('quotes.store'), $this->payload([
            'total' => '1.00', // Un total enviado por el navegador no se usa.
            'global_discount_type' => 'percent',
            'global_discount_value' => '5',
            'items' => [
                ['name' => 'Horas', 'quantity' => '1.5', 'unit_price' => '120.33', 'discount_type' => 'percent', 'discount_value' => '10'],
                ['name' => 'Licencias', 'quantity' => '3', 'unit_price' => '99.99', 'discount_type' => 'amount', 'discount_value' => '20'],
            ],
        ]))->assertSessionHasNoErrors();

        $quote = Quote::sole();
        $this->assertSame('420.30', (string) $quote->total->getAmount());
        $this->assertSame('60.17', (string) $quote->discount_total->getAmount());
        $this->assertSame(['162.45', '279.97'], $quote->items->pluck('line_total')->all());
    }

    public function test_validation_rejects_impossible_discounts_on_the_exact_field(): void
    {
        $items = $this->payload()['items'];
        $items[1]['discount_type'] = 'amount';
        $items[1]['discount_value'] = '400';

        $this->post(route('quotes.store'), $this->payload(['items' => $items]))
            ->assertSessionHasErrors(['items.1.discount_value' => 'El descuento no puede ser mayor que el importe.']);

        $this->post(route('quotes.store'), $this->payload(['items' => []]))->assertSessionHasErrors(['items' => 'Agrega al menos un concepto.']);
        $this->post(route('quotes.store'), $this->payload(['valid_until' => '2026-10-01']))->assertSessionHasErrors('valid_until');
        $this->assertSame(0, Quote::count());
    }

    public function test_zero_tax_rate_is_valid(): void
    {
        $this->post(route('quotes.store'), $this->payload(['tax_rate' => '0']))->assertSessionHasNoErrors();

        $this->assertTrue(Quote::sole()->tax_amount->isZero());
    }

    public function test_catalog_items_keep_the_suggested_billing_for_later_conversion(): void
    {
        $hosting = Service::factory()->recurring('year', 1)->create(['name' => 'Hosting']);
        $items = $this->payload()['items'];
        $items[1]['service_id'] = $hosting->id;

        $this->post(route('quotes.store'), $this->payload(['items' => $items]));

        $item = Quote::sole()->items[1];
        $this->assertSame(BillingType::Recurring, $item->suggested_billing_type);
        $this->assertSame(IntervalUnit::Year, $item->suggested_interval_unit);
        $this->assertNull(Quote::sole()->items[0]->suggested_billing_type);
    }

    public function test_item_order_and_line_totals_are_preserved_when_mixing_catalog_and_custom_items(): void
    {
        // Regresión: validated() devolvía primero los conceptos con service_id y alteraba el orden.
        $hosting = Service::factory()->create(['name' => 'Hosting']);
        $items = $this->payload()['items'];
        $items[1]['service_id'] = $hosting->id;

        $this->post(route('quotes.store'), $this->payload(['items' => $items]))->assertSessionHasNoErrors();

        $quote = Quote::sole();
        $this->assertSame(['Desarrollo web', 'Hosting', 'Dominio'], $quote->items->pluck('name')->all());
        $this->assertSame(['2500.00', '350.00', '80.00'], $quote->items->pluck('line_total')->all());
        $this->assertSame([1, 2, 3], $quote->items->pluck('position')->all());
    }

    public function test_status_transitions_and_locking(): void
    {
        $this->post(route('quotes.store'), $this->payload());
        $quote = Quote::sole();

        $this->post(route('quotes.send', $quote));
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->sent_at);

        $this->post(route('quotes.draft', $quote));
        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);

        $this->post(route('quotes.cancel', $quote), ['reason' => 'No aprobado']);
        $this->assertSame(QuoteStatus::Cancelled, $quote->fresh()->status);

        $this->put(route('quotes.update', $quote), $this->payload())->assertSessionHasErrors('quote');
        $this->get(route('quotes.edit', $quote))->assertRedirect(route('quotes.show', $quote));
    }

    public function test_expired_is_calculated_from_validity(): void
    {
        $this->post(route('quotes.store'), $this->payload(['valid_until' => '2026-10-06']));
        $this->post(route('quotes.store'), $this->payload(['issue_date' => '2026-09-01', 'valid_until' => '2026-09-15']));

        $this->get(route('quotes.index', ['status' => 'expired']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/Index')
                ->has('quotes.data', 1)
                ->where('quotes.data.0.display_status', 'expired'));

        $this->get(route('quotes.index'))->assertInertia(fn (Assert $page) => $page->has('quotes.data', 2));
    }

    public function test_internal_notes_and_token_never_reach_the_document(): void
    {
        $this->post(route('quotes.store'), $this->payload());

        $this->get(route('quotes.show', Quote::sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/Show')
                ->missing('document.internal_notes')
                ->missing('document.public_token')
                ->where('document.total', '2930.00')
                ->where('document.observations', 'Incluye capacitación.'));
    }

    public function test_client_can_be_created_quickly_from_the_editor(): void
    {
        $this->postJson(route('clients.quick-store'), [
            'type' => 'company', 'name' => 'Nueva Empresa SAC', 'document_type' => 'RUC', 'document_number' => '20131312955', 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.name', 'Nueva Empresa SAC')->assertJsonPath('data.document', 'RUC 20131312955');

        $this->postJson(route('clients.quick-store'), ['type' => 'company', 'name' => '', 'document_type' => 'NONE', 'status' => 'active'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
