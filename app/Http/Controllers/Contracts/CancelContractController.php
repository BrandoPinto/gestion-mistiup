<?php

namespace App\Http\Controllers\Contracts;

use App\Domain\Contracts\Actions\CancelContract;
use App\Http\Controllers\Controller;
use App\Models\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CancelContractController extends Controller
{
    public function __invoke(Request $request, ClientService $contract, CancelContract $cancelContract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'cancel_pending_charges' => ['required', 'boolean'],
        ]);

        $result = $cancelContract->handle(
            $contract,
            isset($data['reason']) ? trim($data['reason']) : null,
            (bool) $data['cancel_pending_charges'],
        );

        $message = 'Contrato cancelado: ya no generará nuevos cobros.';

        if ($result['cancelled_charges'] > 0) {
            $message .= " Se cancelaron {$result['cancelled_charges']} cobro(s) pendiente(s).";
        }

        if ($result['kept_partial'] > 0) {
            $message .= " Quedan {$result['kept_partial']} cobro(s) abierto(s) por revisar.";
        }

        return back()->with('success', $message);
    }
}
