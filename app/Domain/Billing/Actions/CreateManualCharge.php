<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Services\TaxBreakdown;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Money\Currency;
use App\Models\Charge;
use App\Models\CompanyProfile;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cobro suelto, sin contrato (un trabajo puntual, un reembolso de gastos…).
 */
class CreateManualCharge
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(int $clientId, string $description, Currency $currency, string $amount, CarbonImmutable $dueDate, ?string $notes, int $userId): Charge
    {
        return DB::transaction(function () use ($clientId, $description, $currency, $amount, $dueDate, $notes, $userId) {
            $money = Money::of($amount, $currency->value);
            $taxRate = (string) CompanyProfile::current()->default_tax_rate;

            $charge = (new Charge)->forceFill(['client_id' => $clientId, 'currency' => $currency]);
            $charge->forceFill([
                'description' => $description,
                'due_date' => $dueDate,
                'amount' => $money,
                'amount_paid' => '0.00',
                'tax_rate' => $taxRate,
                'tax_amount' => TaxBreakdown::fromTotal($money, $taxRate)['tax'],
                'status' => 'pending',
                'notes' => $notes,
                'created_by' => $userId,
            ])->save();

            $this->logger->log('charge.created', $charge, ['amount' => (string) $money->getAmount(), 'currency' => $currency->value]);

            return $charge;
        });
    }
}
