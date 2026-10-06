<?php

namespace Tests\Unit\Shared;

use App\Domain\Shared\Money\MoneyCast;
use App\Domain\Shared\Money\MoneyPresenter;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyCastTest extends TestCase
{
    private function model(string $currency = 'PEN'): Model
    {
        $model = new class extends Model
        {
            protected $guarded = [];

            protected function casts(): array
            {
                return ['amount' => MoneyCast::class.':currency'];
            }
        };

        $model->currency = $currency;

        return $model;
    }

    public function test_decimal_string_round_trips_with_two_decimals(): void
    {
        $model = $this->model();
        $model->amount = '350.5';

        $this->assertSame('350.50', $model->getAttributes()['amount']);
        $this->assertTrue($model->amount->isEqualTo(Money::of('350.50', 'PEN')));
    }

    public function test_floats_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model()->amount = 0.1 + 0.2;
    }

    public function test_more_than_two_decimals_are_rejected_instead_of_silently_rounded(): void
    {
        $this->expectException(RoundingNecessaryException::class);

        $this->model()->amount = '10.005';
    }

    public function test_money_in_another_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model('PEN')->amount = Money::of('100', 'USD');
    }

    public function test_format_groups_thousands_without_float(): void
    {
        $this->assertSame('S/ 1,234,567.80', MoneyPresenter::format(Money::of('1234567.8', 'PEN')));
        $this->assertSame('$ 0.05', MoneyPresenter::format(Money::of('0.05', 'USD')));
        $this->assertSame('-S/ 350.00', MoneyPresenter::format(Money::of('-350', 'PEN')));
    }

    public function test_sums_are_exact(): void
    {
        $total = Money::of('0.10', 'PEN')->plus(Money::of('0.20', 'PEN'));

        $this->assertSame('0.30', (string) $total->getAmount());
    }
}
