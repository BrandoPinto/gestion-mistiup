<?php

namespace App\Http\Controllers\Users;

use App\Domain\Users\Actions\ManageUsers;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mi cuenta": cada usuario cambia sus propios datos y contraseña (pidiendo la actual).
 */
class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('account/Edit', [
            'account' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function update(Request $request, ManageUsers $users): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $users->update($user, [...$data, 'role' => $user->role->value], $user);

        return back()->with('success', 'Tus datos se guardaron.');
    }

    public function updatePassword(Request $request, ManageUsers $users): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ], ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        $users->setPassword($request->user(), $data['password'], $request->user(), $request->session()->getId());

        return back()->with('success', 'Contraseña actualizada. Se cerraron tus otras sesiones.');
    }
}
