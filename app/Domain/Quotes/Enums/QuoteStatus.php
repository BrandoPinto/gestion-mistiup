<?php

namespace App\Domain\Quotes\Enums;

/**
 * Estado persistido. "Vista" (Fase 8) y "Vencida" se calculan: no se guardan.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyConverted = 'partially_converted';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    /** Solo borradores y enviadas se editan; una vez convertida o cancelada, el documento queda fijo. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }

    /** Se pueden convertir conceptos mientras no esté cancelada ni convertida por completo. */
    public function isConvertible(): bool
    {
        return $this === self::Draft || $this === self::Sent || $this === self::PartiallyConverted;
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Draft->value, self::Sent->value];
    }
}
