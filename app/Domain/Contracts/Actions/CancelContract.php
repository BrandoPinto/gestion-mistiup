<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Billing\Actions\CancelCharge;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Charge;
use App\Models\ClientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela un contrato: deja de generar cobros. No se borra: queda en el historial del cliente.
 *
 * Con $cancelPendingCharges (opción por defecto en la interfaz) también cancela sus cobros pendientes
 * SIN pagos. Los cobros con pagos parciales nunca se cancelan automáticamente: se devuelven para avisar.
 */
class CancelContract
{
    public function __construct(
        private readonly CancelCharge $cancelCharge,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @return array{cancelled_charges: int, kept_partial: int}
     */
    public function handle(ClientService $contract, ?string $reason, bool $cancelPendingCharges): array
    {
        if ($contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['contract' => 'Solo se pueden cancelar contratos activos.']);
        }

        return DB::transaction(function () use ($contract, $reason, $cancelPendingCharges) {
            $contract->forceFill([
                'status' => ContractStatus::Cancelled,
                'cancelled_at' => now(), // Timestamp en UTC (zona de la app).
                'cancel_reason' => $reason,
                'next_charge_date' => null,
            ])->save();

            $cancelled = 0;
            $openCharges = Charge::query()->where('client_service_id', $contract->id)->open()->lockForUpdate()->get();

            if ($cancelPendingCharges) {
                foreach ($openCharges as $charge) {
                    if ($this->cancelCharge->canCancel($charge)) {
                        $this->cancelCharge->cancel($charge, 'Contrato cancelado'.($reason ? ": {$reason}" : ''));
                        $cancelled++;
                    }
                }
            }

            $keptPartial = $openCharges->count() - $cancelled;

            $this->logger->log('contract.cancelled', $contract, [
                'reason' => $reason,
                'cancelled_charges' => $cancelled,
                'kept_open_charges' => $keptPartial,
            ]);

            return ['cancelled_charges' => $cancelled, 'kept_partial' => $keptPartial];
        });
    }
}
