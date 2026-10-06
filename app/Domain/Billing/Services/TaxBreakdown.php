<?php

namespace App\Domain\Billing\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Desglose de IGV de un total que YA lo incluye:
 *   base = redondear(total ÷ (1 + tasa/100), 2)  ·  IGV = total − base
 * El IGV se obtiene por diferencia para que base + IGV sume exactamente el total.
 */
final class TaxBreakdown
{
    /**
     * @return array{base: Money, tax: Money}
     */
    public static function fromTotal(Money $total, string $ratePercent): array
    {
        $rate = BigDecimal::of($ratePercent);

        if ($rate->isZero()) {
            return ['base' => $total, 'tax' => Money::zero($total->getCurrency())];
        }

        $divisor = BigDecimal::one()->plus($rate->dividedBy(100, 6, RoundingMode::Unnecessary));
        $base = Money::of(
            $total->getAmount()->dividedBy($divisor, 2, RoundingMode::HalfUp),
            $total->getCurrency(),
        );

        return ['base' => $base, 'tax' => $total->minus($base)];
    }
}
