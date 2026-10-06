import { Select } from '@/components/ui/Field';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { presetKeyOf, recurrenceLabel, RECURRENCE_PRESETS, type BillingType, type IntervalUnit } from '@/lib/recurrence';
import { useState } from 'react';

export type RecurrenceValue = {
    billing_type: BillingType;
    interval_unit: IntervalUnit | '';
    interval_count: string;
};

type RecurrenceFieldsProps = {
    value: RecurrenceValue;
    onChange: (value: RecurrenceValue) => void;
    error?: string;
};

/**
 * Modalidad de cobro: pago único o recurrente con frecuencias habituales o una personalizada (cada N meses/años).
 * Reutilizable en catálogo, servicios contratados y conversión de cotizaciones.
 */
export function RecurrenceFields({ value, onChange, error }: RecurrenceFieldsProps) {
    const count = Number(value.interval_count);
    const hasRecurrence = value.billing_type === 'recurring' && value.interval_unit !== '' && count > 0;
    // "Personalizada" se muestra si el usuario la eligió o si el valor guardado no coincide con un atajo.
    const [chosenCustom, setCustomMode] = useState(false);
    const customMode = chosenCustom || (hasRecurrence && presetKeyOf({ unit: value.interval_unit as IntervalUnit, count }) === 'custom');

    const selectedPreset = customMode ? 'custom' : hasRecurrence ? presetKeyOf({ unit: value.interval_unit as IntervalUnit, count }) : '';

    function changeBillingType(billingType: BillingType) {
        if (billingType === 'one_time') {
            onChange({ billing_type: 'one_time', interval_unit: '', interval_count: '' });
            return;
        }
        // Al pasar a recurrente, anual es lo más común en hosting/dominios.
        onChange({ billing_type: 'recurring', interval_unit: value.interval_unit || 'year', interval_count: value.interval_count || '1' });
    }

    function choosePreset(key: string) {
        if (key === 'custom') {
            setCustomMode(true);
            return;
        }

        const preset = RECURRENCE_PRESETS.find((item) => item.key === key);
        if (preset) {
            setCustomMode(false);
            onChange({ billing_type: 'recurring', interval_unit: preset.recurrence.unit, interval_count: String(preset.recurrence.count) });
        }
    }

    return (
        <div className="flex flex-col gap-3">
            <SegmentedControl
                label="Modalidad de cobro"
                value={value.billing_type}
                onChange={changeBillingType}
                options={[
                    { value: 'one_time', label: 'Pago único' },
                    { value: 'recurring', label: 'Recurrente' },
                ]}
                className="self-start"
            />

            {value.billing_type === 'recurring' && (
                <div className="flex flex-wrap items-center gap-2">
                    <Select aria-label="Frecuencia" value={selectedPreset} onChange={(event) => choosePreset(event.target.value)} className="w-40" invalid={Boolean(error)}>
                        {RECURRENCE_PRESETS.map((preset) => (
                            <option key={preset.key} value={preset.key}>
                                {preset.label}
                            </option>
                        ))}
                        <option value="custom">Personalizada…</option>
                    </Select>

                    {customMode && (
                        <div className="flex items-center gap-2">
                            <span className="text-base text-ink-500">cada</span>
                            <input
                                aria-label="Cantidad"
                                inputMode="numeric"
                                value={value.interval_count}
                                onChange={(event) => onChange({ ...value, interval_count: event.target.value.replace(/\D/g, '').slice(0, 3) })}
                                className="numeric h-9 w-16 rounded-sm border border-line-strong bg-surface px-2 text-center text-base focus:border-brand-600 focus:ring-2 focus:ring-brand-600/25 focus:outline-none max-sm:h-11"
                            />
                            <Select aria-label="Unidad" value={value.interval_unit} onChange={(event) => onChange({ ...value, interval_unit: event.target.value as IntervalUnit })} className="w-28">
                                <option value="month">meses</option>
                                <option value="year">años</option>
                            </Select>
                        </div>
                    )}

                    {customMode && hasRecurrence && <span className="text-sm text-ink-500">= {recurrenceLabel({ unit: value.interval_unit as IntervalUnit, count })}</span>}
                </div>
            )}

            {error && <p className="text-xs text-danger-600">{error}</p>}
        </div>
    );
}
