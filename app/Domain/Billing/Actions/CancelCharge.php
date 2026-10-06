<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Charge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela un cobro sin pagos válidos. Un cobro con pagos no se cancela: primero se anulan sus pagos.
 */
class CancelCharge
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Charge $charge, ?string $reason): void
    {
        DB::transaction(function () use ($charge, $reason) {
            /** @var Charge $locked */
            $locked = Charge::query()->lockForUpdate()->findOrFail($charge->id);

            if (! $this->canCancel($locked)) {
                throw ValidationException::withMessages([
                    'charge' => 'Solo se pueden cancelar cobros sin pagos. Anula primero los pagos registrados.',
                ]);
            }

            $this->cancel($locked, $reason);
        });
    }

    public function canCancel(Charge $charge): bool
    {
        return $charge->status === ChargeStatus::Pending && ! $charge->payments()->valid()->exists();
    }

    /** Requiere el cobro bloqueado y verificado con canCancel(). Lo usa también la cancelación de contratos. */
    public function cancel(Charge $charge, ?string $reason): void
    {
        $charge->forceFill([
            'status' => ChargeStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ])->save();

        $this->logger->log('charge.cancelled', $charge, ['reason' => $reason]);
    }
}
