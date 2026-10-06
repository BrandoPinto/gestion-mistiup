<?php

namespace App\Domain\Quotes\Services;

use App\Domain\Billing\Services\TaxBreakdown;
use App\Domain\Quotes\QuoteCalculationException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Cálculo de una cotización. Precios con IGV incluido. Espejo exacto de resources/js/lib/quote-math.ts:
 * ambos se verifican contra tests/fixtures/quote-calculations.json.
 *
 *   bruto línea      = redondear(cantidad × precio unitario, 2)
 *   descuento línea  = % → redondear(bruto × % / 100, 2)   ·   monto → el monto (no puede superar el bruto)
 *   total línea      = bruto − descuento
 *   subtotal         = Σ totales de línea
 *   descuento global = % → redondear(subtotal × % / 100, 2) ·   monto → el monto (no puede superar el subtotal)
 *   total            = subtotal − descuento global
 *   op. gravada/IGV  = TaxBreakdown (desglose a nivel de documento)
 * Redondeo siempre HALF_UP.
 */
class QuoteCalculator
{
    /**
     * @param  list<array{quantity: string, unit_price: string, discount_type: ?string, discount_value: ?string}>  $items
     * @return array{lines: list<array{gross: string, discount: string, total: string}>, subtotal: string, global_discount: string, discount_total: string, total: string, tax_base: string, tax_amount: string}
     *
     * @throws QuoteCalculationException
     */
    public function calculate(array $items, ?string $globalDiscountType, ?string $globalDiscountValue, string $taxRate, string $currency): array
    {
        $lines = [];
        $subtotal = BigDecimal::zero();
        $lineDiscounts = BigDecimal::zero();

        foreach ($items as $index => $item) {
            $gross = BigDecimal::of($item['quantity'])->multipliedBy($item['unit_price'])->toScale(2, RoundingMode::HalfUp);
            $discount = $this->discount($gross, $item['discount_type'] ?? null, $item['discount_value'] ?? null, "items.{$index}.discount_value");
            $total = $gross->minus($discount);

            $lines[] = ['gross' => (string) $gross, 'discount' => (string) $discount, 'total' => (string) $total];
            $subtotal = $subtotal->plus($total);
            $lineDiscounts = $lineDiscounts->plus($discount);
        }

        $globalDiscount = $this->discount($subtotal, $globalDiscountType, $globalDiscountValue, 'global_discount_value');
        $total = $subtotal->minus($globalDiscount);
        $breakdown = TaxBreakdown::fromTotal(Money::of($total, $currency), $taxRate);

        return [
            'lines' => $lines,
            'subtotal' => (string) $subtotal->toScale(2),
            'global_discount' => (string) $globalDiscount,
            'discount_total' => (string) $lineDiscounts->plus($globalDiscount)->toScale(2),
            'total' => (string) $total->toScale(2),
            'tax_base' => (string) $breakdown['base']->getAmount(),
            'tax_amount' => (string) $breakdown['tax']->getAmount(),
        ];
    }

    private function discount(BigDecimal $base, ?string $type, ?string $value, string $field): BigDecimal
    {
        if ($type === null || $value === null || $value === '') {
            return BigDecimal::zero()->toScale(2);
        }

        $value = BigDecimal::of($value);

        if ($type === 'percent') {
            if ($value->isGreaterThan(100)) {
                throw new QuoteCalculationException($field, 'El descuento no puede superar el 100%.');
            }

            return $base->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp);
        }

        if ($value->isGreaterThan($base)) {
            throw new QuoteCalculationException($field, 'El descuento no puede ser mayor que el importe.');
        }

        return $value->toScale(2);
    }
}
