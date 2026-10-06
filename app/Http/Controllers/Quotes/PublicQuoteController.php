<?php

namespace App\Http\Controllers\Quotes;

use App\Domain\Quotes\Actions\RecordQuoteView;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Pdf\QuotePdfRenderer;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyPresenter;
use App\Http\Resources\QuotePresenter;
use App\Models\CompanyProfile;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Enlace público /q/{token}: el cliente solo puede VER la cotización y descargar su PDF.
 * Sin sesión, sin acciones, sin acceso a otras cotizaciones. El token es aleatorio (nunca el id) y
 * un token inexistente o desactivado responde 404 sin dar pistas.
 */
class PublicQuoteController extends Controller
{
    public function show(Request $request, string $token, RecordQuoteView $recordView): SymfonyResponse
    {
        $quote = $this->find($token);
        $recordView->handle($quote, $request->session(), $request->user() !== null);

        return Inertia::render('public/Quote', [
            'document' => QuotePresenter::document($quote),
            'company' => CompanyPresenter::forDocument(CompanyProfile::current()),
            'cancelled' => $quote->status === QuoteStatus::Cancelled,
            'pdf_url' => route('quotes.public.pdf', $token),
        ])->toResponse($request)->withHeaders($this->privacyHeaders());
    }

    public function pdf(string $token, QuotePdfRenderer $renderer): HttpResponse
    {
        $quote = $this->find($token);

        return response($renderer->render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$quote->pdfFilename().'"',
            ...$this->privacyHeaders(),
        ]);
    }

    private function find(string $token): Quote
    {
        // Formato esperado antes de consultar: evita búsquedas con entradas arbitrarias.
        abort_unless(preg_match('/^[A-Za-z0-9]{40,64}$/', $token) === 1, 404);

        return Quote::query()
            ->where('public_token', $token)
            ->where('public_enabled', true)
            ->with(['client', 'items'])
            ->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function privacyHeaders(): array
    {
        return [
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ];
    }
}
