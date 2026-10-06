<?php

namespace App\Queries;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Payment;
use App\Models\Quote;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Datos del dashboard. Cada métrica es una consulta agregada o acotada (sin cargar historiales):
 * importes SIEMPRE agrupados por moneda, nunca sumando PEN con USD.
 */
class DashboardSummaryQuery
{
    public const UPCOMING_DAYS = 30;

    public const MONTHS = 12;

    /**
     * Cobrado en un rango de fechas (pagos válidos), por moneda.
     *
     * @return list<array{currency: string, amount: string, count: int}>
     */
    public function collected(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Payment::query()
            ->valid()
            ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->toBase()
            ->select('currency')
            ->selectRaw('SUM(amount) as amount, COUNT(*) as count')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (object $row) => ['currency' => $row->currency, 'amount' => ChargeIndexQuery::decimal($row->amount), 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Saldo por cobrar de cobros abiertos, por moneda: total, vencido y lo que vence en los próximos días.
     *
     * @return list<array{currency: string, balance: string, overdue: string, overdue_count: int, upcoming: string}>
     */
    public function receivables(CarbonImmutable $today): array
    {
        $todayString = $today->toDateString();
        $upcomingEnd = $today->addDays(self::UPCOMING_DAYS)->toDateString();

        return Charge::query()
            ->open()
            ->toBase()
            ->select('currency')
            ->selectRaw('SUM(amount - amount_paid) as balance')
            ->selectRaw('SUM(CASE WHEN due_date < ? THEN amount - amount_paid ELSE 0 END) as overdue', [$todayString])
            ->selectRaw('SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue_count', [$todayString])
            ->selectRaw('SUM(CASE WHEN due_date BETWEEN ? AND ? THEN amount - amount_paid ELSE 0 END) as upcoming', [$todayString, $upcomingEnd])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (object $row) => [
                'currency' => $row->currency,
                'balance' => ChargeIndexQuery::decimal($row->balance),
                'overdue' => ChargeIndexQuery::decimal($row->overdue),
                'overdue_count' => (int) $row->overdue_count,
                'upcoming' => ChargeIndexQuery::decimal($row->upcoming),
            ])
            ->all();
    }

    /**
     * Cotizaciones abiertas (borrador/enviada): cantidad y monto por moneda.
     *
     * @return array{count: int, viewed: int, totals: list<array{currency: string, amount: string}>}
     */
    public function openQuotes(): array
    {
        $rows = Quote::query()
            ->open()
            ->toBase()
            ->select('currency')
            ->selectRaw('SUM(total) as amount, COUNT(*) as count, SUM(CASE WHEN first_viewed_at IS NOT NULL THEN 1 ELSE 0 END) as viewed')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        return [
            'count' => (int) $rows->sum('count'),
            'viewed' => (int) $rows->sum('viewed'),
            'totals' => $rows->map(fn (object $row) => ['currency' => $row->currency, 'amount' => ChargeIndexQuery::decimal($row->amount)])->values()->all(),
        ];
    }

    /**
     * @return array{active_clients: int, active_contracts: int}
     */
    public function counts(): array
    {
        return [
            'active_clients' => Client::query()->where('status', ClientStatus::Active->value)->count(),
            'active_contracts' => ClientService::query()->where('status', ContractStatus::Active->value)->count(),
        ];
    }

    /** @return Collection<int, Charge> */
    public function upcomingCharges(CarbonImmutable $today, int $limit = 8): Collection
    {
        return Charge::query()
            ->open()
            ->whereBetween('due_date', [$today->toDateString(), $today->addDays(self::UPCOMING_DAYS)->toDateString()])
            ->with(['client:id,name,deleted_at', 'contract:id,name,deleted_at'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** Los más atrasados primero. @return Collection<int, Charge> */
    public function overdueCharges(CarbonImmutable $today, int $limit = 6): Collection
    {
        return Charge::query()
            ->overdue($today)
            ->with(['client:id,name,deleted_at', 'contract:id,name,deleted_at'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, ClientService> */
    public function endingContracts(CarbonImmutable $today, int $days = 60, int $limit = 5): Collection
    {
        return ClientService::query()
            ->where('status', ContractStatus::Active->value)
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->with('client:id,name,deleted_at')
            ->orderBy('end_date')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, Quote> */
    public function recentOpenQuotes(int $limit = 6): Collection
    {
        return Quote::query()
            ->open()
            ->with('client:id,name,deleted_at')
            ->orderByDesc('issue_date')
            ->orderByDesc('sequence')
            ->limit($limit)
            ->get();
    }

    /**
     * Ingresos (pagos válidos) por mes y moneda de los últimos 12 meses, incluido el actual.
     * Agrupa por día en SQL (portable entre MySQL y SQLite) y suma por mes con BigDecimal.
     *
     * @return list<array{month: string, totals: array<string, string>}>
     */
    public function monthlyIncome(CarbonImmutable $today): array
    {
        $firstMonth = $today->startOfMonth()->subMonths(self::MONTHS - 1);
        $months = [];

        for ($month = $firstMonth; $month->lessThanOrEqualTo($today); $month = $month->addMonth()) {
            $months[$month->format('Y-m')] = ['PEN' => BigDecimal::zero(), 'USD' => BigDecimal::zero()];
        }

        $daily = Payment::query()
            ->valid()
            ->whereBetween('paid_on', [$firstMonth->toDateString(), $today->toDateString()])
            ->toBase()
            ->select('paid_on', 'currency')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('paid_on', 'currency')
            ->get();

        foreach ($daily as $row) {
            $key = substr((string) $row->paid_on, 0, 7);

            if (isset($months[$key][$row->currency])) {
                $months[$key][$row->currency] = $months[$key][$row->currency]->plus(ChargeIndexQuery::decimal($row->amount));
            }
        }

        return array_map(
            fn (string $month, array $totals) => ['month' => $month, 'totals' => array_map(fn (BigDecimal $amount) => (string) $amount->toScale(2), $totals)],
            array_keys($months),
            $months,
        );
    }
}
