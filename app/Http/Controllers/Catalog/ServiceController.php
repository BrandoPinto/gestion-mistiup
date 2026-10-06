<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\DeleteService;
use App\Domain\Catalog\Actions\SaveService;
use App\Domain\Catalog\Actions\SetServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ServiceRequest;
use App\Http\Resources\PaginatedData;
use App\Http\Resources\ServicePresenter;
use App\Models\Service;
use App\Queries\ServiceIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de servicios. Alta y edición se hacen en un modal sobre el listado.
 */
class ServiceController extends Controller
{
    public function index(Request $request, ServiceIndexQuery $query): Response
    {
        Gate::authorize('viewAny', Service::class);

        $filters = $query->filters($request);

        return Inertia::render('catalog/Index', [
            'services' => PaginatedData::from($query->paginate($filters), ServicePresenter::item(...)),
            'filters' => $filters,
        ]);
    }

    public function store(ServiceRequest $request, SaveService $saveService): RedirectResponse
    {
        $service = $saveService->handle($request->validated());

        return back()->with('success', "Servicio «{$service->name}» agregado al catálogo.");
    }

    public function update(ServiceRequest $request, Service $service, SaveService $saveService): RedirectResponse
    {
        $saveService->handle($request->validated(), $service);

        return back()->with('success', 'Cambios guardados.');
    }

    public function updateStatus(Request $request, Service $service, SetServiceStatus $setStatus): RedirectResponse
    {
        Gate::authorize('update', $service);

        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $setStatus->handle($service, (bool) $active);

        return back()->with('success', $active ? 'Servicio activado.' : 'Servicio desactivado: ya no se ofrecerá al contratar ni cotizar.');
    }

    public function destroy(Service $service, DeleteService $deleteService): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $deleteService->handle($service);

        return back()->with('success', "Servicio «{$service->name}» eliminado del catálogo.");
    }
}
