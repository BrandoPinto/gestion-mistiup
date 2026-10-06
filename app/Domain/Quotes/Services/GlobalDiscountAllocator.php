<?php

namespace App\Domain\Quotes\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Reparte el descuento global de una cotización entre sus líneas, en proporción al total de cada una.
 *
 * - La parte de cada línea se calcula sobre el subtotal de TODA la cotización, así el resultado no depende
 *   de qué conceptos se conviertan ni en qué orden.
 * - Redondeo HALF_UP a céntimos; la última línea con importe absorbe la diferencia para que la suma de
 *   las partes sea exactamente el descuento global (y la suma de netos, exactamente el total cotizado).
 */
class GlobalDiscountAllocator
{
    /**
     * @param  array<int|string, string>  $lineTotals  clave => total de línea ("350.00")
     * @return array<int|string, array{discount: string, net: string}>
     */
    public function allocate(array $lineTotals, string $globalDiscount): array
    {
        $discount = BigDecimal::of($globalDiscount);
        $subtotal = array_reduce($lineTotals, fn (BigDecimal $sum, string $total) => $sum->plus($total), BigDecimal::zero());

        if ($discount->isZero() || $subtotal->isZero()) {
            return array_map(fn (string $total) => ['discount' => '0.00', 'net' => (string) BigDecimal::of($total)->toScale(2)], $lineTotals);
        }

        $keys = array_keys($lineTotals);
        $lastWithAmount = null;
        foreach ($keys as $key) {
            if (BigDecimal::of($lineTotals[$key])->isPositive()) {
                $lastWithAmount = $key;
            }
        }

        $result = [];
        $assigned = BigDecimal::zero();

        foreach ($keys as $key) {
            $total = BigDecimal::of($lineTotals[$key]);

            if ($key === $lastWithAmount) {
                continue;
            }

            $share = $total->multipliedBy($discount)->dividedBy($subtotal, 2, RoundingMode::HalfUp);
            $assigned = $assigned->plus($share);
            $result[$key] = ['discount' => (string) $share, 'net' => (string) $total->minus($share)->toScale(2)];
        }

        $lastTotal = BigDecimal::of($lineTotals[$lastWithAmount]);
        $lastShare = $discount->minus($assigned)->toScale(2);
        $result[$lastWithAmount] = ['discount' => (string) $lastShare, 'net' => (string) $lastTotal->minus($lastShare)->toScale(2)];

        // Respetar el orden original de las claves.
        return array_replace(array_fill_keys($keys, null), $result);
    }
}
