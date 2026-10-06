<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Hoy solo existe el rol admin (Gate::before en AppServiceProvider).
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function void(User $user, Payment $payment): bool
    {
        return false;
    }
}
