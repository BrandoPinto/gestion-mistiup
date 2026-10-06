<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Actions\GenerateContractCharges;
use App\Domain\Billing\Enums\ChargeStatus;
use App\Models\ActivityLog;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChargesTest extends TestCase
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

    private function generate(ClientService $contract, string $today = '2026-10-04'): int
    {
        return app(GenerateContractCharges::class)->handle($contract, CarbonImmutable::parse($today));
    }

    /** @return array<string, mixed> */
    private function contractPayload(array $overrides = []): array
    {
        return [
            'client_id' => $this->client->id,
            'name' => 'Hosting empresarial',
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
            ...$overrides,
        ];
    }

    public function test_saving_a_contract_generates_charges_within_the_45_day_horizon(): void
    {
        $this->post(route('contracts.store'), $this->contractPayload())->assertSessionHasNoErrors();

        $charge = Charge::sole();
        $this->assertSame(1, $charge->cycle_number);
        $this->assertSame('2026-10-10', $charge->due_date->toDateString());
        $this->assertSame('2026-10-10', $charge->period_start->toDateString());
        $this->assertSame('2027-10-09', $charge->period_end->toDateString());
        $this->assertSame('350.00', (string) $charge->amount->getAmount());
        $this->assertSame('53.39', (string) $charge->tax_amount->getAmount());

        $contract = ClientService::sole();
        $this->assertSame(2, $contract->next_cycle_number);
        $this->assertSame('2027-10-10', $contract->next_charge_date->toDateString());
    }

    public function test_generation_is_idempotent_and_catches_up_after_missed_days(): void
    {
        $contract = ClientService::factory()->create([
            'client_id' => $this->client->id,
            'interval_unit' => 'month',
            'interval_count' => 1,
            'start_date' => '2026-08-31',
            'next_charge_date' => '2026-08-31',
        ]);

        // Hasta 04/10 + 45 días = 18/11: ciclos de ago, sep, oct y nov (anclados al 31 → 30/09, 31/10).
        $this->assertSame(3, $this->generate($contract));
        $this->assertSame(0, $this->generate($contract));
        $this->assertSame(0, $this->generate($contract));

        $this->assertSame(['2026-08-31', '2026-09-30', '2026-10-31'], Charge::orderBy('cycle_number')->pluck('due_date')->map->toDateString()->all());

        // Un mes después (cron caído varios días): genera solo lo que falta.
        $this->assertSame(1, $this->generate($contract->fresh(), '2026-11-04'));
        $this->assertSame('2026-11-30', Charge::orderByDesc('due_date')->first()->due_date->toDateString());
        $this->assertSame(4, Charge::count());
    }

    public function test_the_adjusted_first_date_only_applies_to_the_first_cycle(): void
    {
        $this->post(route('contracts.store'), $this->contractPayload([
            'start_date' => '2021-10-10',
            'has_term' => false,
            'first_cycle' => '6',
            'first_charge_date' => '2026-10-15',
        ]));

        $charge = Charge::sole();
        $this->assertSame(6, $charge->cycle_number);
        $this->assertSame('2026-10-15', $charge->due_date->toDateString());
        $this->assertSame('2027-10-10', ClientService::sole()->next_charge_date->toDateString());
    }

    public function test_generation_stops_at_the_end_of_the_term(): void
    {
        $contract = ClientService::factory()->create([
            'client_id' => $this->client->id,
            'interval_unit' => 'month',
            'interval_count' => 1,
            'start_date' => '2026-09-01',
            'term_months' => 2,
            'next_charge_date' => '2026-09-01',
        ]);

        $this->assertSame(2, $this->generate($contract, '2026-12-31'));

        $contract->refresh();
        $this->assertNull($contract->next_charge_date);
        $this->assertSame(3, $contract->next_cycle_number);
    }

    public function test_one_time_contract_generates_its_charge_once(): void
    {
        $this->post(route('contracts.store'), $this->contractPayload([
            'name' => 'Desarrollo web',
            'price' => '2500',
            'billing_type' => 'one_time',
            'first_charge_date' => '2027-03-01', // Fuera del horizonte: igual vence, pero aún no se genera.
        ]));

        $this->assertSame(0, Charge::count());
        $this->generate(ClientService::sole(), '2027-02-01');
        $this->generate(ClientService::sole(), '2027-02-02');
        $this->assertSame(1, Charge::count());
        $this->assertNull(ClientService::sole()->next_charge_date);
    }

    public function test_price_change_only_affects_charges_not_yet_generated(): void
    {
        $this->post(route('contracts.store'), $this->contractPayload());
        $contract = ClientService::sole();

        $this->put(route('contracts.update', $contract), $this->contractPayload(['price' => '400', 'start_date' => '2020-01-01']))->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame('350.00', (string) Charge::sole()->amount->getAmount());
        $this->assertSame('400.00', (string) $contract->price->getAmount());
        // Con cobros generados el calendario no cambia aunque se envíe otra fecha de inicio.
        $this->assertSame('2026-10-10', $contract->start_date->toDateString());

        $this->generate($contract, '2027-09-01');
        $this->assertSame('400.00', (string) Charge::where('cycle_number', 2)->sole()->amount->getAmount());
    }

    public function test_manual_charge(): void
    {
        $this->post(route('charges.store'), [
            'client_id' => $this->client->id,
            'description' => 'Migración de correos',
            'currency' => 'USD',
            'amount' => '120.5',
            'due_date' => '2026-10-15',
        ])->assertSessionHasNoErrors();

        $charge = Charge::sole();
        $this->assertNull($charge->client_service_id);
        $this->assertSame('120.50', (string) $charge->amount->getAmount());
        $this->assertSame('USD', $charge->currency->value);

        $this->post(route('charges.store'), ['client_id' => $this->client->id, 'description' => 'x', 'currency' => 'PEN', 'amount' => '0', 'due_date' => '2026-10-15'])
            ->assertSessionHasErrors('amount');
    }

    public function test_amount_can_be_adjusted_only_without_payments_and_requires_a_reason(): void
    {
        $charge = Charge::factory()->create(['client_id' => $this->client->id, 'amount' => '350.00']);

        $this->patch(route('charges.amount', $charge), ['amount' => '300'])->assertSessionHasErrors('reason');
        $this->patch(route('charges.amount', $charge), ['amount' => '300', 'reason' => 'Descuento por pronto pago'])->assertSessionHasNoErrors();

        $this->assertSame('300.00', (string) $charge->fresh()->amount->getAmount());
        $log = ActivityLog::where('action', 'charge.amount_adjusted')->sole();
        $this->assertSame('Descuento por pronto pago', $log->properties['reason']);
        $this->assertSame(['from' => '350.00', 'to' => '300.00'], $log->properties['changes']['amount']);

        $this->post(route('payments.store', $charge), ['amount' => '100', 'paid_on' => '2026-10-04', 'payment_method_id' => PaymentMethod::value('id')]);
        $this->patch(route('charges.amount', $charge), ['amount' => '250', 'reason' => 'otro'])->assertSessionHasErrors('amount');
    }

    public function test_charge_with_payments_cannot_be_cancelled(): void
    {
        $charge = Charge::factory()->create(['client_id' => $this->client->id]);
        $this->post(route('payments.store', $charge), ['amount' => '100', 'paid_on' => '2026-10-04', 'payment_method_id' => PaymentMethod::value('id')]);

        $this->post(route('charges.cancel', $charge))->assertSessionHasErrors('charge');

        $free = Charge::factory()->create(['client_id' => $this->client->id]);
        $this->post(route('charges.cancel', $free), ['reason' => 'Error de registro'])->assertSessionHasNoErrors();
        $this->assertSame(ChargeStatus::Cancelled, $free->fresh()->status);
    }

    public function test_cancelling_a_contract_can_cancel_its_pending_charges_but_never_partial_ones(): void
    {
        $contract = ClientService::factory()->create(['client_id' => $this->client->id, 'interval_unit' => 'month', 'interval_count' => 1, 'start_date' => '2026-09-01', 'next_charge_date' => '2026-09-01']);
        $this->generate($contract);
        [$first, $second] = Charge::orderBy('cycle_number')->get()->all();
        $this->post(route('payments.store', $first), ['amount' => '100', 'paid_on' => '2026-10-04', 'payment_method_id' => PaymentMethod::value('id')]);

        $this->post(route('contracts.cancel', $contract), ['cancel_pending_charges' => true, 'reason' => 'Baja'])->assertSessionHasNoErrors();

        $this->assertSame(ChargeStatus::Partial, $first->fresh()->status);
        $this->assertSame(ChargeStatus::Cancelled, $second->fresh()->status);
        $this->assertSame(0, $this->generate($contract->fresh()));
    }

    public function test_cancelling_a_contract_can_keep_its_pending_charges(): void
    {
        $contract = ClientService::factory()->create(['client_id' => $this->client->id]);
        $this->generate($contract);

        $this->post(route('contracts.cancel', $contract), ['cancel_pending_charges' => false]);

        $this->assertSame(ChargeStatus::Pending, Charge::sole()->status);
    }

    public function test_overdue_is_calculated_from_the_date_and_totals_never_mix_currencies(): void
    {
        Charge::factory()->create(['client_id' => $this->client->id, 'due_date' => '2026-10-03', 'amount' => '100.00']);
        Charge::factory()->create(['client_id' => $this->client->id, 'due_date' => '2026-10-04', 'amount' => '200.00']); // Vence hoy: aún no está vencido.
        Charge::factory()->usd('50.00')->create(['client_id' => $this->client->id, 'due_date' => '2026-09-01']);
        Charge::factory()->create(['client_id' => $this->client->id, 'status' => 'cancelled', 'due_date' => '2026-01-01']);

        $this->get(route('charges.index', ['status' => 'overdue']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('charges/Index')
                ->has('charges.data', 2)
                ->where('charges.data.0.display_status', 'overdue')
                ->where('totals', [
                    ['currency' => 'PEN', 'amount' => '100.00', 'paid' => '0.00', 'balance' => '100.00', 'count' => 1],
                    ['currency' => 'USD', 'amount' => '50.00', 'paid' => '0.00', 'balance' => '50.00', 'count' => 1],
                ]));

        $this->get(route('clients.show', $this->client))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.pending', [['currency' => 'PEN', 'amount' => '300.00'], ['currency' => 'USD', 'amount' => '50.00']])
                ->where('stats.overdue', [['currency' => 'PEN', 'amount' => '100.00'], ['currency' => 'USD', 'amount' => '50.00']])
                ->where('stats.next_due_date', '2026-10-04'));
    }
}
