<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'client_service_id' => null,
            'description' => 'Trabajo puntual',
            'due_date' => '2026-10-20',
            'currency' => 'PEN',
            'amount' => '3000.00',
            'tax_rate' => '18.00',
            'tax_amount' => '457.63',
            'amount_paid' => '0.00',
            'status' => 'pending',
        ];
    }

    public function usd(string $amount = '500.00'): static
    {
        return $this->state(fn () => ['currency' => 'USD', 'amount' => $amount, 'tax_amount' => '0.00']);
    }
}
