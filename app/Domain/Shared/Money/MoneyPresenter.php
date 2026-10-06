<?php

namespace App\Domain\Shared\Money;

use Brick\Money\Money;

/**
 * Forma única en la que un importe viaja al frontend: string decimal + moneda. Nunca number.
 */
final class MoneyPresenter
{
    /**
     * @return array{amount: string, currency: string}
     */
    public static function toArray(Money $money): array
    {
        return [
            'amount' => (string) $money->getAmount(),
            'currency' => $money->getCurrency()->getCurrencyCode(),
        ];
    }

    /**
     * Texto para mensajes: "S/ 1,500.00". Agrupa miles sobre el string decimal (sin float).
     */
    public static function format(Money $money): string
    {
        $amount = (string) $money->getAmount();
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = explode('.', ltrim($amount, '-')) + [1 => '00'];
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
        $symbol = Currency::from($money->getCurrency()->getCurrencyCode())->symbol();

        return ($negative ? '-' : '')."{$symbol} {$grouped}.{$fraction}";
    }
}
