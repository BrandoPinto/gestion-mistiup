<?php

namespace App\Domain\Shared\Money;

enum Currency: string
{
    case PEN = 'PEN';
    case USD = 'USD';

    public function symbol(): string
    {
        return match ($this) {
            self::PEN => 'S/',
            self::USD => '$',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PEN => 'Soles',
            self::USD => 'Dólares',
        };
    }
}
