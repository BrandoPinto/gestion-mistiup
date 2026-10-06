<?php

namespace App\Http\Controllers\Contracts;

use App\Domain\Contracts\Actions\SaveContract;
use App\Domain\Contracts\Services\ContractSchedule;
use App\Domain\Shared\Dates\BusinessClock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractRequest;
use App\Http\Resources\ActivityPresenter;
use App\Http\Resources\ChargePresenter;
use App\Http\Resources\ContractPresenter;
use App\Http\Resources\PaginatedData;
use App\Http\Resources\ServicePresenter;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Service;
use App\Queries\ContractIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request, ContractIndexQuery $query): Response
    {
        Gate::authorize('viewAny', ClientService::class);

        $filters = $query->filters($request);

        return Inertia::render('contracts/Index', [
            'contracts' => PaginatedData::from($query->paginate($filters), ContractPresenter::listItem(...)),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ClientService::class);

        $client = $request->integer('client') ? Client::find($request->integer('client')) : null;

        return Inertia::render('contracts/Create', [
            'client' => $client ? ['id' => $client->id, 'name' => $client->name, 'document' => $client->displayDocument()] : null,
            'catalog' => $this->catalog(),
        ]);
    }

    public function store(ContractRequest $request, SaveContract $saveContract): RedirectResponse
    {
        $contract = $saveContract->handle($request->terms());

        return redirect()->route('contracts.show', $contract)->with('success', 'Servicio contratado registrado.');
    }

    public function show(ClientService $contract, ContractSchedule $schedule, BusinessClock $clock): Response
    {
        Gate::authorize('view', $contract);

        $contract->load(['client:id,name,deleted_at', 'service:id,name,deleted_at', 'quoteItem.quote:id,number']);
        $today = $clock->today();

        return Inertia::render('contracts/Show', [
            'contract' => ContractPresenter::detail($contract),
            'upcoming' => $schedule->upcoming($contract),
            'charges' => $contract->charges()
                ->with('client:id,name,deleted_at')
                ->orderByDesc('cycle_number')
                ->orderByDesc('due_date')
                ->get()
                ->map(fn (Charge $charge) => ChargePresenter::listItem($charge, $today)),
            'activity' => fn () => $contract->activityLogs()
                ->with('user:id,name')
                ->latest('created_at')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(ActivityPresenter::item(...)),
        ]);
    }

    public function edit(ClientService $contract): Response
    {
        Gate::authorize('update', $contract);

        $contract->load('client:id,name,document_type,document_number,deleted_at');

        return Inertia::render('contracts/Edit', [
            'contract' => ['id' => $contract->id, 'name' => $contract->name, 'status' => $contract->status->value, 'schedule_locked' => $contract->scheduleLocked()],
            'client' => ['id' => $contract->client->id, 'name' => $contract->client->name, 'document' => $contract->client->displayDocument()],
            'values' => ContractPresenter::formValues($contract),
            'catalog' => $this->catalog(),
        ]);
    }

    public function update(ContractRequest $request, ClientService $contract, SaveContract $saveContract): RedirectResponse
    {
        $saveContract->handle($request->terms(), $contract);

        return redirect()->route('contracts.show', $contract)->with('success', 'Cambios guardados.');
    }

    /**
     * Servicios activos del catálogo para precargar condiciones. Es una lista corta: va completa.
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        return Service::active()->orderBy('name')->get()->map(ServicePresenter::item(...))->all();
    }
}
