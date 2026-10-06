<?php

namespace App\Http\Controllers\Quotes;

use App\Domain\Quotes\Actions\SaveQuote;
use App\Domain\Shared\Dates\BusinessClock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\QuoteRequest;
use App\Http\Resources\ActivityPresenter;
use App\Http\Resources\ClientPresenter;
use App\Http\Resources\CompanyPresenter;
use App\Http\Resources\PaginatedData;
use App\Http\Resources\QuotePresenter;
use App\Http\Resources\ServicePresenter;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Quote;
use App\Models\Service;
use App\Queries\QuoteIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request, QuoteIndexQuery $query, BusinessClock $clock): Response
    {
        Gate::authorize('viewAny', Quote::class);

        $today = $clock->today();
        $filters = $query->filters($request);

        return Inertia::render('quotes/Index', [
            'quotes' => PaginatedData::from($query->paginate($filters, $today), fn (Quote $quote) => QuotePresenter::listItem($quote, $today)),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, BusinessClock $clock): Response
    {
        Gate::authorize('create', Quote::class);

        $client = $request->integer('client') ? Client::find($request->integer('client')) : null;

        return Inertia::render('quotes/Create', [
            ...$this->editorProps(),
            'client' => $client ? ClientPresenter::option($client) : null,
            'today' => $clock->todayString(),
        ]);
    }

    public function store(QuoteRequest $request, SaveQuote $saveQuote): RedirectResponse
    {
        $quote = $saveQuote->handle($request->validated(), $request->calculation(), $request->user()->id);

        return redirect()->route('quotes.show', $quote)->with('success', "Cotización {$quote->number} guardada como borrador.");
    }

    public function show(Quote $quote, BusinessClock $clock): Response
    {
        Gate::authorize('view', $quote);

        $quote->load(['client', 'items']);
        $today = $clock->today();

        return Inertia::render('quotes/Show', [
            'quote' => [
                ...QuotePresenter::listItem($quote, $today),
                'internal_notes' => $quote->internal_notes,
                'sent_at' => $quote->sent_at?->toIso8601String(),
                'cancelled_at' => $quote->cancelled_at?->toIso8601String(),
                'cancel_reason' => $quote->cancel_reason,
                'editable' => $quote->status->isEditable(),
                'public_url' => $quote->publicUrl(),
                'public_enabled' => $quote->public_enabled,
                'first_viewed_at' => $quote->first_viewed_at?->toIso8601String(),
                'last_viewed_at' => $quote->last_viewed_at?->toIso8601String(),
                'view_count' => $quote->view_count,
                'pdf_url' => route('quotes.pdf', $quote),
                'convertible' => $quote->status->isConvertible(),
            ],
            // Conceptos ya convertidos y su contrato (trazabilidad cotización → servicios).
            'conversions' => $quote->items()
                ->whereHas('contract')
                ->with('contract:id,quote_item_id,name,status')
                ->get()
                ->map(fn ($item) => [
                    'item' => $item->name,
                    'contract' => ['id' => $item->contract->id, 'name' => $item->contract->name, 'status' => $item->contract->status->value],
                ]),
            'document' => QuotePresenter::document($quote),
            'company' => CompanyPresenter::forDocument(CompanyProfile::current()),
            'activity' => fn () => $quote->activityLogs()
                ->with('user:id,name')
                ->latest('created_at')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(ActivityPresenter::item(...)),
        ]);
    }

    public function edit(Quote $quote): Response|RedirectResponse
    {
        Gate::authorize('update', $quote);

        if (! $quote->status->isEditable()) {
            return redirect()->route('quotes.show', $quote)->with('error', 'Esta cotización ya no se puede editar.');
        }

        $quote->load(['client', 'items']);

        return Inertia::render('quotes/Edit', [
            ...$this->editorProps(),
            'quote' => [
                'id' => $quote->id,
                'number' => $quote->number,
                'status' => $quote->status->value,
                'public_url' => $quote->public_enabled ? $quote->publicUrl() : null,
                'pdf_url' => route('quotes.pdf', $quote),
            ],
            'client' => ClientPresenter::option($quote->client),
            'values' => QuotePresenter::formValues($quote),
        ]);
    }

    public function update(QuoteRequest $request, Quote $quote, SaveQuote $saveQuote): RedirectResponse
    {
        $saveQuote->handle($request->validated(), $request->calculation(), $request->user()->id, $quote);

        return redirect()->route('quotes.show', $quote)->with('success', 'Cotización actualizada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function editorProps(): array
    {
        $company = CompanyProfile::current();

        return [
            'company' => CompanyPresenter::forDocument($company),
            'defaults' => CompanyPresenter::quoteDefaults($company),
            'catalog' => Service::active()->orderBy('name')->get()->map(ServicePresenter::item(...))->all(),
        ];
    }
}
