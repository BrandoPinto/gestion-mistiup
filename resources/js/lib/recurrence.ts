/**
 * Espejo de App\Domain\Billing\Recurrence: misma normalización y mismas etiquetas.
 * Sirve para mostrar la frecuencia mientras se edita; el backend vuelve a normalizar al guardar.
 */

export type BillingType = 'one_time' | 'recurring';
export type IntervalUnit = 'month' | 'year';

export type Recurrence = { unit: IntervalUnit; count: number };

export const MAX_RECURRENCE_MONTHS = 120;

export function toMonths({ unit, count }: Recurrence): number {
    return unit === 'year' ? count * 12 : count;
}

/** 12 meses → 1 año; 24 meses → 2 años; 18 meses se queda en meses. */
export function normalizeRecurrence(recurrence: Recurrence): Recurrence {
    const months = toMonths(recurrence);

    return months % 12 === 0 ? { unit: 'year', count: months / 12 } : { unit: 'month', count: months };
}

export function recurrenceLabel(recurrence: Recurrence): string {
    const normalized = normalizeRecurrence(recurrence);

    switch (toMonths(normalized)) {
        case 1:
            return 'Mensual';
        case 3:
            return 'Trimestral';
        case 6:
            return 'Semestral';
        case 12:
            return 'Anual';
        default:
            return normalized.unit === 'year' ? `Cada ${normalized.count} años` : `Cada ${normalized.count} meses`;
    }
}

/** Frecuencias habituales que se ofrecen como atajos. */
export const RECURRENCE_PRESETS: { key: string; label: string; recurrence: Recurrence }[] = [
    { key: 'monthly', label: 'Mensual', recurrence: { unit: 'month', count: 1 } },
    { key: 'quarterly', label: 'Trimestral', recurrence: { unit: 'month', count: 3 } },
    { key: 'semiannual', label: 'Semestral', recurrence: { unit: 'month', count: 6 } },
    { key: 'annual', label: 'Anual', recurrence: { unit: 'year', count: 1 } },
];

export function presetKeyOf(recurrence: Recurrence): string {
    const normalized = normalizeRecurrence(recurrence);
    const preset = RECURRENCE_PRESETS.find((item) => item.recurrence.unit === normalized.unit && item.recurrence.count === normalized.count);

    return preset?.key ?? 'custom';
}
