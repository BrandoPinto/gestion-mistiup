<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

/**
 * Hoy solo existe el rol admin (Gate::before en AppServiceProvider).
 */
class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Quote $quote): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Quote $quote): bool
    {
        return false;
    }
}
