<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo de la empresa. Es público a propósito: aparece en el enlace público de cotizaciones y en el PDF.
 * La URL lleva ?v=<hash> que cambia con cada logo, así que puede cachearse por largo tiempo.
 */
class BrandingController extends Controller
{
    public function logo(): StreamedResponse
    {
        $company = CompanyProfile::current();
        abort_unless($company->logo_path && Storage::disk('local')->exists($company->logo_path), 404);

        return Storage::disk('local')->response($company->logo_path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
