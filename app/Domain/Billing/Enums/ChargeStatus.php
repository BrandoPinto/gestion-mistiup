<?php

namespace App\Domain\Billing\Enums;

/**
 * Estado persistido del cobro. "Vencido" NO es un estado guardado: se calcula como
 * (pending|partial) y due_date < hoy, porque depende del día en que se consulta.
 */
enum ChargeStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Partial;
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Pending->value, self::Partial->value];
    }
}
