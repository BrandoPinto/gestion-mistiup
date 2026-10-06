<?php

namespace App\Http\Controllers\Users;

use App\Domain\Users\Actions\ManageUsers;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de usuarios (solo administradores). Las contraseñas nunca se muestran ni se registran.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-users');

        return Inertia::render('users/Index', [
            'users' => User::query()->orderByDesc('is_active')->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'is_current' => $user->id === $request->user()->id,
            ]),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    public function store(UserRequest $request, ManageUsers $users): RedirectResponse
    {
        $user = $users->create($request->validated());

        return back()->with('success', "Usuario {$user->email} creado.");
    }

    public function update(UserRequest $request, User $user, ManageUsers $users): RedirectResponse
    {
        $users->update($user, $request->validated(), $request->user());

        return back()->with('success', 'Usuario actualizado.');
    }

    public function resetPassword(Request $request, User $user, ManageUsers $users): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        $keep = $user->id === $request->user()->id ? $request->session()->getId() : null;
        $users->setPassword($user, $data['password'], $request->user(), $keep);

        return back()->with('success', 'Contraseña actualizada. Se cerraron las demás sesiones de ese usuario.');
    }

    public function setStatus(Request $request, User $user, ManageUsers $users): RedirectResponse
    {
        Gate::authorize('manage-users');

        $active = (bool) $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $users->setActive($user, $active, $request->user());

        return back()->with('success', $active ? 'Usuario activado.' : 'Usuario desactivado: ya no puede ingresar.');
    }
}
