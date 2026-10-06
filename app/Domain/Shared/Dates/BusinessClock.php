<?php

namespace App\Domain\Shared\Dates;

use Carbon\CarbonImmutable;

/**
 * Fuente única de "hoy" para reglas de negocio (vencimientos, recordatorios, generación de cobros).
 *
 * Los timestamps se guardan en UTC; las fechas de negocio son DATE en la zona del negocio (Lima).
 * En tests se controla con Carbon::setTestNow().
 */
class BusinessClock
{
    public function timezone(): string
    {
        return config('app.business_timezone');
    }

    /** Instante actual expresado en la zona del negocio. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * Fecha de calendario de hoy en Lima, con la MISMA representación que las columnas DATE de los modelos
     * (medianoche en la zona de la app). Así se puede comparar directamente con start_date, due_date, etc.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->todayString());
    }

    public function todayString(): string
    {
        return $this->now()->toDateString();
    }
}
