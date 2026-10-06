<?php

namespace App\Http\Controllers\Quotes;

use App\Domain\Quotes\Pdf\QuotePdfRenderer;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * PDF y gestión del enlace público desde el panel administrativo.
 */
class QuoteDocumentController extends Controller
{
    public function pdf(Request $request, Quote $quote, QuotePdfRenderer $renderer): Response
    {
        Gate::authorize('view', $quote);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($renderer->render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$quote->pdfFilename().'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function togglePublic(Request $request, Quote $quote, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $quote);

        $enabled = (bool) $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        $quote->forceFill(['public_enabled' => $enabled])->save();
        $logger->log($enabled ? 'quote.link_enabled' : 'quote.link_disabled', $quote);

        return back()->with('success', $enabled ? 'Enlace público activado.' : 'Enlace público desactivado: quien lo tenga ya no podrá abrirlo.');
    }

    /** Genera un token nuevo: el enlace anterior deja de funcionar de inmediato. */
    public function regenerateLink(Quote $quote, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $quote);

        $quote->forceFill(['public_token' => Str::random(48), 'public_enabled' => true])->save();
        $logger->log('quote.link_regenerated', $quote);

        return back()->with('success', 'Se generó un enlace nuevo. El anterior ya no funciona.');
    }
}
