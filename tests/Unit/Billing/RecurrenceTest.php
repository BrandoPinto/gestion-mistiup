<?php

namespace Tests\Unit\Billing;

use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecurrenceTest extends TestCase
{
    /** @return array<string, array{IntervalUnit, int, IntervalUnit, int, string}> */
    public static function cases(): array
    {
        return [
            'mensual' => [IntervalUnit::Month, 1, IntervalUnit::Month, 1, 'Mensual'],
            'trimestral' => [IntervalUnit::Month, 3, IntervalUnit::Month, 3, 'Trimestral'],
            'semestral' => [IntervalUnit::Month, 6, IntervalUnit::Month, 6, 'Semestral'],
            '12 meses se guarda como 1 año' => [IntervalUnit::Month, 12, IntervalUnit::Year, 1, 'Anual'],
            '1 año' => [IntervalUnit::Year, 1, IntervalUnit::Year, 1, 'Anual'],
            '24 meses se guarda como 2 años' => [IntervalUnit::Month, 24, IntervalUnit::Year, 2, 'Cada 2 años'],
            '18 meses se queda en meses' => [IntervalUnit::Month, 18, IntervalUnit::Month, 18, 'Cada 18 meses'],
            '5 años' => [IntervalUnit::Year, 5, IntervalUnit::Year, 5, 'Cada 5 años'],
        ];
    }

    #[DataProvider('cases')]
    public function test_has_a_single_normalized_representation(IntervalUnit $unit, int $count, IntervalUnit $expectedUnit, int $expectedCount, string $label): void
    {
        $recurrence = Recurrence::of($unit, $count);

        $this->assertSame($expectedUnit, $recurrence->unit);
        $this->assertSame($expectedCount, $recurrence->count);
        $this->assertSame($label, $recurrence->label());
    }

    public function test_twelve_months_and_one_year_are_the_same_recurrence(): void
    {
        $this->assertTrue(Recurrence::of(IntervalUnit::Month, 12)->equals(Recurrence::of(IntervalUnit::Year, 1)));
    }

    public function test_rejects_zero_months(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Recurrence::of(IntervalUnit::Month, 0);
    }

    public function test_rejects_more_than_ten_years(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Recurrence::of(IntervalUnit::Year, 11);
    }
}
