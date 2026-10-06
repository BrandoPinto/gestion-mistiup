<?php

namespace App\Domain\Quotes\Pdf;

use App\Http\Resources\CompanyPresenter;
use App\Http\Resources\QuotePresenter;
use App\Models\CompanyProfile;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el PDF de una cotización con dompdf (PHP puro: funciona en cPanel sin Node ni Chrome).
 * Usa el mismo contrato de datos que la vista previa (QuotePresenter::document) para que ambos coincidan.
 * Recursos remotos deshabilitados: el logo se incrusta como data URI desde el disco privado.
 */
class QuotePdfRenderer
{
    public function render(Quote $quote): string
    {
        $quote->loadMissing(['client', 'items']);
        $company = CompanyProfile::current();

        return Pdf::loadView('pdf.quote', [
            'doc' => QuotePresenter::document($quote),
            'company' => CompanyPresenter::forDocument($company),
            'logo' => $this->logoDataUri($company),
            'fonts' => [
                'regular' => resource_path('fonts/Inter-Regular.ttf'),
                'medium' => resource_path('fonts/Inter-Medium.ttf'),
                'semibold' => resource_path('fonts/Inter-SemiBold.ttf'),
            ],
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'Inter')
            // Solo incrusta los caracteres usados: PDF de decenas de KB en vez de cientos.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    private function logoDataUri(CompanyProfile $company): ?string
    {
        if (! $company->logo_path || ! Storage::disk('local')->exists($company->logo_path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($company->logo_path);

        // dompdf solo dibuja WEBP si GD lo soporta; si no, el PDF muestra el nombre de la empresa.
        if ($mime === 'image/webp' && ! function_exists('imagecreatefromwebp')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($company->logo_path));
    }
}
