<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Login');
    }

    public function store(LoginRequest $request, ActivityLogger $logger): SymfonyResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $logger->log('auth.login', $user);

        // Recarga completa (no visita Inertia): sin sesión el navegador solo recibió las rutas públicas de Ziggy.
        return Inertia::location(redirect()->intended(route('dashboard', absolute: false))->getTargetUrl());
    }

    public function destroy(Request $request, ActivityLogger $logger): SymfonyResponse
    {
        $logger->log('auth.logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('login'));
    }
}
