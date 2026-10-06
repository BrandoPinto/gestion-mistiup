<?php

namespace Tests\Unit\Billing;

use App\Domain\Billing\Services\TaxBreakdown;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxBreakdownTest extends TestCase
{
    /** @return array<string, array{string, string, string, string}> */
    public static function cases(): array
    {
        return [
            '1180 con IGV 18%' => ['1180.00', '18.00', '1000.00', '180.00'],
            '350 con IGV 18%' => ['350.00', '18.00', '296.61', '53.39'],
            'céntimos' => ['0.01', '18.00', '0.01', '0.00'],
            'sin IGV' => ['350.00', '0.00', '350.00', '0.00'],
        ];
    }

    #[DataProvider('cases')]
    public function test_base_plus_tax_always_equals_total(string $total, string $rate, string $base, string $tax): void
    {
        $result = TaxBreakdown::fromTotal(Money::of($total, 'PEN'), $rate);

        $this->assertSame($base, (string) $result['base']->getAmount());
        $this->assertSame($tax, (string) $result['tax']->getAmount());
        $this->assertTrue($result['base']->plus($result['tax'])->isEqualTo(Money::of($total, 'PEN')));
    }
}
