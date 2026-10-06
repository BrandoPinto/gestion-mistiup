<?php

namespace App\Queries;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Enums\ClientType;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Listado de clientes con búsqueda, filtros, orden y paginación. Solo acepta valores de una lista blanca.
 */
class ClientIndexQuery
{
    public const SORTABLE = ['name', 'created_at'];

    public const PER_PAGE = [15, 25, 50];

    /**
     * @return array{search: string, status: ?string, type: ?string, sort: string, direction: string, per_page: int}
     */
    public function filters(Request $request): array
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString();
        $perPage = $request->integer('per_page', self::PER_PAGE[0]);
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => ClientStatus::tryFrom($status)?->value,
            'type' => ClientType::tryFrom($type)?->value,
            'sort' => in_array($sort, self::SORTABLE, true) ? $sort : 'name',
            'direction' => $direction === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : self::PER_PAGE[0],
        ];
    }

    /**
     * @param  array{search: string, status: ?string, type: ?string, sort: string, direction: string, per_page: int}  $filters
     * @return LengthAwarePaginator<int, Client>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Client::query()
            ->search($filters['search'])
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }
}
