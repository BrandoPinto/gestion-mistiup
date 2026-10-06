<?php

namespace App\Domain\Shared\Audit;

use BackedEnum;
use Brick\Money\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Cambios pendientes de guardar de un modelo, con valores normalizados para el log de auditoría:
 * importes como texto decimal, enums por su valor y fechas como "YYYY-MM-DD", sin depender del motor de base de datos.
 */
final class ModelChanges
{
    /**
     * Llamar ANTES de save().
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function pending(Model $model, array $ignore = ['updated_at']): array
    {
        $changes = [];

        foreach (array_keys($model->getDirty()) as $field) {
            if (in_array($field, $ignore, true)) {
                continue;
            }

            $from = self::normalize($model->getOriginal($field));
            $to = self::normalize($model->getAttribute($field));

            if ($from !== $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Money => (string) $value->getAmount(),
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format(DATE_ATOM),
            default => $value,
        };
    }
}
