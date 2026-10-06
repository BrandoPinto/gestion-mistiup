<?php

namespace App\Http\Resources;

use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Shared\Money\MoneyPresenter;
use App\Models\ClientService;

/**
 * Formas de un servicio contratado hacia el frontend. Deben coincidir con features/contracts/types.ts.
 */
final class ContractPresenter
{
    /**
     * Requiere la relación client cargada.
     *
     * @return array<string, mixed>
     */
    public static function listItem(ClientService $contract): array
    {
        return [
            'id' => $contract->id,
            'client' => ['id' => $contract->client->id, 'name' => $contract->client->name],
            'name' => $contract->name,
            'billing_type' => $contract->billing_type->value,
            'billing_label' => $contract->billingLabel(),
            'price' => MoneyPresenter::toArray($contract->price),
            'start_date' => $contract->start_date->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
            'next_charge_date' => $contract->next_charge_date?->toDateString(),
            'next_cycle_number' => $contract->next_cycle_number,
            'total_cycles' => $contract->totalCycles(),
            'status' => $contract->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(ClientService $contract): array
    {
        return [
            ...self::listItem($contract),
            'description' => $contract->description,
            'notes' => $contract->notes,
            'term_months' => $contract->term_months,
            'service' => $contract->service ? ['id' => $contract->service->id, 'name' => $contract->service->name] : null,
            'origin_quote' => $contract->quoteItem?->quote ? ['id' => $contract->quoteItem->quote->id, 'number' => $contract->quoteItem->quote->number] : null,
            'cancelled_at' => $contract->cancelled_at?->toIso8601String(),
            'cancel_reason' => $contract->cancel_reason,
            'created_at' => $contract->created_at->toIso8601String(),
        ];
    }

    /**
     * Valores del formulario de edición (strings, como los maneja el formulario).
     *
     * @return array<string, mixed>
     */
    public static function formValues(ClientService $contract): array
    {
        $termMonths = $contract->term_months;
        $termInYears = $termMonths !== null && $termMonths % 12 === 0;

        return [
            'client_id' => $contract->client_id,
            'service_id' => $contract->service_id,
            'name' => $contract->name,
            'description' => $contract->description ?? '',
            'currency' => $contract->currency->value,
            'price' => (string) $contract->price->getAmount(),
            'billing_type' => $contract->billing_type->value,
            'interval_unit' => $contract->interval_unit?->value ?? '',
            'interval_count' => $contract->interval_count ? (string) $contract->interval_count : '',
            'start_date' => $contract->start_date->toDateString(),
            'has_term' => $termMonths !== null,
            'term_unit' => $termMonths === null ? IntervalUnit::Year->value : ($termInYears ? IntervalUnit::Year->value : IntervalUnit::Month->value),
            'term_count' => $termMonths === null ? '' : (string) ($termInYears ? intdiv($termMonths, 12) : $termMonths),
            'first_cycle' => (string) $contract->next_cycle_number,
            'first_charge_date' => $contract->next_charge_date?->toDateString() ?? '',
            'notes' => $contract->notes ?? '',
        ];
    }
}
