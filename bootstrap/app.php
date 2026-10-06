<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Navegación Inertia: en vez del modal con la página de error, volver atrás con un aviso.
        // En local se mantiene el detalle del error para depurar (salvo el 419, que siempre es un aviso).
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $messages = [
                403 => 'No tienes permiso para realizar esta acción.',
                404 => 'No encontramos lo que buscabas; puede que ya no exista.',
                419 => 'La sesión expiró por inactividad. Vuelve a intentarlo.',
                429 => 'Demasiados intentos seguidos. Espera un minuto.',
                500 => 'Ocurrió un error inesperado. Inténtalo de nuevo en unos minutos.',
                503 => 'El sistema está en mantenimiento. Vuelve a intentarlo en unos minutos.',
            ];
            $status = $response->getStatusCode();
            $debug = config('app.debug') && $status !== 419;

            if (! $request->header('X-Inertia') || $debug || ! isset($messages[$status])) {
                return $response;
            }

            return back()->with('error', $messages[$status]);
        });
    })->create();
