<?php

namespace Tests\Unit\Quotes;

use App\Domain\Quotes\Services\GlobalDiscountAllocator;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

class GlobalDiscountAllocatorTest extends TestCase
{
    private function sum(array $result, string $field): string
    {
        return (string) array_reduce($result, fn (BigDecimal $carry, array $line) => $carry->plus($line[$field]), BigDecimal::zero())->toScale(2);
    }

    public function test_without_global_discount_net_equals_line_total(): void
    {
        $result = (new GlobalDiscountAllocator)->allocate([1 => '2500.00', 2 => '350.00'], '0.00');

        $this->assertSame([1 => ['discount' => '0.00', 'net' => '2500.00'], 2 => ['discount' => '0.00', 'net' => '350.00']], $result);
    }

    public function test_proportional_split_that_sums_exactly(): void
    {
        // 2930 con 293 de descuento (10%): partes 250 / 35 / 8.
        $result = (new GlobalDiscountAllocator)->allocate([10 => '2500.00', 11 => '350.00', 12 => '80.00'], '293.00');

        $this->assertSame(['discount' => '250.00', 'net' => '2250.00'], $result[10]);
        $this->assertSame(['discount' => '35.00', 'net' => '315.00'], $result[11]);
        $this->assertSame(['discount' => '8.00', 'net' => '72.00'], $result[12]);
    }

    public function test_rounding_remainder_goes_to_the_last_line(): void
    {
        // 100 entre tres líneas iguales: 33.33 + 33.33 + 33.34.
        $result = (new GlobalDiscountAllocator)->allocate(['a' => '300.00', 'b' => '300.00', 'c' => '300.00'], '100.00');

        $this->assertSame(['33.33', '33.33', '33.34'], array_column($result, 'discount'));
        $this->assertSame('100.00', $this->sum($result, 'discount'));
        $this->assertSame('800.00', $this->sum($result, 'net'));
    }

    public function test_zero_lines_get_no_share_and_keep_their_order(): void
    {
        $result = (new GlobalDiscountAllocator)->allocate(['x' => '0.00', 'y' => '200.00', 'z' => '100.00'], '30.00');

        $this->assertSame(['x', 'y', 'z'], array_keys($result));
        $this->assertSame('0.00', $result['x']['discount']);
        $this->assertSame('20.00', $result['y']['discount']);
        $this->assertSame('10.00', $result['z']['discount']);
    }
}
