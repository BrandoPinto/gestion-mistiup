<?php

namespace App\Policies;

use App\Models\ClientService;
use App\Models\User;

/**
 * Hoy solo existe el rol admin (Gate::before en AppServiceProvider).
 */
class ClientServicePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ClientService $contract): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ClientService $contract): bool
    {
        return false;
    }
}
