<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Forma única de un listado paginado hacia el frontend (tipo Paginated<T> en TS).
 */
final class PaginatedData
{
    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
     * @param  callable(TItem): array<string, mixed>  $transform
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public static function from(LengthAwarePaginator $paginator, callable $transform): array
    {
        return [
            'data' => array_values(array_map($transform, $paginator->items())),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
