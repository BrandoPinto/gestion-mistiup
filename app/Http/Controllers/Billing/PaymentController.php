<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Actions\RegisterPayment;
use App\Domain\Billing\Actions\VoidPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\RegisterPaymentRequest;
use App\Http\Resources\PaginatedData;
use App\Http\Resources\PaymentPresenter;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Queries\PaymentIndexQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, PaymentIndexQuery $query): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $query->filters($request);
        $builder = $query->query($filters);

        return Inertia::render('payments/Index', [
            'payments' => PaginatedData::from($query->paginate($builder), PaymentPresenter::item(...)),
            'totals' => $query->totals($builder),
            'filters' => $filters,
            'paymentMethods' => PaymentMethod::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(RegisterPaymentRequest $request, Charge $charge, RegisterPayment $registerPayment): RedirectResponse
    {
        $data = $request->validated();

        $registerPayment->handle(
            charge: $charge,
            amount: $data['amount'],
            paidOn: CarbonImmutable::parse($data['paid_on']),
            paymentMethodId: (int) $data['payment_method_id'],
            reference: $data['reference'],
            notes: $data['notes'],
            receipt: $request->file('receipt'),
            userId: $request->user()->id,
        );

        return back()->with('success', 'Pago registrado.');
    }

    public function void(Request $request, Payment $payment, VoidPayment $voidPayment): RedirectResponse
    {
        Gate::authorize('void', $payment);

        $data = $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'Indica el motivo de la anulación: queda en el historial.'],
        );

        $voidPayment->handle($payment, trim($data['reason']), $request->user()->id);

        return back()->with('success', 'Pago anulado. El saldo del cobro se recalculó.');
    }
}
