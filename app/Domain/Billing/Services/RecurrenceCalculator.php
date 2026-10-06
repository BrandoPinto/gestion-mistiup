<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Recurrence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Cálculo puro de ciclos de cobro. Sin base de datos ni reloj: recibe todas las fechas.
 *
 * Regla de anclaje: el ciclo N vence en (inicio + (N−1) × frecuencia), calculado SIEMPRE desde la fecha
 * de inicio y nunca encadenando el vencimiento anterior. Si el día no existe en el mes, se usa el último
 * día del mes: un inicio el 31/01 vence 28/02, 31/03, 30/04… (no se "corre" al 28).
 */
class RecurrenceCalculator
{
    /** Vencimiento natural del ciclo N (1 = primer ciclo, que vence en la fecha de inicio: cobro adelantado). */
    public function dueDate(CarbonImmutable $start, Recurrence $recurrence, int $cycle): CarbonImmutable
    {
        if ($cycle < 1) {
            throw new InvalidArgumentException('El número de ciclo empieza en 1.');
        }

        return $start->startOfDay()->addMonthsNoOverflow(($cycle - 1) * $recurrence->months());
    }

    /**
     * Periodo que cubre el ciclo N: desde su vencimiento hasta el día anterior al siguiente.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function period(CarbonImmutable $start, Recurrence $recurrence, int $cycle): array
    {
        return [
            'start' => $this->dueDate($start, $recurrence, $cycle),
            'end' => $this->dueDate($start, $recurrence, $cycle + 1)->subDay(),
        ];
    }

    /**
     * Cantidad de ciclos de un contrato con duración. La duración debe ser múltiplo exacto de la frecuencia.
     */
    public function cycleCount(Recurrence $recurrence, int $termMonths): int
    {
        if (! $this->termFitsRecurrence($recurrence, $termMonths)) {
            throw new InvalidArgumentException('La duración debe ser un número exacto de ciclos.');
        }

        return intdiv($termMonths, $recurrence->months());
    }

    public function termFitsRecurrence(Recurrence $recurrence, int $termMonths): bool
    {
        return $termMonths >= $recurrence->months() && $termMonths % $recurrence->months() === 0;
    }

    /** Último día cubierto por un contrato de N meses: inicio 10/10/2026 + 5 años → 09/10/2031. */
    public function endDate(CarbonImmutable $start, int $termMonths): CarbonImmutable
    {
        return $start->startOfDay()->addMonthsNoOverflow($termMonths)->subDay();
    }

    /**
     * Primer ciclo cuyo vencimiento es igual o posterior a la fecha dada (null si el contrato ya terminó).
     */
    public function firstCycleOnOrAfter(CarbonImmutable $start, Recurrence $recurrence, CarbonImmutable $date, ?int $maxCycles = null): ?int
    {
        // Se comparan fechas de calendario ("Y-m-d"), nunca instantes: la zona horaria de cada objeto no influye.
        $target = $date->toDateString();
        $start = CarbonImmutable::parse($start->toDateString());

        if ($target <= $start->toDateString()) {
            return 1;
        }

        // Estimación por meses transcurridos y ajuste fino: evita iterar sobre años de ciclos.
        $elapsedMonths = (int) $start->diffInMonths(CarbonImmutable::parse($target), absolute: true);
        $cycle = max(1, intdiv($elapsedMonths, $recurrence->months()));

        while ($this->dueDate($start, $recurrence, $cycle)->toDateString() < $target) {
            $cycle++;
        }

        while ($cycle > 1 && $this->dueDate($start, $recurrence, $cycle - 1)->toDateString() >= $target) {
            $cycle--;
        }

        return $maxCycles !== null && $cycle > $maxCycles ? null : $cycle;
    }

    /**
     * Calendario desde un ciclo dado.
     *
     * @return list<array{cycle: int, due_date: CarbonImmutable, period_start: CarbonImmutable, period_end: CarbonImmutable}>
     */
    public function schedule(CarbonImmutable $start, Recurrence $recurrence, int $fromCycle, int $limit, ?int $maxCycles = null): array
    {
        $items = [];
        $last = $maxCycles === null ? $fromCycle + $limit - 1 : min($maxCycles, $fromCycle + $limit - 1);

        for ($cycle = $fromCycle; $cycle <= $last; $cycle++) {
            $period = $this->period($start, $recurrence, $cycle);
            $items[] = [
                'cycle' => $cycle,
                'due_date' => $period['start'],
                'period_start' => $period['start'],
                'period_end' => $period['end'],
            ];
        }

        return $items;
    }
}
