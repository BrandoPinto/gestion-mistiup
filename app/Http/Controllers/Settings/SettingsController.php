<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Índice de Configuración: enlaza a cada sección.
 */
class SettingsController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('manage-settings');

        return Inertia::render('settings/Index');
    }
}
