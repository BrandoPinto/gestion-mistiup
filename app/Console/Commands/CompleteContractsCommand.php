<?php

namespace App\Console\Commands;

use App\Domain\Contracts\Actions\CompleteFinishedContracts;
use App\Domain\Shared\Dates\BusinessClock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:complete-finished')]
#[Description('Marca como finalizados los contratos cuya vigencia terminó.')]
class CompleteContractsCommand extends Command
{
    public function handle(CompleteFinishedContracts $complete, BusinessClock $clock): int
    {
        $count = $complete->handle($clock->today());

        $this->info("Contratos finalizados: {$count}.");

        return self::SUCCESS;
    }
}
