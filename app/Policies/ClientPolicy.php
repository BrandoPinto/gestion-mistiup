<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Hoy solo existe el rol admin (Gate::before en AppServiceProvider). Las reglas quedan
 * declaradas aquí para que agregar roles no requiera tocar controladores.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Client $client): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Client $client): bool
    {
        return false;
    }

    public function delete(User $user, Client $client): bool
    {
        return false;
    }
}
