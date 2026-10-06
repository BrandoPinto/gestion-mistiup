<?php

namespace App\Http\Controllers\Quotes;

use App\Domain\Quotes\Actions\ChangeQuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuoteStatusController extends Controller
{
    public function send(Quote $quote, ChangeQuoteStatus $status): RedirectResponse
    {
        Gate::authorize('update', $quote);
        $status->markSent($quote);

        return back()->with('success', 'Cotización marcada como enviada.');
    }

    public function draft(Quote $quote, ChangeQuoteStatus $status): RedirectResponse
    {
        Gate::authorize('update', $quote);
        $status->backToDraft($quote);

        return back()->with('success', 'La cotización volvió a borrador.');
    }

    public function cancel(Request $request, Quote $quote, ChangeQuoteStatus $status): RedirectResponse
    {
        Gate::authorize('update', $quote);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $status->cancel($quote, isset($data['reason']) ? trim($data['reason']) : null);

        return back()->with('success', 'Cotización cancelada.');
    }
}
