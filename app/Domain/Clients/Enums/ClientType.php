<?php

namespace App\Domain\Clients\Enums;

enum ClientType: string
{
    case Company = 'company';
    case Person = 'person';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Empresa',
            self::Person => 'Persona',
        };
    }
}
