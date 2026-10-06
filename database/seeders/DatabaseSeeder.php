<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea el administrador inicial a partir de ADMIN_* en .env. Es idempotente.
     */
    public function run(): void
    {
        ['name' => $name, 'email' => $email, 'password' => $password] = config('app.initial_admin');

        if (! $email || ! $password) {
            throw new RuntimeException('Define ADMIN_EMAIL y ADMIN_PASSWORD en .env antes de ejecutar el seeder.');
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );
    }
}
