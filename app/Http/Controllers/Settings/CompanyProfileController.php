<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Audit\ModelChanges;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyProfileRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Mi empresa. El logo se guarda en disco privado y se sirve por BrandingController.
 */
class CompanyProfileController extends Controller
{
    private const LOGO_DISK = 'local';

    public function edit(): Response
    {
        Gate::authorize('manage-settings');

        $company = CompanyProfile::current();

        return Inertia::render('settings/Company', [
            'values' => [
                ...collect(['trade_name', 'legal_name', 'ruc', 'address', 'phone', 'email', 'website', 'yape_phone', 'plin_phone', 'wallet_holder', 'quote_intro', 'quote_terms'])
                    ->mapWithKeys(fn (string $field) => [$field => $company->{$field} ?? ''])
                    ->all(),
                'bank_accounts' => array_values($company->bank_accounts ?? []),
                'default_tax_rate' => rtrim(rtrim((string) $company->default_tax_rate, '0'), '.'),
                'default_currency' => $company->default_currency->value,
                'quote_validity_days' => (string) $company->quote_validity_days,
            ],
            'logo_url' => $company->logoUrl(),
        ]);
    }

    public function update(CompanyProfileRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $company = CompanyProfile::current();
        $company->forceFill($request->validated());
        $changes = ModelChanges::pending($company);
        $company->save();

        if ($changes !== []) {
            $logger->log('settings.company_updated', null, ['fields' => array_keys($changes)]);
        }

        return back()->with('success', 'Datos de la empresa guardados.');
    }

    public function uploadLogo(Request $request, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        // Sin SVG: puede contener scripts y el generador de PDF no lo soporta bien.
        $request->validate(
            ['logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000']],
            ['logo.mimes' => 'El logo debe ser PNG, JPG o WEBP.', 'logo.max' => 'El logo no puede pesar más de 2 MB.'],
        );

        $company = CompanyProfile::current();
        $file = $request->file('logo');
        $path = $file->storeAs('branding', 'logo-'.Str::uuid().'.'.$file->guessExtension(), self::LOGO_DISK);
        $previous = $company->logo_path;

        $company->forceFill(['logo_path' => $path])->save();

        if ($previous) {
            Storage::disk(self::LOGO_DISK)->delete($previous);
        }

        $logger->log('settings.logo_updated');

        return back()->with('success', 'Logo actualizado.');
    }

    public function deleteLogo(ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $company = CompanyProfile::current();

        if ($company->logo_path) {
            Storage::disk(self::LOGO_DISK)->delete($company->logo_path);
            $company->forceFill(['logo_path' => null])->save();
            $logger->log('settings.logo_removed');
        }

        return back()->with('success', 'Logo eliminado.');
    }
}
