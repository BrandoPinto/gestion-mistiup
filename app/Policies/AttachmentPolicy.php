<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

/**
 * Hoy solo existe el rol admin (Gate::before en AppServiceProvider). Con más roles, ver un adjunto
 * debería requerir permiso sobre su dueño (attachable).
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return false;
    }
}
