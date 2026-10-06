<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'default_currency' => 'PEN',
            'default_price' => fake()->randomElement(['80.00', '350.00', '1200.00', '2500.00']),
            'default_billing_type' => 'one_time',
            'default_interval_unit' => null,
            'default_interval_count' => null,
            'is_active' => true,
        ];
    }

    public function recurring(string $unit = 'year', int $count = 1): static
    {
        return $this->state(fn () => [
            'default_billing_type' => 'recurring',
            'default_interval_unit' => $unit,
            'default_interval_count' => $count,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
