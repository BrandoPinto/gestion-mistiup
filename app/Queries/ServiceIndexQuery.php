<?php

namespace App\Queries;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ServiceIndexQuery
{
    public const PER_PAGE = 25;

    /**
     * @return array{search: string, status: ?string}
     */
    public function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : null,
        ];
    }

    /**
     * @param  array{search: string, status: ?string}  $filters
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Service::query()
            ->search($filters['search'])
            ->when($filters['status'], fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
