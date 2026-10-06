<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\User;

/**
 * Hoy solo existe el rol admin (Gate::before en AppServiceProvider).
 */
class ChargePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Charge $charge): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Charge $charge): bool
    {
        return false;
    }
}
