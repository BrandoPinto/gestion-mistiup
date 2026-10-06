<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Billing\Actions\GenerateContractCharges;
use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Services\RecurrenceCalculator;
use App\Domain\Contracts\ContractTerms;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Audit\ModelChanges;
use App\Domain\Shared\Dates\BusinessClock;
use App\Models\ClientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea o actualiza un servicio contratado y genera los cobros que ya entran en el horizonte.
 *
 * - El cambio de precio solo afecta a cobros que aún no se generaron: los cobros guardan su propio importe.
 * - Si el contrato ya tiene cobros, su calendario (moneda, modalidad, inicio, duración, ciclo) no se modifica:
 *   esos valores se ignoran y solo se actualizan nombre, descripción, catálogo, precio y notas.
 */
class SaveContract
{
    public function __construct(
        private readonly RecurrenceCalculator $calculator,
        private readonly GenerateContractCharges $generateCharges,
        private readonly BusinessClock $clock,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(ContractTerms $terms, ?ClientService $contract = null): ClientService
    {
        if ($contract !== null && $contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['contract' => 'Solo se pueden editar contratos activos.']);
        }

        $contract = DB::transaction(function () use ($terms, $contract) {
            $isNew = $contract === null;
            // Un contrato no cambia de cliente al editarse.
            $contract ??= (new ClientService)->forceFill([
                'status' => ContractStatus::Active,
                'client_id' => $terms->clientId,
                'quote_item_id' => $terms->quoteItemId,
            ]);

            if ($isNew || ! $contract->scheduleLocked()) {
                $this->fillSchedule($contract, $terms);
            }

            $contract->forceFill([
                'service_id' => $terms->serviceId,
                'name' => $terms->name,
                'description' => $terms->description,
                'price' => $terms->price,
                'notes' => $terms->notes,
            ]);

            $changes = $isNew ? [] : ModelChanges::pending($contract);
            $contract->save();

            if ($isNew) {
                $this->logger->log('contract.created', $contract, ['client_id' => $contract->client_id]);
            } elseif ($changes !== []) {
                $this->logger->log('contract.updated', $contract, ['changes' => $changes]);
            }

            return $contract;
        });

        $this->generateCharges->handle($contract, $this->clock->today());

        return $contract;
    }

    private function fillSchedule(ClientService $contract, ContractTerms $terms): void
    {
        // La moneda antes que el precio: MoneyCast interpreta el importe en esa moneda.
        $contract->forceFill([
            'currency' => $terms->currency,
            'billing_type' => $terms->recurrence ? BillingType::Recurring : BillingType::OneTime,
            'interval_unit' => $terms->recurrence?->unit,
            'interval_count' => $terms->recurrence?->count,
            'start_date' => $terms->startDate,
            'term_months' => $terms->termMonths,
            'end_date' => $terms->termMonths ? $this->calculator->endDate($terms->startDate, $terms->termMonths) : null,
            'next_cycle_number' => $terms->firstCycle,
            'next_charge_date' => $terms->firstChargeDate,
        ]);
    }
}
