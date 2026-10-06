<?php

namespace App\Queries;

use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Shared\Money\Currency;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Listado de cotizaciones. Filtro de estado: open (borrador/enviada, por defecto), expired (calculado), cada estado, all.
 */
class QuoteIndexQuery
{
    public const PER_PAGE = 25;

    public const STATUSES = ['open', 'expired', 'draft', 'sent', 'partially_converted', 'converted', 'cancelled', 'all'];

    /**
     * @return array{search: string, status: string, currency: ?string}
     */
    public function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'open',
            'currency' => Currency::tryFrom($request->string('currency')->toString())?->value,
        ];
    }

    /**
     * @param  array{search: string, status: string, currency: ?string}  $filters
     * @return LengthAwarePaginator<int, Quote>
     */
    public function paginate(array $filters, CarbonImmutable $today, ?int $clientId = null): LengthAwarePaginator
    {
        $like = '%'.addcslashes($filters['search'], '%_\\').'%';

        return Quote::query()
            ->with('client:id,name,deleted_at')
            ->when($clientId, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('number', 'like', $like)
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like))))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->tap(fn (Builder $query) => match ($filters['status']) {
                'open' => $query->open(),
                'expired' => $query->open()->where('valid_until', '<', $today->toDateString()),
                'all' => $query,
                default => $query->where('status', QuoteStatus::from($filters['status'])->value),
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('sequence')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
