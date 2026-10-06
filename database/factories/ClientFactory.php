<?php

namespace Database\Factories;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Enums\ClientType;
use App\Domain\Clients\Enums\DocumentType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ClientType::Company,
            'name' => fake()->unique()->company().' S.A.C.',
            'trade_name' => null,
            'document_type' => DocumentType::Ruc,
            'document_number' => self::validRuc('20'),
            'phone' => null,
            'whatsapp' => '9'.fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress().', Lima',
            'contact_name' => fake()->name(),
            'notes' => null,
            'status' => ClientStatus::Active,
        ];
    }

    public function person(): static
    {
        return $this->state(fn () => [
            'type' => ClientType::Person,
            'name' => fake()->name(),
            'document_type' => DocumentType::Dni,
            'document_number' => fake()->unique()->numerify('########'),
            'contact_name' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ClientStatus::Inactive]);
    }

    /** Genera un RUC con dígito verificador correcto. */
    public static function validRuc(string $prefix = '20'): string
    {
        $base = $prefix.fake()->unique()->numerify('########');
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $base[$index] * $weight;
        }

        $check = 11 - ($sum % 11);

        return $base.match ($check) {
            10 => 0,
            11 => 1,
            default => $check,
        };
    }
}
