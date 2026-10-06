<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Queries\DashboardSummaryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-15 15:00:00', 'UTC'));
        $this->actingAs(User::factory()->create());
        $this->client = Client::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pay(Charge $charge, string $amount, string $date): void
    {
        $this->post(route('payments.store', $charge), ['amount' => $amount, 'paid_on' => $date, 'payment_method_id' => PaymentMethod::value('id')])->assertSessionHasNoErrors();
    }

    private function charge(array $attributes): Charge
    {
        return Charge::factory()->create(['client_id' => $this->client->id, ...$attributes]);
    }

    public function test_collected_this_month_is_split_by_currency_and_ignores_voided_and_other_months(): void
    {
        $pen = $this->charge(['amount' => '1000.00', 'due_date' => '2026-10-01']);
        $usd = $this->charge(['amount' => '500.00', 'currency' => 'USD', 'tax_amount' => '0.00', 'due_date' => '2026-10-01']);

        $this->pay($pen, '300', '2026-10-02');
        $this->pay($pen, '200', '2026-09-10'); // Mismo tramo del mes anterior (1 al 15).
        $this->pay($pen, '50', '2026-09-28'); // Mes anterior, fuera del tramo comparable.
        $this->pay($usd, '150', '2026-10-10');
        $this->pay($pen, '100', '2026-10-12');
        $this->post(route('payments.void', Payment::where('amount', '100.00')->sole()), ['reason' => 'error']);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Index')
            ->where('collected.this_month', [
                ['currency' => 'PEN', 'amount' => '300.00', 'count' => 1],
                ['currency' => 'USD', 'amount' => '150.00', 'count' => 1],
            ])
            ->where('collected.previous_period', [['currency' => 'PEN', 'amount' => '200.00', 'count' => 1]]));
    }

    public function test_receivables_overdue_and_upcoming_by_currency(): void
    {
        $this->charge(['amount' => '350.00', 'due_date' => '2026-10-10']); // Vencido.
        $partial = $this->charge(['amount' => '1000.00', 'due_date' => '2026-10-20']); // Por vencer.
        $this->charge(['amount' => '80.00', 'due_date' => '2027-03-01']); // Más allá de 30 días.
        $this->charge(['amount' => '99.00', 'currency' => 'USD', 'tax_amount' => '0.00', 'due_date' => '2026-10-01']);
        $this->charge(['amount' => '500.00', 'status' => 'cancelled', 'due_date' => '2026-10-01']);
        $this->pay($partial, '400', '2026-10-15');

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('receivables', [
                ['currency' => 'PEN', 'balance' => '1030.00', 'overdue' => '350.00', 'overdue_count' => 1, 'upcoming' => '600.00'],
                ['currency' => 'USD', 'balance' => '99.00', 'overdue' => '99.00', 'overdue_count' => 1, 'upcoming' => '0.00'],
            ])
            ->has('overdue', 2)
            ->has('upcoming', 1)
            ->where('upcoming.0.balance', ['amount' => '600.00', 'currency' => 'PEN']));
    }

    public function test_monthly_income_has_twelve_months_and_keeps_currencies_apart(): void
    {
        $pen = $this->charge(['amount' => '5000.00', 'due_date' => '2026-01-01']);
        $usd = $this->charge(['amount' => '500.00', 'currency' => 'USD', 'tax_amount' => '0.00', 'due_date' => '2026-01-01']);
        $this->pay($pen, '100', '2025-10-31'); // Fuera de los 12 meses (nov-2025 a oct-2026).
        $this->pay($pen, '250.50', '2025-11-01');
        $this->pay($pen, '0.25', '2026-10-01');
        $this->pay($pen, '0.05', '2026-10-15');
        $this->pay($usd, '40', '2026-10-03');

        $income = app(DashboardSummaryQuery::class)->monthlyIncome(CarbonImmutable::parse('2026-10-15'));

        $this->assertCount(12, $income);
        $this->assertSame('2025-11', $income[0]['month']);
        $this->assertSame(['PEN' => '250.50', 'USD' => '0.00'], $income[0]['totals']);
        $this->assertSame('2026-10', $income[11]['month']);
        $this->assertSame(['PEN' => '0.30', 'USD' => '40.00'], $income[11]['totals']);
    }

    public function test_dashboard_runs_a_bounded_number_of_queries(): void
    {
        foreach (range(1, 15) as $index) {
            $this->charge(['amount' => '100.00', 'due_date' => '2026-10-'.str_pad((string) (16 + ($index % 10)), 2, '0', STR_PAD_LEFT)]);
        }

        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        $queries = count(DB::getQueryLog());

        // Sin N+1: el número de consultas no crece con la cantidad de cobros.
        $this->assertLessThan(30, $queries);
    }
}
