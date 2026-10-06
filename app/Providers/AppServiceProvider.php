<?php

namespace App\Providers;

use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fuera de producción: lazy loading (N+1) y atributos no asignables lanzan excepción.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Nombres estables en columnas polimórficas (activity_logs, attachments…), independientes del namespace.
        Relation::enforceMorphMap([
            'user' => User::class,
            'client' => Client::class,
            'service' => Service::class,
            'contract' => ClientService::class,
            'charge' => Charge::class,
            'payment' => Payment::class,
            'quote' => Quote::class,
        ]);

        // URLs en español: /clientes/nuevo, /clientes/5/editar.
        Route::resourceVerbs(['create' => 'nuevo', 'edit' => 'editar']);

        // Rol único por ahora: el administrador puede todo. Las Policies quedan listas para más roles.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        // Configuración del sistema (empresa, recordatorios, métodos de pago…): solo administradores.
        Gate::define('manage-settings', fn (User $user) => false);
        Gate::define('manage-users', fn (User $user) => false);

        // Política de contraseñas: sin servicio externo (uncompromised) para no depender de red en cPanel.
        Password::defaults(fn () => Password::min(10)->letters()->numbers());
    }
}
