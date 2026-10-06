<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\ChargeStatus;
use App\Models\Charge;
use Brick\Money\Money;

/**
 * ÚNICO punto que escribe amount_paid, status y paid_on de un cobro. La fuente de verdad son los pagos:
 * amount_paid = SUM(pagos no anulados). Debe llamarse dentro de la misma transacción que crea o anula
 * el pago y con el cobro bloqueado (lockForUpdate).
 */
class ChargeSettlement
{
    public function recalculate(Charge $charge): Charge
    {
        $payments = $charge->payments()->valid()->get(['amount', 'currency', 'paid_on']);

        $paid = Money::zero($charge->currency->value);
        foreach ($payments as $payment) {
            $paid = $paid->plus($payment->amount);
        }

        $charge->amount_paid = $paid;

        if ($charge->status !== ChargeStatus::Cancelled) {
            $charge->status = $this->statusFor($charge->amount, $paid);
        }

        $charge->paid_on = $charge->status === ChargeStatus::Paid
            ? $payments->max(fn ($payment) => $payment->paid_on->toDateString())
            : null;

        $charge->save();

        return $charge;
    }

    private function statusFor(Money $amount, Money $paid): ChargeStatus
    {
        return match (true) {
            $paid->isZero() => ChargeStatus::Pending,
            $paid->isGreaterThanOrEqualTo($amount) => ChargeStatus::Paid,
            default => ChargeStatus::Partial,
        };
    }
}
