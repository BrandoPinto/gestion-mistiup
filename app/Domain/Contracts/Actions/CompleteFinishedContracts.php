<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\ClientService;
use Carbon\CarbonImmutable;

/**
 * Marca como finalizados los contratos con duración cuya vigencia terminó y que ya no tienen ciclos por generar.
 * Sus cobros pendientes no cambian: se siguen cobrando normalmente.
 */
class CompleteFinishedContracts
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(CarbonImmutable $today): int
    {
        $completed = 0;

        ClientService::query()
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today->toDateString())
            ->whereNull('next_charge_date')
            ->chunkById(200, function ($contracts) use (&$completed) {
                foreach ($contracts as $contract) {
                    $contract->forceFill(['status' => ContractStatus::Completed])->save();
                    $this->logger->log('contract.completed', $contract);
                    $completed++;
                }
            });

        return $completed;
    }
}
