<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Hoy solo existe el rol admin (Gate::before en AppServiceProvider). Las reglas quedan
 * declaradas aquí para que agregar roles no requiera tocar controladores.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Service $service): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Service $service): bool
    {
        return false;
    }

    public function delete(User $user, Service $service): bool
    {
        return false;
    }
}
