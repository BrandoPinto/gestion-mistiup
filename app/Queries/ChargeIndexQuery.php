<?php

namespace App\Queries;

use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Shared\Money\Currency;
use App\Models\Charge;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Listado de cobros con filtros y totales del saldo POR MONEDA (nunca se suman PEN y USD).
 * Estados de filtro: open (pendiente o parcial, por defecto), overdue (calculado), pending, partial, paid, cancelled, all.
 */
class ChargeIndexQuery
{
    public const PER_PAGE = 25;

    public const STATUSES = ['open', 'overdue', 'pending', 'partial', 'paid', 'cancelled', 'all'];

    /**
     * @return array{search: string, status: string, currency: ?string, from: ?string, to: ?string}
     */
    public function filters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->string($key)->toString()) ? $request->string($key)->toString() : null;

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'open',
            'currency' => Currency::tryFrom($request->string('currency')->toString())?->value,
            'from' => $date('from'),
            'to' => $date('to'),
        ];
    }

    /**
     * @param  array{search: string, status: string, currency: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<Charge>
     */
    public function query(array $filters, CarbonImmutable $today, ?int $clientId = null): Builder
    {
        $like = '%'.addcslashes($filters['search'], '%_\\').'%';

        return Charge::query()
            ->when($clientId, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('description', 'like', $like)
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like))))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('due_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('due_date', '<=', $to))
            ->tap(fn (Builder $query) => match ($filters['status']) {
                'open' => $query->open(),
                'overdue' => $query->overdue($today),
                'all' => $query,
                default => $query->where('status', ChargeStatus::from($filters['status'])->value),
            });
    }

    /**
     * @return LengthAwarePaginator<int, Charge>
     */
    public function paginate(Builder $query, string $status): LengthAwarePaginator
    {
        $finished = in_array($status, ['paid', 'cancelled'], true);

        return (clone $query)
            ->with(['client:id,name,deleted_at', 'contract:id,name,deleted_at'])
            // Abiertos: lo más urgente primero. Pagados/cancelados: lo más reciente primero.
            ->orderBy('due_date', $finished ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Totales del resultado filtrado, agrupados por moneda (agregación en SQL).
     *
     * @return list<array{currency: string, amount: string, paid: string, balance: string, count: int}>
     */
    public function totals(Builder $query): array
    {
        return (clone $query)
            ->toBase()
            ->select('currency')
            ->selectRaw('SUM(amount) as amount, SUM(amount_paid) as paid, SUM(amount - amount_paid) as balance, COUNT(*) as count')
            ->groupBy('currency')
            ->orderBy('currency') // PEN antes que USD (orden alfabético).
            ->get()
            ->map(fn (object $row) => [
                'currency' => $row->currency,
                'amount' => self::decimal($row->amount),
                'paid' => self::decimal($row->paid),
                'balance' => self::decimal($row->balance),
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /** Normaliza el resultado SQL (string en MySQL, a veces número en SQLite) a texto con 2 decimales, sin float. */
    public static function decimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HalfUp);
    }
}
