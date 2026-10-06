<?php

namespace App\Domain\Reminders\Services;

use App\Models\ReminderRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Registro de avisos ya emitidos. claim() inserta la fila y devuelve true SOLO si no existía:
 * el UNIQUE (regla, entidad, fecha objetivo) hace atómica la decisión aunque dos procesos corran a la vez.
 */
class ReminderLedger
{
    public function claim(ReminderRule $rule, Model $remindable, CarbonImmutable $targetDate, bool $notified = true): bool
    {
        return DB::table('reminder_dispatches')->insertOrIgnore([
            'reminder_rule_id' => $rule->id,
            'remindable_type' => $remindable->getMorphClass(),
            'remindable_id' => $remindable->getKey(),
            'target_date' => $targetDate->toDateString(),
            'notified' => $notified,
            'created_at' => now(),
        ]) === 1;
    }
}
