<?php

namespace App\Http\Controllers\Clients;

use App\Domain\Clients\Actions\DeleteClient;
use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Shared\Dates\BusinessClock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ActivityPresenter;
use App\Http\Resources\ChargePresenter;
use App\Http\Resources\ClientPresenter;
use App\Http\Resources\ContractPresenter;
use App\Http\Resources\PaginatedData;
use App\Http\Resources\QuotePresenter;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Quote;
use App\Queries\ClientBalanceQuery;
use App\Queries\ClientIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    private const TABS = ['summary', 'services', 'charges', 'quotes'];

    public function index(Request $request, ClientIndexQuery $query): Response
    {
        Gate::authorize('viewAny', Client::class);

        $filters = $query->filters($request);

        return Inertia::render('clients/Index', [
            'clients' => PaginatedData::from($query->paginate($filters), ClientPresenter::listItem(...)),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('clients/Create');
    }

    public function store(ClientRequest $request, SaveClient $saveClient): RedirectResponse
    {
        $client = $saveClient->handle($request->validated());

        return redirect()->route('clients.show', $client)->with('success', 'Cliente registrado.');
    }

    public function show(Request $request, Client $client, ClientBalanceQuery $balance, BusinessClock $clock): Response
    {
        Gate::authorize('view', $client);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'summary';

        $today = $clock->today();

        return Inertia::render('clients/Show', [
            'client' => ClientPresenter::detail($client),
            'tab' => $tab,
            'stats' => [
                'active_contracts' => $client->contracts()->active()->count(),
                'open_quotes' => $client->quotes()->open()->count(),
                ...$balance->for($client, $today),
            ],
            'quotes' => $tab === 'quotes'
                ? $client->quotes()
                    ->with('client:id,name,deleted_at')
                    ->orderByDesc('issue_date')
                    ->orderByDesc('sequence')
                    ->limit(200)
                    ->get()
                    ->map(fn (Quote $quote) => QuotePresenter::listItem($quote, $today))
                : [],
            // Abiertos primero (lo más urgente arriba) y luego el historial reciente.
            'charges' => $tab === 'charges'
                ? $client->charges()
                    ->with(['client:id,name,deleted_at', 'contract:id,name,deleted_at'])
                    ->orderByRaw("case when status in ('pending', 'partial') then 0 else 1 end")
                    ->orderByRaw("case when status in ('pending', 'partial') then due_date end asc")
                    ->orderByDesc('due_date')
                    ->limit(200)
                    ->get()
                    ->map(fn (Charge $charge) => ChargePresenter::listItem($charge, $today))
                : [],
            // Solo se consulta en la pestaña que lo necesita.
            'contracts' => $tab === 'services'
                ? $client->contracts()
                    ->with('client:id,name,deleted_at')
                    ->orderByRaw("case when status = 'active' then 0 else 1 end")
                    ->orderByRaw('next_charge_date is null')
                    ->orderBy('next_charge_date')
                    ->get()
                    ->map(ContractPresenter::listItem(...))
                : [],
            'activity' => fn () => $client->activityLogs()
                ->with('user:id,name')
                ->latest('created_at')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(ActivityPresenter::item(...)),
        ]);
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('clients/Edit', [
            'client' => ['id' => $client->id, 'name' => $client->name],
            'values' => ClientPresenter::formValues($client),
        ]);
    }

    public function update(ClientRequest $request, Client $client, SaveClient $saveClient): RedirectResponse
    {
        $saveClient->handle($request->validated(), $client);

        return redirect()->route('clients.show', $client)->with('success', 'Cambios guardados.');
    }

    public function destroy(Client $client, DeleteClient $deleteClient): RedirectResponse
    {
        Gate::authorize('delete', $client);

        $deleteClient->handle($client);

        return redirect()->route('clients.index')->with('success', "Cliente «{$client->name}» eliminado.");
    }
}
