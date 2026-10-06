<?php

namespace App\Queries;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\Charge;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Vista "Vencimientos": cobros abiertos agrupados por urgencia y contratos que terminan pronto.
 * Una sola consulta de cobros (acotada a 90 días hacia adelante) que se agrupa en memoria.
 */
class DueOverviewQuery
{
    public const HORIZON_DAYS = 90;

    /** Límite por seguridad; por encima conviene filtrar desde Cobros. */
    private const MAX_CHARGES = 500;

    /**
     * @return array{charges: Collection<int, Charge>, contracts: Collection<int, ClientService>}
     */
    public function fetch(CarbonImmutable $today): array
    {
        $charges = Charge::query()
            ->open()
            ->where('due_date', '<=', $today->addDays(self::HORIZON_DAYS)->toDateString())
            ->with(['client:id,name,deleted_at', 'contract:id,name,deleted_at'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(self::MAX_CHARGES)
            ->get();

        $contracts = ClientService::query()
            ->where('status', ContractStatus::Active->value)
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays(self::HORIZON_DAYS)->toDateString()])
            ->with('client:id,name,deleted_at')
            ->orderBy('end_date')
            ->get();

        return ['charges' => $charges, 'contracts' => $contracts];
    }

    /**
     * Clave de grupo según días hasta el vencimiento.
     */
    public static function bucket(Charge $charge, CarbonImmutable $today): string
    {
        $days = (int) $today->diffInDays($charge->due_date, absolute: false);

        return match (true) {
            $days < 0 => 'overdue',
            $days <= 7 => 'week',
            $days <= 30 => 'month',
            default => 'later',
        };
    }
}
