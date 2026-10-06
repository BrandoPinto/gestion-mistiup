<?php

namespace App\Domain\Quotes\Actions;

use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Quote;
use Illuminate\Validation\ValidationException;

/**
 * Transiciones manuales de estado: marcar como enviada, volver a borrador y cancelar.
 * Las de conversión (parcial / total) las hace la Fase 9.
 */
class ChangeQuoteStatus
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function markSent(Quote $quote): void
    {
        $this->assertEditable($quote);

        if ($quote->status === QuoteStatus::Sent) {
            return;
        }

        $quote->forceFill(['status' => QuoteStatus::Sent, 'sent_at' => $quote->sent_at ?? now()])->save();
        $this->logger->log('quote.sent', $quote);
    }

    public function backToDraft(Quote $quote): void
    {
        if ($quote->status !== QuoteStatus::Sent) {
            throw ValidationException::withMessages(['quote' => 'Solo una cotización enviada puede volver a borrador.']);
        }

        $quote->forceFill(['status' => QuoteStatus::Draft])->save();
        $this->logger->log('quote.back_to_draft', $quote);
    }

    public function cancel(Quote $quote, ?string $reason): void
    {
        $this->assertEditable($quote);

        $quote->forceFill(['status' => QuoteStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
        $this->logger->log('quote.cancelled', $quote, ['reason' => $reason]);
    }

    private function assertEditable(Quote $quote): void
    {
        if (! $quote->status->isEditable()) {
            throw ValidationException::withMessages(['quote' => 'La cotización ya no admite cambios de estado.']);
        }
    }
}
