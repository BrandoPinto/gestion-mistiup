<?php

namespace App\Console\Commands;

use App\Domain\Reminders\Actions\DispatchReminders;
use App\Domain\Shared\Dates\BusinessClock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:dispatch')]
#[Description('Emite los recordatorios internos de vencimientos (idempotente: puede ejecutarse varias veces al día).')]
class DispatchRemindersCommand extends Command
{
    public function handle(DispatchReminders $dispatch, BusinessClock $clock): int
    {
        $sent = $dispatch->handle($clock->today());

        $this->info("Avisos emitidos · por vencer: {$sent['charge_due']} · vencidos: {$sent['charge_overdue']} · fin de contrato: {$sent['contract_end']}.");

        return self::SUCCESS;
    }
}
