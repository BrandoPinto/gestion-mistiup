<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Settings\AppSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Cobros: con cuántos días de anticipación se genera el cobro de cada ciclo.
 */
class BillingSettingsController extends Controller
{
    public function edit(AppSettings $settings): Response
    {
        Gate::authorize('manage-settings');

        return Inertia::render('settings/Billing', [
            'charge_lead_days' => $settings->chargeLeadDays(),
        ]);
    }

    public function update(Request $request, AppSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $data = $request->validate(
            ['charge_lead_days' => ['required', 'integer', 'min:1', 'max:180']],
            [],
            ['charge_lead_days' => 'días de anticipación'],
        );

        $previous = $settings->chargeLeadDays();
        $settings->set(AppSettings::CHARGE_LEAD_DAYS, (int) $data['charge_lead_days']);
        $logger->log('settings.billing_updated', null, ['charge_lead_days' => ['from' => $previous, 'to' => (int) $data['charge_lead_days']]]);

        return back()->with('success', 'Parámetros de cobro guardados. Se aplican desde la próxima generación de cobros.');
    }
}
