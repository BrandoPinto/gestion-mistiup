<?php

namespace Tests\Unit\Billing;

use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Billing\Services\RecurrenceCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RecurrenceCalculatorTest extends TestCase
{
    private RecurrenceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new RecurrenceCalculator;
    }

    private function date(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value);
    }

    private function dates(array $schedule): array
    {
        return array_map(fn ($item) => $item['due_date']->toDateString(), $schedule);
    }

    public function test_first_cycle_is_charged_in_advance_on_start_date(): void
    {
        $annual = Recurrence::of(IntervalUnit::Year, 1);

        $this->assertSame('2026-10-10', $this->calculator->dueDate($this->date('2026-10-10'), $annual, 1)->toDateString());
        $this->assertSame('2027-10-10', $this->calculator->dueDate($this->date('2026-10-10'), $annual, 2)->toDateString());
    }

    public function test_month_end_start_is_anchored_and_does_not_drift(): void
    {
        $monthly = Recurrence::of(IntervalUnit::Month, 1);

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'],
            $this->dates($this->calculator->schedule($this->date('2026-01-31'), $monthly, 1, 5)),
        );
    }

    public function test_leap_day_anchor_on_annual_recurrence(): void
    {
        $annual = Recurrence::of(IntervalUnit::Year, 1);

        $this->assertSame(
            ['2028-02-29', '2029-02-28', '2030-02-28', '2031-02-28', '2032-02-29'],
            $this->dates($this->calculator->schedule($this->date('2028-02-29'), $annual, 1, 5)),
        );
    }

    public function test_period_covers_until_the_day_before_next_due_date(): void
    {
        $period = $this->calculator->period($this->date('2026-10-10'), Recurrence::of(IntervalUnit::Year, 1), 1);

        $this->assertSame('2026-10-10', $period['start']->toDateString());
        $this->assertSame('2027-10-09', $period['end']->toDateString());
    }

    public function test_five_year_annual_contract_has_five_cycles_and_ends_the_day_before(): void
    {
        $annual = Recurrence::of(IntervalUnit::Year, 1);

        $this->assertSame(5, $this->calculator->cycleCount($annual, 60));
        $this->assertSame('2031-10-09', $this->calculator->endDate($this->date('2026-10-10'), 60)->toDateString());
        $this->assertCount(5, $this->calculator->schedule($this->date('2026-10-10'), $annual, 1, 12, maxCycles: 5));
    }

    public function test_term_must_be_an_exact_number_of_cycles(): void
    {
        $annual = Recurrence::of(IntervalUnit::Year, 1);

        $this->assertFalse($this->calculator->termFitsRecurrence($annual, 18));
        $this->assertFalse($this->calculator->termFitsRecurrence($annual, 6));
        $this->assertTrue($this->calculator->termFitsRecurrence(Recurrence::of(IntervalUnit::Month, 6), 18));

        $this->expectException(InvalidArgumentException::class);
        $this->calculator->cycleCount($annual, 18);
    }

    public function test_first_cycle_on_or_after_for_contracts_started_in_the_past(): void
    {
        $annual = Recurrence::of(IntervalUnit::Year, 1);
        $start = $this->date('2021-10-10');

        // Hoy 04/10/2026: el ciclo que vence el 10/10/2026 es el 6.º.
        $this->assertSame(6, $this->calculator->firstCycleOnOrAfter($start, $annual, $this->date('2026-10-04')));
        // El mismo día del vencimiento cuenta.
        $this->assertSame(6, $this->calculator->firstCycleOnOrAfter($start, $annual, $this->date('2026-10-10')));
        $this->assertSame(7, $this->calculator->firstCycleOnOrAfter($start, $annual, $this->date('2026-10-11')));
        // Inicio futuro: el primer ciclo.
        $this->assertSame(1, $this->calculator->firstCycleOnOrAfter($this->date('2027-01-01'), $annual, $this->date('2026-10-04')));
        // Contrato de 3 ciclos ya terminado.
        $this->assertNull($this->calculator->firstCycleOnOrAfter($start, $annual, $this->date('2026-10-04'), maxCycles: 3));
    }

    public function test_first_cycle_ignores_time_zone_of_the_dates(): void
    {
        $annual = Recurrence::of(IntervalUnit::Year, 1);
        // Regresión: "hoy" a medianoche en Lima (05:00 UTC) no debe considerarse posterior al inicio del mismo día.
        $todayInLima = CarbonImmutable::parse('2026-10-05 00:00:00', 'America/Lima');

        $this->assertSame(1, $this->calculator->firstCycleOnOrAfter($this->date('2026-10-05'), $annual, $todayInLima));
        $this->assertSame(6, $this->calculator->firstCycleOnOrAfter($this->date('2021-10-05'), $annual, $todayInLima));
    }

    public function test_first_cycle_on_or_after_with_month_end_anchor(): void
    {
        $monthly = Recurrence::of(IntervalUnit::Month, 1);

        // Ancla 31: el ciclo de febrero vence el 28; el 01/03 ya corresponde al ciclo de marzo (31/03).
        $this->assertSame(2, $this->calculator->firstCycleOnOrAfter($this->date('2026-01-31'), $monthly, $this->date('2026-02-28')));
        $this->assertSame(3, $this->calculator->firstCycleOnOrAfter($this->date('2026-01-31'), $monthly, $this->date('2026-03-01')));
    }
}
