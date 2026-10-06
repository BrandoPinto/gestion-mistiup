<?php

namespace App\Queries;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\ClientService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Listado global de servicios contratados. Por defecto muestra los activos ordenados por próximo cobro.
 */
class ContractIndexQuery
{
    public const PER_PAGE = 25;

    /**
     * @return array{search: string, status: string, billing_type: ?string}
     */
    public function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => $status === 'all' || ContractStatus::tryFrom($status) ? $status : ContractStatus::Active->value,
            'billing_type' => BillingType::tryFrom($request->string('billing_type')->toString())?->value,
        ];
    }

    /**
     * @param  array{search: string, status: string, billing_type: ?string}  $filters
     * @return LengthAwarePaginator<int, ClientService>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $like = '%'.addcslashes($filters['search'], '%_\\').'%';

        return ClientService::query()
            ->with('client:id,name,deleted_at')
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like))))
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['billing_type'], fn (Builder $query, string $type) => $query->where('billing_type', $type))
            // Próximo cobro primero; los que no tienen (finalizados/cancelados) al final.
            ->orderByRaw('next_charge_date is null')
            ->orderBy('next_charge_date')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
