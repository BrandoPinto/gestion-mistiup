<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Billing\Services\TaxBreakdown;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Charge;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambia el importe de un cobro (p. ej. un descuento puntual). Solo mientras NO tenga pagos válidos;
 * con pagos, el camino es cancelarlo y crear otro. El motivo es obligatorio y queda en el historial.
 */
class AdjustChargeAmount
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Charge $charge, string $amount, string $reason): void
    {
        DB::transaction(function () use ($charge, $amount, $reason) {
            /** @var Charge $locked */
            $locked = Charge::query()->lockForUpdate()->findOrFail($charge->id);

            if ($locked->status !== ChargeStatus::Pending || $locked->payments()->valid()->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'Solo se puede cambiar el importe de un cobro pendiente y sin pagos. Si ya tiene pagos, cancélalo y crea otro.',
                ]);
            }

            $money = Money::of($amount, $locked->currency->value);

            if (! $money->isPositive()) {
                throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
            }

            $previous = (string) $locked->amount->getAmount();

            $locked->forceFill([
                'amount' => $money,
                'tax_amount' => TaxBreakdown::fromTotal($money, (string) $locked->tax_rate)['tax'],
            ])->save();

            $this->logger->log('charge.amount_adjusted', $locked, [
                'changes' => ['amount' => ['from' => $previous, 'to' => (string) $money->getAmount()]],
                'reason' => $reason,
            ]);
        });
    }
}
