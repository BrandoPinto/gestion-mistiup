<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Dates\BusinessClock;
use App\Http\Resources\ChargePresenter;
use App\Http\Resources\ContractPresenter;
use App\Http\Resources\QuotePresenter;
use App\Models\Charge;
use App\Models\Quote;
use App\Queries\DashboardSummaryQuery;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen: ¿qué debo cobrar?, ¿quién me debe?, ¿qué vence pronto?, ¿cuánto he cobrado?,
 * ¿qué cotizaciones tengo pendientes? Todo por moneda.
 */
class DashboardController extends Controller
{
    public function __invoke(DashboardSummaryQuery $query, BusinessClock $clock): Response
    {
        $today = $clock->today();
        $monthStart = $today->startOfMonth();
        $previousStart = $monthStart->subMonth();

        return Inertia::render('dashboard/Index', [
            'today' => $today->toDateString(),
            'collected' => [
                'this_month' => $query->collected($monthStart, $today),
                // Mismo tramo del mes anterior (del 1 al mismo día) para comparar de forma justa.
                'previous_period' => $query->collected($previousStart, $previousStart->addDays(min($today->day, $previousStart->daysInMonth) - 1)),
            ],
            'receivables' => $query->receivables($today),
            'quotes' => $query->openQuotes(),
            'counts' => $query->counts(),
            'upcoming' => $query->upcomingCharges($today)->map(fn (Charge $charge) => ChargePresenter::listItem($charge, $today)),
            'overdue' => $query->overdueCharges($today)->map(fn (Charge $charge) => ChargePresenter::listItem($charge, $today)),
            'ending_contracts' => $query->endingContracts($today)->map(ContractPresenter::listItem(...)),
            'recent_quotes' => $query->recentOpenQuotes()->map(fn (Quote $quote) => QuotePresenter::listItem($quote, $today)),
            // Diferido: el gráfico no retrasa la carga del resto del resumen.
            'income' => Inertia::defer(fn () => $query->monthlyIncome($today)),
        ]);
    }
}
