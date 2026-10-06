<?php

namespace App\Queries;

use App\Domain\Shared\Money\Currency;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Historial de pagos con filtros por fecha, método y moneda. Los totales excluyen pagos anulados.
 */
class PaymentIndexQuery
{
    public const PER_PAGE = 25;

    /**
     * @return array{search: string, method: ?int, currency: ?string, from: ?string, to: ?string, voided: bool}
     */
    public function filters(Request $request): array
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->string($key)->toString()) ? $request->string($key)->toString() : null;

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'method' => $request->integer('method') ?: null,
            'currency' => Currency::tryFrom($request->string('currency')->toString())?->value,
            'from' => $date('from'),
            'to' => $date('to'),
            'voided' => $request->boolean('voided'),
        ];
    }

    /**
     * @param  array{search: string, method: ?int, currency: ?string, from: ?string, to: ?string, voided: bool}  $filters
     * @return Builder<Payment>
     */
    public function query(array $filters): Builder
    {
        $like = '%'.addcslashes($filters['search'], '%_\\').'%';

        return Payment::query()
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('reference', 'like', $like)
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like))
                ->orWhereHas('charge', fn (Builder $charge) => $charge->where('description', 'like', $like))))
            ->when($filters['method'], fn (Builder $query, int $method) => $query->where('payment_method_id', $method))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('paid_on', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('paid_on', '<=', $to))
            ->when(! $filters['voided'], fn (Builder $query) => $query->valid());
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginate(Builder $query): LengthAwarePaginator
    {
        return (clone $query)
            ->with(['client:id,name,deleted_at', 'charge:id,description', 'method:id,name', 'creator:id,name', 'attachments'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Total cobrado (solo pagos válidos) por moneda.
     *
     * @return list<array{currency: string, amount: string, count: int}>
     */
    public function totals(Builder $query): array
    {
        return (clone $query)
            ->valid()
            ->toBase()
            ->select('currency')
            ->selectRaw('SUM(amount) as amount, COUNT(*) as count')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (object $row) => [
                'currency' => $row->currency,
                'amount' => ChargeIndexQuery::decimal($row->amount),
                'count' => (int) $row->count,
            ])
            ->all();
    }
}
