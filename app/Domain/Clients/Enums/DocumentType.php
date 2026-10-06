<?php

namespace App\Domain\Clients\Enums;

enum DocumentType: string
{
    case Ruc = 'RUC';
    case Dni = 'DNI';
    case Ce = 'CE';
    case None = 'NONE';

    public function label(): string
    {
        return match ($this) {
            self::Ruc => 'RUC',
            self::Dni => 'DNI',
            self::Ce => 'Carné de extranjería',
            self::None => 'Sin documento',
        };
    }
}
