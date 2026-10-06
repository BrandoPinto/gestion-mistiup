<?php

namespace App\Domain\Billing\Enums;

enum BillingType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Pago único',
            self::Recurring => 'Recurrente',
        };
    }
}
