<?php

namespace App\Domain\Shared\Money;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Convierte una columna DECIMAL(12,2) en Brick\Money usando la moneda de otra columna de la misma fila.
 *
 * Uso: 'amount' => MoneyCast::class.':currency'
 *
 * Nunca acepta float: los importes entran como string decimal ("350.00") o como Money.
 *
 * @implements CastsAttributes<Money, Money|string>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly string $currencyColumn = 'currency') {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::of((string) $value, $this->currencyCode($attributes));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException("El importe [{$key}] no puede asignarse como float.");
        }

        if ($value instanceof Money) {
            $expected = $this->currencyCode($attributes);

            if ($value->getCurrency()->getCurrencyCode() !== $expected) {
                throw new InvalidArgumentException("El importe [{$key}] está en otra moneda distinta de {$expected}.");
            }

            return (string) $value->getAmount();
        }

        return (string) Money::of((string) $value, $this->currencyCode($attributes), roundingMode: RoundingMode::Unnecessary)->getAmount();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function currencyCode(array $attributes): string
    {
        $currency = $attributes[$this->currencyColumn] ?? null;

        if ($currency === null) {
            throw new InvalidArgumentException("Falta la moneda [{$this->currencyColumn}] antes de asignar el importe.");
        }

        return $currency instanceof Currency ? $currency->value : (string) $currency;
    }
}
