<?php

namespace App\Http\Resources;

use App\Domain\Shared\Money\MoneyPresenter;
use App\Models\Quote;
use App\Models\QuoteItem;
use Carbon\CarbonImmutable;

/**
 * Formas de una cotización hacia el frontend. Deben coincidir con resources/js/features/quotes/types.ts.
 * document() es el contrato único que consumen la vista previa, el enlace público y el PDF.
 */
final class QuotePresenter
{
    /**
     * Requiere client cargado.
     *
     * @return array<string, mixed>
     */
    public static function listItem(Quote $quote, CarbonImmutable $today): array
    {
        return [
            'id' => $quote->id,
            'number' => $quote->number,
            'client' => ['id' => $quote->client->id, 'name' => $quote->client->name],
            'issue_date' => $quote->issue_date->toDateString(),
            'valid_until' => $quote->valid_until->toDateString(),
            'total' => MoneyPresenter::toArray($quote->total),
            'status' => $quote->status->value,
            'display_status' => $quote->displayStatus($today),
        ];
    }

    /**
     * Documento listo para mostrar (sin datos internos). Requiere client e items cargados.
     *
     * @return array<string, mixed>
     */
    public static function document(Quote $quote): array
    {
        $client = $quote->client;

        return [
            'number' => $quote->number,
            'issue_date' => $quote->issue_date->toDateString(),
            'valid_until' => $quote->valid_until->toDateString(),
            'delivery_date' => $quote->delivery_date?->toDateString(),
            'currency' => $quote->currency->value,
            'tax_rate' => (string) $quote->tax_rate,
            'client' => [
                'name' => $client->name,
                'document' => $client->displayDocument(),
                'address' => $client->address,
                'email' => $client->email,
                'phone' => $client->whatsapp ?? $client->phone,
                'contact_name' => $client->contact_name,
            ],
            'items' => $quote->items->map(fn (QuoteItem $item) => [
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value,
                'line_discount' => $item->line_discount,
                'line_total' => $item->line_total,
            ])->values()->all(),
            'global_discount_type' => $quote->global_discount_type,
            'global_discount_value' => $quote->global_discount_value,
            'subtotal' => (string) $quote->subtotal->getAmount(),
            'global_discount' => (string) $quote->global_discount_amount->getAmount(),
            'discount_total' => (string) $quote->discount_total->getAmount(),
            'total' => (string) $quote->total->getAmount(),
            'tax_base' => (string) $quote->tax_base->getAmount(),
            'tax_amount' => (string) $quote->tax_amount->getAmount(),
            'intro' => $quote->intro,
            'observations' => $quote->observations,
            'terms' => $quote->terms,
        ];
    }

    /**
     * Valores del editor. Requiere items cargados.
     *
     * @return array<string, mixed>
     */
    public static function formValues(Quote $quote): array
    {
        return [
            'client_id' => $quote->client_id,
            'currency' => $quote->currency->value,
            'issue_date' => $quote->issue_date->toDateString(),
            'valid_until' => $quote->valid_until->toDateString(),
            'delivery_date' => $quote->delivery_date?->toDateString() ?? '',
            'tax_rate' => self::trimDecimal((string) $quote->tax_rate),
            'global_discount_type' => $quote->global_discount_type ?? '',
            'global_discount_value' => $quote->global_discount_value ?? '',
            'intro' => $quote->intro ?? '',
            'observations' => $quote->observations ?? '',
            'terms' => $quote->terms ?? '',
            'internal_notes' => $quote->internal_notes ?? '',
            'items' => $quote->items->map(fn (QuoteItem $item) => [
                'key' => 'item-'.$item->id,
                'service_id' => $item->service_id,
                'name' => $item->name,
                'description' => $item->description ?? '',
                'quantity' => self::trimDecimal($item->quantity),
                'unit_price' => $item->unit_price,
                'discount_type' => $item->discount_type ?? '',
                'discount_value' => $item->discount_value ?? '',
            ])->values()->all(),
        ];
    }

    /** "1.00" → "1", "1.50" → "1.5": más natural en el campo cantidad. */
    private static function trimDecimal(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
