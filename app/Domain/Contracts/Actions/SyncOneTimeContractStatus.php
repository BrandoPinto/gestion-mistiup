<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\Charge;
use App\Models\ClientService;

/**
 * Un contrato de pago único se da por finalizado cuando su cobro queda pagado,
 * y vuelve a activo si ese pago se anula. Los recurrentes no se tocan aquí.
 */
class SyncOneTimeContractStatus
{
    public function handle(Charge $charge): void
    {
        if ($charge->client_service_id === null) {
            return;
        }

        $contract = ClientService::query()->find($charge->client_service_id);

        if (! $contract || $contract->billing_type !== BillingType::OneTime || $contract->status === ContractStatus::Cancelled) {
            return;
        }

        $target = $charge->status === ChargeStatus::Paid ? ContractStatus::Completed : ContractStatus::Active;

        if ($contract->status !== $target) {
            $contract->forceFill(['status' => $target])->save();
        }
    }
}
