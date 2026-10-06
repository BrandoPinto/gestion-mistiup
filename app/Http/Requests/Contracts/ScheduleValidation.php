<?php

namespace App\Http\Requests\Contracts;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Billing\Services\RecurrenceCalculator;
use Illuminate\Validation\Validator;

/**
 * Reglas de coherencia del calendario de un contrato, compartidas por el formulario de contratos
 * y la conversión de cotizaciones: frecuencia ≤ 10 años, duración ≤ 20 años y múltiplo exacto de la frecuencia.
 */
final class ScheduleValidation
{
    public const MAX_TERM_MONTHS = 240;

    /**
     * @param  array<string, mixed>  $data  Campos billing_type, interval_unit, interval_count, has_term, term_unit, term_count.
     * @param  string  $prefix  Prefijo de los nombres de campo para los errores ("" o "items.2.").
     * @return int|null Total de ciclos si el contrato tiene duración y es válida; null en otro caso.
     */
    public static function check(array $data, Validator $validator, string $prefix = ''): ?int
    {
        if (($data['billing_type'] ?? null) !== BillingType::Recurring->value) {
            return null;
        }

        $unit = IntervalUnit::from($data['interval_unit']);
        $count = (int) $data['interval_count'];
        $months = $unit === IntervalUnit::Year ? $count * 12 : $count;

        if ($months > Recurrence::MAX_MONTHS) {
            $validator->errors()->add($prefix.'interval_count', 'La frecuencia no puede superar 10 años.');

            return null;
        }

        if (empty($data['has_term'])) {
            return null;
        }

        $termMonths = self::termMonths($data);
        $recurrence = Recurrence::of($unit, $count);
        $calculator = app(RecurrenceCalculator::class);

        if ($termMonths > self::MAX_TERM_MONTHS) {
            $validator->errors()->add($prefix.'term_count', 'La duración no puede superar 20 años.');

            return null;
        }

        if (! $calculator->termFitsRecurrence($recurrence, $termMonths)) {
            $validator->errors()->add($prefix.'term_count', "La duración debe ser un número exacto de ciclos ({$recurrence->label()}).");

            return null;
        }

        return $calculator->cycleCount($recurrence, $termMonths);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function termMonths(array $data): int
    {
        $count = (int) ($data['term_count'] ?? 0);

        return ($data['term_unit'] ?? null) === IntervalUnit::Year->value ? $count * 12 : $count;
    }
}
