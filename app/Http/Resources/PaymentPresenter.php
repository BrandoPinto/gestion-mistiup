<?php

namespace App\Http\Resources;

use App\Domain\Shared\Money\MoneyPresenter;
use App\Models\Attachment;
use App\Models\Payment;

/**
 * Debe coincidir con features/payments/types.ts. Requiere method, creator y attachments cargados
 * (y charge/client cuando se listan fuera de un cobro).
 */
final class PaymentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function item(Payment $payment): array
    {
        /** @var Attachment|null $receipt */
        $receipt = $payment->attachments->first();

        return [
            'id' => $payment->id,
            'paid_on' => $payment->paid_on->toDateString(),
            'amount' => MoneyPresenter::toArray($payment->amount),
            'method' => $payment->method->name,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'created_by' => $payment->creator?->name,
            'created_at' => $payment->created_at->toIso8601String(),
            'voided_at' => $payment->voided_at?->toIso8601String(),
            'void_reason' => $payment->void_reason,
            'receipt' => $receipt ? [
                'id' => $receipt->id,
                'name' => $receipt->original_name,
                'mime_type' => $receipt->mime_type,
                'url' => route('attachments.show', $receipt),
            ] : null,
            'charge' => $payment->relationLoaded('charge') ? ['id' => $payment->charge->id, 'description' => $payment->charge->description] : null,
            'client' => $payment->relationLoaded('client') ? ['id' => $payment->client->id, 'name' => $payment->client->name] : null,
        ];
    }
}
