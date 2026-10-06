<?php

namespace App\Domain\Reminders\Services;

use App\Domain\Shared\Money\MoneyPresenter;
use App\Models\Charge;
use App\Models\ClientService;
use Carbon\CarbonImmutable;

/**
 * Textos de los avisos. Se guardan ya armados en la notificación (no se recalculan al leerla).
 */
class ReminderMessages
{
    /**
     * @return array{kind: string, tone: string, title: string, body: string, url: string, target_date: string}
     */
    public function chargeDue(Charge $charge, CarbonImmutable $today): array
    {
        $daysLeft = (int) $today->diffInDays($charge->due_date);
        $when = match ($daysLeft) {
            0 => 'Vence hoy',
            1 => 'Vence mañana',
            default => "Vence en {$daysLeft} días",
        };

        return [
            'kind' => 'charge_due',
            'tone' => 'warning',
            'title' => "{$charge->description} · {$charge->client->name}",
            'body' => "{$when} ({$charge->due_date->format('d/m/Y')}) · ".MoneyPresenter::format($charge->balance()),
            'url' => route('charges.show', $charge, absolute: false),
            'target_date' => $charge->due_date->toDateString(),
        ];
    }

    /**
     * @return array{kind: string, tone: string, title: string, body: string, url: string, target_date: string}
     */
    public function chargeOverdue(Charge $charge, CarbonImmutable $today): array
    {
        $daysLate = (int) $charge->due_date->diffInDays($today);
        $since = $daysLate === 1 ? 'Venció ayer' : "Venció hace {$daysLate} días";

        return [
            'kind' => 'charge_overdue',
            'tone' => 'danger',
            'title' => "{$charge->description} · {$charge->client->name}",
            'body' => "{$since} ({$charge->due_date->format('d/m/Y')}) · saldo ".MoneyPresenter::format($charge->balance()),
            'url' => route('charges.show', $charge, absolute: false),
            'target_date' => $charge->due_date->toDateString(),
        ];
    }

    /**
     * @return array{kind: string, tone: string, title: string, body: string, url: string, target_date: string}
     */
    public function contractEnd(ClientService $contract, CarbonImmutable $today): array
    {
        $daysLeft = (int) $today->diffInDays($contract->end_date);
        $when = $daysLeft === 0 ? 'Termina hoy' : "Termina en {$daysLeft} días";

        return [
            'kind' => 'contract_end',
            'tone' => 'brand',
            'title' => "{$contract->name} · {$contract->client->name}",
            'body' => "{$when} ({$contract->end_date->format('d/m/Y')}). Revisa si se renueva.",
            'url' => route('contracts.show', $contract, absolute: false),
            'target_date' => $contract->end_date->toDateString(),
        ];
    }
}
