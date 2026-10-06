<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Services\ChargeSettlement;
use App\Domain\Contracts\Actions\SyncOneTimeContractStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula un pago: queda en el historial (marcado como anulado) y deja de contar para el saldo del cobro.
 */
class VoidPayment
{
    public function __construct(
        private readonly ChargeSettlement $settlement,
        private readonly SyncOneTimeContractStatus $syncContract,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Payment $payment, string $reason, int $userId): void
    {
        DB::transaction(function () use ($payment, $reason, $userId) {
            /** @var Charge $charge */
            $charge = Charge::query()->lockForUpdate()->findOrFail($payment->charge_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isVoided()) {
                throw ValidationException::withMessages(['reason' => 'El pago ya está anulado.']);
            }

            $payment->forceFill([
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
            ])->save();

            $this->settlement->recalculate($charge);
            $this->syncContract->handle($charge);

            $this->logger->log('payment.voided', $charge, [
                'payment_id' => $payment->id,
                'amount' => (string) $payment->amount->getAmount(),
                'currency' => $payment->currency->value,
                'reason' => $reason,
            ]);
        });
    }
}
