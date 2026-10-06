<?php

namespace App\Domain\Reminders\Actions;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Reminders\Enums\ReminderEvent;
use App\Domain\Reminders\Notifications\BillingReminder;
use App\Domain\Reminders\Services\ReminderLedger;
use App\Domain\Reminders\Services\ReminderMessages;
use App\Models\Charge;
use App\Models\ClientService;
use App\Models\ReminderRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Emite los recordatorios internos que correspondan HOY. Seguro de ejecutar muchas veces al día:
 *
 * - Ventanas, no días exactos: si el cron falló ayer, hoy se emite igual (mientras siga dentro de la ventana).
 * - Un aviso por regla + entidad + fecha objetivo (ReminderLedger). Si se reprograma el vencimiento, se vuelve a avisar.
 * - Si una entidad cae en varias ventanas a la vez (p. ej. 30 y 7 días antes), se envía solo el aviso más
 *   cercano y las demás ventanas se marcan como cubiertas: nunca llegan dos avisos del mismo cobro el mismo día.
 */
class DispatchReminders
{
    /** Cobros vencidos más antiguos que esto (además de la regla) no generan aviso nuevo: evita ráfagas al activar el sistema. */
    private const OVERDUE_LOOKBACK_DAYS = 60;

    public function __construct(
        private readonly ReminderLedger $ledger,
        private readonly ReminderMessages $messages,
    ) {}

    /**
     * @return array{charge_due: int, charge_overdue: int, contract_end: int}
     */
    public function handle(CarbonImmutable $today): array
    {
        $rules = ReminderRule::active()->get()->groupBy(fn (ReminderRule $rule) => $rule->event->value);
        $recipients = User::active()->get();
        $sent = ['charge_due' => 0, 'charge_overdue' => 0, 'contract_end' => 0];

        if ($recipients->isEmpty()) {
            return $sent;
        }

        if ($due = $rules->get(ReminderEvent::ChargeDue->value)) {
            $sent['charge_due'] = $this->chargesDue($due->sortBy('days')->values(), $today, $recipients);
        }

        if ($overdue = $rules->get(ReminderEvent::ChargeOverdue->value)) {
            $sent['charge_overdue'] = $this->chargesOverdue($overdue->sortByDesc('days')->values(), $today, $recipients);
        }

        if ($end = $rules->get(ReminderEvent::ContractEnd->value)) {
            $sent['contract_end'] = $this->contractsEnding($end->sortBy('days')->values(), $today, $recipients);
        }

        return $sent;
    }

    /**
     * @param  Collection<int, ReminderRule>  $rules  Ordenadas de menor a mayor anticipación.
     * @param  Collection<int, User>  $recipients
     */
    private function chargesDue(Collection $rules, CarbonImmutable $today, Collection $recipients): int
    {
        $sent = 0;

        Charge::query()
            ->open()
            ->whereBetween('due_date', [$today->toDateString(), $today->addDays($rules->max('days'))->toDateString()])
            ->with('client:id,name,deleted_at')
            ->chunkById(200, function ($charges) use ($rules, $today, $recipients, &$sent) {
                foreach ($charges as $charge) {
                    $daysLeft = (int) $today->diffInDays($charge->due_date);
                    $applicable = $rules->filter(fn (ReminderRule $rule) => $rule->days >= $daysLeft)->values();

                    if ($this->claimClosest($applicable, $charge, $charge->due_date)) {
                        Notification::send($recipients, new BillingReminder($this->messages->chargeDue($charge, $today)));
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * @param  Collection<int, ReminderRule>  $rules  Ordenadas de mayor a menor atraso (la más cercana primero).
     * @param  Collection<int, User>  $recipients
     */
    private function chargesOverdue(Collection $rules, CarbonImmutable $today, Collection $recipients): int
    {
        $sent = 0;
        $minDays = $rules->min('days');
        $oldest = $today->subDays($rules->max('days') + self::OVERDUE_LOOKBACK_DAYS);

        Charge::query()
            ->open()
            ->whereBetween('due_date', [$oldest->toDateString(), $today->subDays($minDays)->toDateString()])
            ->with('client:id,name,deleted_at')
            ->chunkById(200, function ($charges) use ($rules, $today, $recipients, &$sent) {
                foreach ($charges as $charge) {
                    $daysLate = (int) $charge->due_date->diffInDays($today);
                    $applicable = $rules->filter(fn (ReminderRule $rule) => $rule->days <= $daysLate)->values();

                    if ($this->claimClosest($applicable, $charge, $charge->due_date)) {
                        Notification::send($recipients, new BillingReminder($this->messages->chargeOverdue($charge, $today)));
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * @param  Collection<int, ReminderRule>  $rules
     * @param  Collection<int, User>  $recipients
     */
    private function contractsEnding(Collection $rules, CarbonImmutable $today, Collection $recipients): int
    {
        $sent = 0;

        ClientService::query()
            ->where('status', ContractStatus::Active->value)
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays($rules->max('days'))->toDateString()])
            ->with('client:id,name,deleted_at')
            ->chunkById(200, function ($contracts) use ($rules, $today, $recipients, &$sent) {
                foreach ($contracts as $contract) {
                    $daysLeft = (int) $today->diffInDays($contract->end_date);
                    $applicable = $rules->filter(fn (ReminderRule $rule) => $rule->days >= $daysLeft)->values();

                    if ($this->claimClosest($applicable, $contract, $contract->end_date)) {
                        Notification::send($recipients, new BillingReminder($this->messages->contractEnd($contract, $today)));
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Reclama la regla más cercana (primera de la lista). Si es nueva, marca el resto como cubiertas.
     *
     * @param  Collection<int, ReminderRule>  $applicable
     */
    private function claimClosest(Collection $applicable, Model $remindable, CarbonImmutable $targetDate): bool
    {
        $closest = $applicable->first();

        if ($closest === null || ! $this->ledger->claim($closest, $remindable, $targetDate)) {
            return false;
        }

        foreach ($applicable->slice(1) as $covered) {
            $this->ledger->claim($covered, $remindable, $targetDate, notified: false);
        }

        return true;
    }
}
