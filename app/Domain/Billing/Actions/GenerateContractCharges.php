<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Services\RecurrenceCalculator;
use App\Domain\Billing\Services\TaxBreakdown;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Settings\AppSettings;
use App\Models\Charge;
use App\Models\ClientService;
use App\Models\CompanyProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Genera los cobros de un contrato cuyo vencimiento cae dentro del horizonte (hoy + días de anticipación).
 *
 * Idempotente: el contrato se bloquea, se avanza next_cycle_number por cada cobro creado y la base de datos
 * impide dos cobros del mismo ciclo (UNIQUE client_service_id + cycle_number). Ejecutarla varias veces,
 * o con días de retraso, nunca duplica ni pierde ciclos.
 */
class GenerateContractCharges
{
    public function __construct(
        private readonly RecurrenceCalculator $calculator,
        private readonly ActivityLogger $logger,
        private readonly AppSettings $settings,
    ) {}

    /**
     * @return int Cantidad de cobros creados.
     */
    public function handle(ClientService $contract, CarbonImmutable $today): int
    {
        $horizon = $today->addDays($this->settings->chargeLeadDays())->toDateString();
        $limit = (int) config('billing.max_charges_per_run');

        return DB::transaction(function () use ($contract, $horizon, $limit) {
            /** @var ClientService $locked */
            $locked = ClientService::query()->lockForUpdate()->findOrFail($contract->id);
            $created = 0;

            while ($created < $limit && $this->hasDueCycle($locked, $horizon)) {
                $this->createCharge($locked);
                $this->advance($locked);
                $created++;
            }

            if ($created > 0) {
                $locked->save();
                $this->logger->log('contract.charges_generated', $locked, ['count' => $created]);
            }

            $contract->setRawAttributes($locked->getAttributes(), sync: true);

            return $created;
        });
    }

    private function hasDueCycle(ClientService $contract, string $horizon): bool
    {
        return $contract->status === ContractStatus::Active
            && $contract->next_charge_date !== null
            && $contract->next_charge_date->toDateString() <= $horizon;
    }

    private function createCharge(ClientService $contract): void
    {
        $recurrence = $contract->recurrence();
        $period = $recurrence ? $this->calculator->period($contract->start_date, $recurrence, $contract->next_cycle_number) : null;
        $taxRate = (string) CompanyProfile::current()->default_tax_rate;
        $tax = TaxBreakdown::fromTotal($contract->price, $taxRate)['tax'];

        $charge = (new Charge)->forceFill([
            'client_id' => $contract->client_id,
            'client_service_id' => $contract->id,
            'currency' => $contract->currency,
        ]);

        $charge->forceFill([
            'description' => $contract->name,
            'cycle_number' => $contract->next_cycle_number,
            'period_start' => $period['start'] ?? null,
            'period_end' => $period['end'] ?? null,
            'due_date' => $contract->next_charge_date,
            'amount' => $contract->price, // Copia: cambiar el precio del contrato no altera cobros ya generados.
            'amount_paid' => '0.00',
            'tax_rate' => $taxRate,
            'tax_amount' => $tax,
            'status' => 'pending',
        ])->save();
    }

    /** Pasa al siguiente ciclo, con su fecha natural anclada al inicio (la fecha ajustada a mano solo aplica al primero). */
    private function advance(ClientService $contract): void
    {
        $nextCycle = $contract->next_cycle_number + 1;
        $recurrence = $contract->recurrence();
        $total = $contract->totalCycles();
        $hasNext = $recurrence !== null && ($total === null || $nextCycle <= $total);

        $contract->forceFill([
            'next_cycle_number' => $nextCycle,
            'next_charge_date' => $hasNext ? $this->calculator->dueDate($contract->start_date, $recurrence, $nextCycle) : null,
        ]);
    }
}
