<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Actions\CreateManualCharge;
use App\Domain\Shared\Dates\BusinessClock;
use App\Domain\Shared\Money\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ManualChargeRequest;
use App\Http\Resources\ActivityPresenter;
use App\Http\Resources\ChargePresenter;
use App\Http\Resources\PaginatedData;
use App\Http\Resources\PaymentPresenter;
use App\Models\Charge;
use App\Models\PaymentMethod;
use App\Queries\ChargeIndexQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChargeController extends Controller
{
    public function index(Request $request, ChargeIndexQuery $query, BusinessClock $clock): Response
    {
        Gate::authorize('viewAny', Charge::class);

        $today = $clock->today();
        $filters = $query->filters($request);
        $builder = $query->query($filters, $today);

        return Inertia::render('charges/Index', [
            'charges' => PaginatedData::from($query->paginate($builder, $filters['status']), fn (Charge $charge) => ChargePresenter::listItem($charge, $today)),
            'totals' => $query->totals($builder),
            'filters' => $filters,
        ]);
    }

    public function store(ManualChargeRequest $request, CreateManualCharge $createCharge): RedirectResponse
    {
        $data = $request->validated();

        $charge = $createCharge->handle(
            clientId: (int) $data['client_id'],
            description: $data['description'],
            currency: Currency::from($data['currency']),
            amount: $data['amount'],
            dueDate: CarbonImmutable::parse($data['due_date']),
            notes: $data['notes'],
            userId: $request->user()->id,
        );

        return redirect()->route('charges.show', $charge)->with('success', 'Cobro registrado.');
    }

    public function show(Charge $charge, BusinessClock $clock): Response
    {
        Gate::authorize('view', $charge);

        $charge->load(['client:id,name,deleted_at', 'contract:id,name,deleted_at']);
        $payments = $charge->payments()
            ->with(['method:id,name', 'creator:id,name', 'attachments'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('charges/Show', [
            'charge' => ChargePresenter::detail($charge, $clock->today(), $payments->whereNull('voided_at')->isNotEmpty()),
            'payments' => $payments->map(PaymentPresenter::item(...)),
            'paymentMethods' => PaymentMethod::active()->get(['id', 'name']),
            'today' => $clock->todayString(),
            'activity' => fn () => $charge->activityLogs()
                ->with('user:id,name')
                ->latest('created_at')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(ActivityPresenter::item(...)),
        ]);
    }
}
