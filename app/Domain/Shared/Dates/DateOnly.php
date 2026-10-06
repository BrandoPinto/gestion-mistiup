<?php

namespace App\Domain\Shared\Dates;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Fecha de calendario (vencimientos, inicios, fechas de pago). Se guarda SIEMPRE como "Y-m-d", sin hora,
 * para que comparar contra '2026-11-10' funcione igual en MySQL y en SQLite. Se lee como CarbonImmutable
 * a medianoche en la zona de la app, comparable con BusinessClock::today().
 *
 * @implements CastsAttributes<CarbonImmutable, CarbonImmutable|string>
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse(substr((string) $value, 0, 10));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        throw new InvalidArgumentException("Fecha inválida para [{$key}].");
    }
}
