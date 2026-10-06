<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Actions\AdjustChargeAmount;
use App\Domain\Billing\Actions\CancelCharge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ServiceRequest;
use App\Models\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Acciones puntuales sobre un cobro: ajustar el importe y cancelar.
 */
class ChargeActionsController extends Controller
{
    public function adjustAmount(Request $request, Charge $charge, AdjustChargeAmount $adjust): RedirectResponse
    {
        Gate::authorize('update', $charge);

        $request->merge(['amount' => str_replace(',', '', trim((string) $request->input('amount')))]);
        $data = $request->validate([
            'amount' => ['required', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'amount.regex' => 'Ingresa un importe válido (máximo 2 decimales).',
            'reason.required' => 'Indica el motivo del cambio: queda en el historial.',
        ]);

        $adjust->handle($charge, $data['amount'], trim($data['reason']));

        return back()->with('success', 'Importe actualizado.');
    }

    public function cancel(Request $request, Charge $charge, CancelCharge $cancelCharge): RedirectResponse
    {
        Gate::authorize('update', $charge);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $cancelCharge->handle($charge, isset($data['reason']) ? trim($data['reason']) : null);

        return back()->with('success', 'Cobro cancelado.');
    }
}
