<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Dates\BusinessClock;
use App\Domain\Shared\Money\MoneyPresenter;
use App\Http\Resources\ChargePresenter;
use App\Http\Resources\ContractPresenter;
use App\Models\Charge;
use App\Queries\DueOverviewQuery;
use Brick\Money\Money;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DueController extends Controller
{
    private const GROUPS = ['overdue', 'week', 'month', 'later'];

    public function __invoke(DueOverviewQuery $query, BusinessClock $clock): Response
    {
        Gate::authorize('viewAny', Charge::class);

        $today = $clock->today();
        ['charges' => $charges, 'contracts' => $contracts] = $query->fetch($today);
        $grouped = $charges->groupBy(fn (Charge $charge) => DueOverviewQuery::bucket($charge, $today));

        $groups = [];
        foreach (self::GROUPS as $key) {
            $items = $grouped->get($key, collect());
            $groups[] = [
                'key' => $key,
                'charges' => $items->map(fn (Charge $charge) => ChargePresenter::listItem($charge, $today))->values(),
                'totals' => $this->balanceByCurrency($items),
            ];
        }

        return Inertia::render('due/Index', [
            'groups' => $groups,
            'contracts' => $contracts->map(ContractPresenter::listItem(...))->values(),
            'horizon_days' => DueOverviewQuery::HORIZON_DAYS,
        ]);
    }

    /**
     * Saldo por moneda con Brick\Money (los cobros ya están en memoria).
     *
     * @param  iterable<Charge>  $charges
     * @return list<array{currency: string, amount: string}>
     */
    private function balanceByCurrency(iterable $charges): array
    {
        $totals = [];

        foreach ($charges as $charge) {
            $currency = $charge->currency->value;
            $totals[$currency] = ($totals[$currency] ?? Money::zero($currency))->plus($charge->balance());
        }

        ksort($totals);

        return array_values(array_map(fn (Money $money) => MoneyPresenter::toArray($money), $totals));
    }
}
