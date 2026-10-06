<?php

namespace Tests\Unit\Quotes;

use App\Domain\Quotes\QuoteCalculationException;
use App\Domain\Quotes\Services\QuoteCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Los mismos casos se verifican en TypeScript (resources/js/lib/quote-math.test.ts): si alguno cambia,
 * la vista previa del editor y el cálculo guardado dejarían de coincidir.
 */
class QuoteCalculatorTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>}> */
    public static function fixtures(): array
    {
        $cases = json_decode(file_get_contents(__DIR__.'/../../fixtures/quote-calculations.json'), true, flags: JSON_THROW_ON_ERROR);

        return array_combine(array_column($cases, 'name'), array_map(fn (array $case) => [$case], $cases));
    }

    #[DataProvider('fixtures')]
    public function test_matches_shared_fixture(array $case): void
    {
        $result = (new QuoteCalculator)->calculate($case['items'], $case['global_discount_type'], $case['global_discount_value'], $case['tax_rate'], 'PEN');

        $this->assertSame($case['expected'], $result);
    }

    public function test_line_discount_cannot_exceed_its_amount(): void
    {
        try {
            (new QuoteCalculator)->calculate([['quantity' => '1', 'unit_price' => '100', 'discount_type' => 'amount', 'discount_value' => '150']], null, null, '18', 'PEN');
            $this->fail('Debió rechazar el descuento.');
        } catch (QuoteCalculationException $exception) {
            $this->assertSame('items.0.discount_value', $exception->field);
        }
    }

    public function test_percent_discount_cannot_exceed_100(): void
    {
        $this->expectException(QuoteCalculationException::class);

        (new QuoteCalculator)->calculate([['quantity' => '1', 'unit_price' => '100', 'discount_type' => null, 'discount_value' => null]], 'percent', '101', '18', 'PEN');
    }
}
