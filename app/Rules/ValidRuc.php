<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RUC peruano: 11 dígitos, prefijo válido (10, 15, 16, 17, 20) y dígito verificador módulo 11 de SUNAT.
 */
class ValidRuc implements ValidationRule
{
    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    private const PREFIXES = ['10', '15', '16', '17', '20'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes((string) $value)) {
            $fail('El RUC no es válido. Verifica los 11 dígitos.');
        }
    }

    public static function passes(string $ruc): bool
    {
        if (! preg_match('/^\d{11}$/', $ruc) || ! in_array(substr($ruc, 0, 2), self::PREFIXES, true)) {
            return false;
        }

        $sum = 0;
        foreach (self::WEIGHTS as $index => $weight) {
            $sum += (int) $ruc[$index] * $weight;
        }

        $check = 11 - ($sum % 11);
        $check = match ($check) {
            10 => 0,
            11 => 1,
            default => $check,
        };

        return $check === (int) $ruc[10];
    }
}
