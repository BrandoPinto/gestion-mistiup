<?php

namespace Tests\Feature\Quotes;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Models\ActivityLog;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuoteConversionTest extends TestCase
{
    use RefreshDatabase;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:00:00', 'UTC'));
        $this->actingAs(User::factory()->create());

        $hosting = Service::factory()->recurring('year', 1)->create(['name' => 'Hosting']);
        $domain = Service::factory()->recurring('year', 1)->create(['name' => 'Dominio']);

        // Ejemplo del requerimiento, con 10% de descuento global (293.00).
        $this->post(route('quotes.store'), [
            'client_id' => Client::factory()->create()->id,
            'currency' => 'PEN',
            'issue_date' => '2026-10-06',
            'valid_until' => '2026-10-21',
            'tax_rate' => '18',
            'global_discount_type' => 'percent',
            'global_discount_value' => '10',
            'items' => [
                ['name' => 'Desarrollo web', 'quantity' => '1', 'unit_price' => '2500'],
                ['service_id' => $hosting->id, 'name' => 'Hosting', 'quantity' => '1', 'unit_price' => '350'],
                ['service_id' => $domain->id, 'name' => 'Dominio', 'quantity' => '1', 'unit_price' => '80'],
            ],
        ])->assertSessionHasNoErrors();

        $this->quote = Quote::sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function itemId(int $position): int
    {
        return $this->quote->items()->where('position', $position)->value('id');
    }

    /** @return array<string, mixed> */
    private function line(int $position, array $overrides = []): array
    {
        return [
            'quote_item_id' => $this->itemId($position),
            'name' => 'Servicio',
            'price' => '100',
            'billing_type' => 'one_time',
            'interval_unit' => '',
            'interval_count' => '',
            'start_date' => '2026-10-10',
            'has_term' => false,
            'term_unit' => 'year',
            'term_count' => '',
            'first_charge_date' => '2026-10-10',
            ...$overrides,
        ];
    }

    public function test_conversion_page_proposes_net_prices_with_prorated_global_discount(): void
    {
        $this->get(route('quotes.convert', $this->quote))
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/Convert')
                ->where('items.0.net_price', '2250.00')
                ->where('items.1.net_price', '315.00')
                ->where('items.2.net_price', '72.00')
                ->where('items.0.suggested_billing_type', 'one_time')
                ->where('items.1.suggested_billing_type', 'recurring')
                ->where('items.1.suggested_interval_unit', 'year'));
    }

    public function test_converting_each_item_with_its_own_billing_mode_from_the_requirements(): void
    {
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [
            $this->line(1, ['name' => 'Desarrollo web', 'price' => '2250.00']),
            $this->line(2, ['name' => 'Hosting', 'price' => '315.00', 'billing_type' => 'recurring', 'interval_unit' => 'year', 'interval_count' => '1']),
            $this->line(3, ['name' => 'Dominio', 'price' => '72.00', 'billing_type' => 'recurring', 'interval_unit' => 'year', 'interval_count' => '1']),
        ]])->assertSessionHasNoErrors()->assertRedirect(route('quotes.show', $this->quote));

        $this->assertSame(QuoteStatus::Converted, $this->quote->fresh()->status);
        $this->assertNotNull($this->quote->fresh()->converted_at);

        $contracts = ClientService::orderBy('id')->get();
        $this->assertSame([BillingType::OneTime, BillingType::Recurring, BillingType::Recurring], $contracts->pluck('billing_type')->all());
        $this->assertSame(['2250.00', '315.00', '72.00'], $contracts->map(fn ($contract) => (string) $contract->price->getAmount())->all());
        $this->assertSame($this->quote->client_id, $contracts->first()->client_id);
        $this->assertSame($this->itemId(1), $contracts->first()->quote_item_id);

        // Los contratos suman exactamente el total cotizado y sus primeros cobros ya se generaron.
        $this->assertSame('2637.00', (string) $this->quote->fresh()->total->getAmount());
        $this->assertSame(3, Charge::count());
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'quote.converted', 'subject_id' => $this->quote->id]);
    }

    public function test_partial_conversion_then_the_rest(): void
    {
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$this->line(1)]])->assertSessionHasNoErrors();
        $this->assertSame(QuoteStatus::PartiallyConverted, $this->quote->fresh()->status);

        // La cotización ya no se edita, pero sí se puede convertir el resto.
        $this->get(route('quotes.edit', $this->quote))->assertRedirect(route('quotes.show', $this->quote));
        $this->get(route('quotes.convert', $this->quote))->assertInertia(fn (Assert $page) => $page->where('items.0.contract.name', 'Servicio'));

        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$this->line(2), $this->line(3)]])->assertSessionHasNoErrors();
        $this->assertSame(QuoteStatus::Converted, $this->quote->fresh()->status);
    }

    public function test_an_item_cannot_be_converted_twice(): void
    {
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$this->line(1)]]);

        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$this->line(1)]])->assertSessionHasErrors('quote');
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$this->line(2), $this->line(2)]])->assertSessionHasErrors('items.1.quote_item_id');

        $this->assertSame(1, ClientService::count());
    }

    public function test_conversion_is_all_or_nothing(): void
    {
        // El segundo concepto tiene una duración imposible: no se crea ninguno.
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [
            $this->line(1),
            $this->line(2, ['billing_type' => 'recurring', 'interval_unit' => 'year', 'interval_count' => '1', 'has_term' => true, 'term_unit' => 'month', 'term_count' => '18']),
        ]])->assertSessionHasErrors('items.1.term_count');

        $this->assertSame(0, ClientService::count());
        $this->assertSame(QuoteStatus::Draft, $this->quote->fresh()->status);
    }

    public function test_items_from_another_quote_or_cancelled_quotes_are_rejected(): void
    {
        $this->post(route('quotes.store'), ['client_id' => $this->quote->client_id, 'currency' => 'PEN', 'issue_date' => '2026-10-06', 'valid_until' => '2026-10-21', 'tax_rate' => '18', 'items' => [['name' => 'Otro', 'quantity' => '1', 'unit_price' => '10']]]);
        $other = Quote::where('id', '!=', $this->quote->id)->sole();

        $foreign = $this->line(1, ['quote_item_id' => $other->items()->value('id')]);
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$foreign]])->assertSessionHasErrors('items.0.quote_item_id');

        $this->post(route('quotes.cancel', $this->quote));
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$this->line(1)]])->assertSessionHasErrors('quote');
        $this->assertSame(0, ClientService::count());
    }

    public function test_contract_keeps_a_link_to_its_origin_quote(): void
    {
        $this->post(route('quotes.convert.store', $this->quote), ['items' => [$this->line(1)]]);

        $this->get(route('contracts.show', ClientService::sole()))
            ->assertInertia(fn (Assert $page) => $page->where('contract.origin_quote.number', 'COT-2026-0001'));

        $this->get(route('quotes.show', $this->quote))
            ->assertInertia(fn (Assert $page) => $page->has('conversions', 1)->where('quote.convertible', true));
    }
}
