<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Billing\Services\ChargeSettlement;
use App\Domain\Contracts\Actions\SyncOneTimeContractStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Files\StoreAttachment;
use App\Domain\Shared\Money\MoneyPresenter;
use App\Models\Charge;
use App\Models\Payment;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un pago contra un cobro. Reglas (decididas en la Fase 0):
 * - un pago corresponde a un cobro;
 * - misma moneda que el cobro;
 * - no se puede pagar más que el saldo;
 * - no se paga un cobro cancelado o ya pagado.
 * Las reglas se verifican con el cobro BLOQUEADO para que dos pagos simultáneos no superen el saldo.
 */
class RegisterPayment
{
    public function __construct(
        private readonly ChargeSettlement $settlement,
        private readonly StoreAttachment $storeAttachment,
        private readonly SyncOneTimeContractStatus $syncContract,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(
        Charge $charge,
        string $amount,
        CarbonImmutable $paidOn,
        int $paymentMethodId,
        ?string $reference,
        ?string $notes,
        ?UploadedFile $receipt,
        int $userId,
    ): Payment {
        // El archivo se guarda antes de la transacción; si algo falla después se elimina.
        $storedReceipt = $receipt ? $this->storeAttachment->storeFile($receipt) : null;

        try {
            return DB::transaction(function () use ($charge, $amount, $paidOn, $paymentMethodId, $reference, $notes, $storedReceipt, $userId) {
                /** @var Charge $locked */
                $locked = Charge::query()->lockForUpdate()->findOrFail($charge->id);
                $money = Money::of($amount, $locked->currency->value);

                $this->assertCanReceive($locked, $money);

                $payment = (new Payment)->forceFill([
                    'client_id' => $locked->client_id,
                    'charge_id' => $locked->id,
                    'payment_method_id' => $paymentMethodId,
                    'paid_on' => $paidOn,
                    'currency' => $locked->currency,
                ]);
                $payment->forceFill([
                    'amount' => $money,
                    'reference' => $reference,
                    'notes' => $notes,
                    'created_by' => $userId,
                ])->save();

                if ($storedReceipt) {
                    $this->storeAttachment->attach($payment, $storedReceipt, $userId);
                }

                $this->settlement->recalculate($locked);
                $this->syncContract->handle($locked);

                $this->logger->log('payment.registered', $locked, [
                    'payment_id' => $payment->id,
                    'amount' => (string) $money->getAmount(),
                    'currency' => $locked->currency->value,
                ]);

                return $payment;
            });
        } catch (\Throwable $exception) {
            if ($storedReceipt) {
                $this->storeAttachment->discard($storedReceipt);
            }

            throw $exception;
        }
    }

    private function assertCanReceive(Charge $charge, Money $amount): void
    {
        if ($charge->status === ChargeStatus::Cancelled) {
            throw ValidationException::withMessages(['amount' => 'El cobro está cancelado: no puede recibir pagos.']);
        }

        if ($charge->status === ChargeStatus::Paid) {
            throw ValidationException::withMessages(['amount' => 'El cobro ya está pagado.']);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor que cero.']);
        }

        $balance = $charge->balance();

        if ($amount->isGreaterThan($balance)) {
            throw ValidationException::withMessages([
                'amount' => 'El monto supera el saldo pendiente ('.MoneyPresenter::format($balance).').',
            ]);
        }
    }
}
