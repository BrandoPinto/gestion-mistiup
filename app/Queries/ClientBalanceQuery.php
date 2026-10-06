<?php

namespace App\Queries;

use App\Models\Charge;
use App\Models\Client;
use Carbon\CarbonImmutable;

/**
 * Situación de cobro de un cliente: pendiente y vencido por moneda, y próximo vencimiento.
 * Dos consultas agregadas en SQL, sin cargar cobros en memoria.
 */
class ClientBalanceQuery
{
    /**
     * @return array{pending: list<array{currency: string, amount: string}>, overdue: list<array{currency: string, amount: string}>, next_due_date: ?string}
     */
    public function for(Client $client, CarbonImmutable $today): array
    {
        $rows = Charge::query()
            ->where('client_id', $client->id)
            ->open()
            ->toBase()
            ->select('currency')
            ->selectRaw('SUM(amount - amount_paid) as balance')
            ->selectRaw('SUM(CASE WHEN due_date < ? THEN amount - amount_paid ELSE 0 END) as overdue', [$today->toDateString()])
            ->selectRaw('MIN(CASE WHEN due_date >= ? THEN due_date END) as next_due', [$today->toDateString()])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $nextDue = $rows->pluck('next_due')->filter()->min();

        return [
            'pending' => $rows->map(fn (object $row) => ['currency' => $row->currency, 'amount' => ChargeIndexQuery::decimal($row->balance)])->values()->all(),
            'overdue' => $rows
                ->filter(fn (object $row) => ChargeIndexQuery::decimal($row->overdue) !== '0.00')
                ->map(fn (object $row) => ['currency' => $row->currency, 'amount' => ChargeIndexQuery::decimal($row->overdue)])
                ->values()
                ->all(),
            'next_due_date' => $nextDue ? substr((string) $nextDue, 0, 10) : null,
        ];
    }
}
