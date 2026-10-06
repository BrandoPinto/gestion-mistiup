<?php

namespace App\Domain\Contracts\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Completed => 'Finalizado',
            self::Cancelled => 'Cancelado',
        };
    }
}
