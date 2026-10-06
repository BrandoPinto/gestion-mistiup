<?php

namespace App\Http\Resources;

use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Billing\Services\TaxBreakdown;
use App\Domain\Shared\Money\MoneyPresenter;
use App\Models\Charge;
use Carbon\CarbonImmutable;

/**
 * Formas de un cobro hacia el frontend. Deben coincidir con features/charges/types.ts.
 * "overdue" se calcula aquí con la fecha de hoy (Lima); no existe en la base de datos.
 */
final class ChargePresenter
{
    /**
     * Requiere la relación client cargada (y contract si se quiere mostrar).
     *
     * @return array<string, mixed>
     */
    public static function listItem(Charge $charge, CarbonImmutable $today): array
    {
        $overdue = $charge->isOverdue($today);

        return [
            'id' => $charge->id,
            'client' => ['id' => $charge->client->id, 'name' => $charge->client->name],
            'contract' => $charge->relationLoaded('contract') && $charge->contract
                ? ['id' => $charge->contract->id, 'name' => $charge->contract->name]
                : null,
            'description' => $charge->description,
            'cycle_number' => $charge->cycle_number,
            'period_start' => $charge->period_start?->toDateString(),
            'period_end' => $charge->period_end?->toDateString(),
            'due_date' => $charge->due_date->toDateString(),
            'amount' => MoneyPresenter::toArray($charge->amount),
            'amount_paid' => MoneyPresenter::toArray($charge->amount_paid),
            'balance' => MoneyPresenter::toArray($charge->balance()),
            'status' => $charge->status->value,
            'display_status' => $overdue ? 'overdue' : $charge->status->value,
            'days_overdue' => $overdue ? (int) $charge->due_date->diffInDays($today) : 0,
            'paid_on' => $charge->paid_on?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Charge $charge, CarbonImmutable $today, bool $hasValidPayments): array
    {
        $breakdown = TaxBreakdown::fromTotal($charge->amount, (string) $charge->tax_rate);

        return [
            ...self::listItem($charge, $today),
            'tax_rate' => (string) $charge->tax_rate,
            'tax_amount' => MoneyPresenter::toArray($breakdown['tax']),
            'base_amount' => MoneyPresenter::toArray($breakdown['base']),
            'notes' => $charge->notes,
            'cancelled_at' => $charge->cancelled_at?->toIso8601String(),
            'cancel_reason' => $charge->cancel_reason,
            'created_at' => $charge->created_at->toIso8601String(),
            'can' => [
                'pay' => $charge->status->isOpen(),
                'adjust' => $charge->status === ChargeStatus::Pending && ! $hasValidPayments,
                'cancel' => $charge->status === ChargeStatus::Pending && ! $hasValidPayments,
            ],
        ];
    }
}
