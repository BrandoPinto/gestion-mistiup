<?php

namespace App\Console\Commands;

use App\Domain\Billing\Actions\GenerateContractCharges;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\Dates\BusinessClock;
use App\Domain\Shared\Settings\AppSettings;
use App\Models\ClientService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('billing:generate-charges')]
#[Description('Genera los cobros de contratos que vencen dentro del horizonte configurado (idempotente).')]
class GenerateChargesCommand extends Command
{
    public function handle(GenerateContractCharges $generate, BusinessClock $clock, AppSettings $settings): int
    {
        $today = $clock->today();
        $horizon = $today->addDays($settings->chargeLeadDays())->toDateString();
        $created = 0;
        $failed = 0;

        ClientService::query()
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('next_charge_date')
            ->where('next_charge_date', '<=', $horizon)
            ->chunkById(100, function ($contracts) use ($generate, $today, &$created, &$failed) {
                foreach ($contracts as $contract) {
                    // Un contrato con problemas no debe impedir generar los demás.
                    try {
                        $created += $generate->handle($contract, $today);
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                        $this->error("Contrato #{$contract->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info("Cobros generados: {$created}.".($failed ? " Contratos con error: {$failed}." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
