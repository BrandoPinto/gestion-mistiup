<?php

namespace App\Http\Controllers\Clients;

use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Clients\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientPresenter;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Endpoints JSON para selectores de cliente: búsqueda rápida y alta rápida desde otro formulario (cotizaciones).
 */
class ClientSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Client::class);

        $term = mb_substr(trim($request->string('q')->toString()), 0, 100);

        $clients = Client::query()
            ->where('status', ClientStatus::Active->value)
            ->search($term)
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(ClientPresenter::option(...));

        return response()->json(['data' => $clients]);
    }

    /** Alta rápida: mismas validaciones que el formulario completo; responde JSON (422 con errores). */
    public function store(ClientRequest $request, SaveClient $saveClient): JsonResponse
    {
        $client = $saveClient->handle($request->validated());

        return response()->json(['data' => ClientPresenter::option($client)], 201);
    }
}
