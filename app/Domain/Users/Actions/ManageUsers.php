<?php

namespace App\Domain\Users\Actions;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Audit\ModelChanges;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Altas, cambios, contraseñas y activación de usuarios. Las contraseñas se guardan con el hash de Laravel
 * (cast "hashed"); nunca en texto plano ni en el log. Protege contra quedarse sin acceso: nadie puede
 * desactivarse a sí mismo y siempre queda al menos un administrador activo.
 */
class ManageUsers
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data): User
    {
        $user = User::create([...$data, 'is_active' => true]);
        $this->logger->log('user.created', $user, ['email' => $user->email, 'role' => $user->role->value]);

        return $user;
    }

    /**
     * @param  array{name: string, email: string, role: string}  $data
     */
    public function update(User $user, array $data, User $actor): void
    {
        DB::transaction(function () use ($user, $data, $actor) {
            $user->fill($data);

            if ($user->isDirty('role') && $user->id === $actor->id) {
                throw ValidationException::withMessages(['role' => 'No puedes cambiar tu propio rol.']);
            }

            $changes = ModelChanges::pending($user);
            $user->save();

            if ($changes !== []) {
                $this->logger->log('user.updated', $user, ['changes' => $changes]);
            }
        });
    }

    /** Contraseña nueva definida por un administrador (o por el propio usuario). Cierra sus otras sesiones. */
    public function setPassword(User $user, string $password, User $actor, ?string $keepSessionId = null): void
    {
        $user->forceFill(['password' => $password, 'remember_token' => null])->save();
        $this->endSessions($user, $keepSessionId);
        $this->logger->log($user->id === $actor->id ? 'user.password_changed' : 'user.password_reset', $user);
    }

    public function setActive(User $user, bool $active, User $actor): void
    {
        if ($user->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($user, $active, $actor) {
            if (! $active) {
                if ($user->id === $actor->id) {
                    throw ValidationException::withMessages(['user' => 'No puedes desactivar tu propio usuario.']);
                }

                // Bloquea las filas de administradores para que dos desactivaciones simultáneas no dejen cero.
                $activeAdmins = User::query()->active()->where('role', UserRole::Admin->value)->lockForUpdate()->count();

                if ($user->isAdmin() && $activeAdmins <= 1) {
                    throw ValidationException::withMessages(['user' => 'Debe quedar al menos un administrador activo.']);
                }
            }

            $user->forceFill(['is_active' => $active])->save();

            if (! $active) {
                $this->endSessions($user);
            }

            $this->logger->log($active ? 'user.activated' : 'user.deactivated', $user);
        });
    }

    /** Cierra las sesiones guardadas del usuario (driver database). Opcionalmente conserva la actual. */
    private function endSessions(User $user, ?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId, fn ($query, string $id) => $query->where('id', '!=', $id))
            ->delete();
    }
}
