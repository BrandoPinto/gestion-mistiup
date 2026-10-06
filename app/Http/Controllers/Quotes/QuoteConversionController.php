<?php

namespace App\Http\Controllers\Quotes;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Quotes\Actions\ConvertQuoteItems;
use App\Domain\Quotes\Services\GlobalDiscountAllocator;
use App\Domain\Shared\Dates\BusinessClock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\ConvertQuoteRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Convertir una cotización aceptada en servicios contratados, eligiendo qué conceptos y con qué modalidad.
 */
class QuoteConversionController extends Controller
{
    public function create(Quote $quote, GlobalDiscountAllocator $allocator, BusinessClock $clock): Response|RedirectResponse
    {
        Gate::authorize('update', $quote);

        if (! $quote->status->isConvertible()) {
            return redirect()->route('quotes.show', $quote)->with('error', 'Esta cotización ya no se puede convertir.');
        }

        $quote->load(['client', 'items.contract:id,quote_item_id,name']);

        // Precio sugerido = total de la línea menos su parte proporcional del descuento global.
        $allocation = $allocator->allocate(
            $quote->items->mapWithKeys(fn (QuoteItem $item) => [$item->id => $item->line_total])->all(),
            (string) $quote->global_discount_amount->getAmount(),
        );

        return Inertia::render('quotes/Convert', [
            'quote' => [
                'id' => $quote->id,
                'number' => $quote->number,
                'client' => ['id' => $quote->client->id, 'name' => $quote->client->name],
                'currency' => $quote->currency->value,
                'has_global_discount' => ! $quote->global_discount_amount->isZero(),
            ],
            'items' => $quote->items->map(fn (QuoteItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'allocated_discount' => $allocation[$item->id]['discount'],
                'net_price' => $allocation[$item->id]['net'],
                'suggested_billing_type' => $item->suggested_billing_type?->value ?? BillingType::OneTime->value,
                'suggested_interval_unit' => $item->suggested_interval_unit?->value,
                'suggested_interval_count' => $item->suggested_interval_count,
                'contract' => $item->contract ? ['id' => $item->contract->id, 'name' => $item->contract->name] : null,
            ])->values(),
            'today' => $clock->todayString(),
        ]);
    }

    public function store(ConvertQuoteRequest $request, Quote $quote, ConvertQuoteItems $convert): RedirectResponse
    {
        $contracts = $convert->handle($quote, $request->conversions());
        $count = count($contracts);

        return redirect()->route('quotes.show', $quote)->with(
            'success',
            $count === 1 ? 'Se creó 1 servicio contratado.' : "Se crearon {$count} servicios contratados.",
        );
    }
}
