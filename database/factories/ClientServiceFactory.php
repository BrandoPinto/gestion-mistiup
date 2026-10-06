<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientService>
 */
class ClientServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'service_id' => null,
            'name' => 'Hosting empresarial',
            'description' => null,
            'currency' => 'PEN',
            'price' => '350.00',
            'billing_type' => 'recurring',
            'interval_unit' => 'year',
            'interval_count' => 1,
            'start_date' => '2026-10-10',
            'term_months' => null,
            'end_date' => null,
            'next_cycle_number' => 1,
            'next_charge_date' => '2026-10-10',
            'status' => 'active',
        ];
    }

    public function oneTime(string $price = '2500.00'): static
    {
        return $this->state(fn () => [
            'name' => 'Desarrollo web',
            'price' => $price,
            'billing_type' => 'one_time',
            'interval_unit' => null,
            'interval_count' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled', 'cancelled_at' => now(), 'next_charge_date' => null]);
    }
}
