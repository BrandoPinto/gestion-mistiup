<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Billing\Recurrence;
use App\Domain\Billing\Services\RecurrenceCalculator;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\ClientService;
use Carbon\CarbonImmutable;

/**
 * Calendario de cobros de un contrato, para mostrar. Toda la aritmética de fechas vive en
 * RecurrenceCalculator; el frontend nunca calcula vencimientos.
 */
class ContractSchedule
{
    /** Ciclos ofrecidos alrededor del sugerido al elegir el primer cobro. */
    private const OPTIONS_AROUND = 12;

    public function __construct(private readonly RecurrenceCalculator $calculator) {}

    /**
     * Vista previa para el formulario: ciclos elegibles como primer cobro y el sugerido
     * (el primero que vence hoy o después; los anteriores se consideran cobrados fuera del sistema).
     *
     * @return array{suggested_first_cycle: int|null, total_cycles: int|null, end_date: string|null, cycles: list<array<string, mixed>>}
     */
    public function preview(CarbonImmutable $start, ?Recurrence $recurrence, ?int $termMonths, CarbonImmutable $today): array
    {
        if ($recurrence === null) {
            return [
                'suggested_first_cycle' => 1,
                'total_cycles' => 1,
                'end_date' => null,
                'cycles' => [['cycle' => 1, 'due_date' => $start->toDateString(), 'period_start' => null, 'period_end' => null]],
            ];
        }

        $total = $termMonths ? $this->calculator->cycleCount($recurrence, $termMonths) : null;
        $suggested = $this->calculator->firstCycleOnOrAfter($start, $recurrence, $today, $total);
        $pivot = $suggested ?? $total ?? 1;
        $from = max(1, $pivot - self::OPTIONS_AROUND);

        return [
            'suggested_first_cycle' => $suggested,
            'total_cycles' => $total,
            'end_date' => $termMonths ? $this->calculator->endDate($start, $termMonths)->toDateString() : null,
            'cycles' => $this->format($this->calculator->schedule($start, $recurrence, $from, $pivot - $from + 1 + self::OPTIONS_AROUND, $total)),
        ];
    }

    /**
     * Próximos cobros pendientes de generar de un contrato. El primero usa next_charge_date
     * (que pudo moverse a mano); los siguientes, su fecha natural anclada al inicio.
     *
     * @return list<array<string, mixed>>
     */
    public function upcoming(ClientService $contract, int $limit = 6): array
    {
        if ($contract->status !== ContractStatus::Active || $contract->next_charge_date === null) {
            return [];
        }

        $recurrence = $contract->recurrence();

        if ($recurrence === null) {
            return [[
                'cycle' => 1,
                'due_date' => $contract->next_charge_date->toDateString(),
                'period_start' => null,
                'period_end' => null,
                'adjusted' => ! $contract->next_charge_date->equalTo($contract->start_date),
            ]];
        }

        $items = $this->format($this->calculator->schedule(
            $contract->start_date,
            $recurrence,
            $contract->next_cycle_number,
            $limit,
            $contract->totalCycles(),
        ));

        if ($items !== []) {
            $adjusted = $items[0]['due_date'] !== $contract->next_charge_date->toDateString();
            $items[0]['due_date'] = $contract->next_charge_date->toDateString();
            $items[0]['adjusted'] = $adjusted;
        }

        return $items;
    }

    /**
     * @param  list<array{cycle: int, due_date: CarbonImmutable, period_start: CarbonImmutable, period_end: CarbonImmutable}>  $schedule
     * @return list<array<string, mixed>>
     */
    private function format(array $schedule): array
    {
        return array_map(fn (array $item) => [
            'cycle' => $item['cycle'],
            'due_date' => $item['due_date']->toDateString(),
            'period_start' => $item['period_start']->toDateString(),
            'period_end' => $item['period_end']->toDateString(),
            'adjusted' => false,
        ], $schedule);
    }
}
