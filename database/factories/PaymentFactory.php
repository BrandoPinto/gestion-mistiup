<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para datos de prueba que no pasan por RegisterPayment: no recalcula el cobro.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_method_id' => fn () => PaymentMethod::query()->value('id'),
            'paid_on' => '2026-10-01',
            'currency' => 'PEN',
            'amount' => '100.00',
        ];
    }
}
