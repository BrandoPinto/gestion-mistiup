import { HttpError, postJson } from '@/lib/http';
import { useEffect, useState } from 'react';
import type { ContractFormValues, SchedulePreview } from './types';

type PreviewState = {
    preview: SchedulePreview | null;
    error: string | null;
    loading: boolean;
};

function termMonths(values: ContractFormValues): number | null {
    if (values.billing_type !== 'recurring' || !values.has_term) return null;
    const count = Number(values.term_count);
    if (!count) return null;
    return values.term_unit === 'year' ? count * 12 : count;
}

/**
 * Pide al servidor el calendario de cobros cada vez que cambian inicio, modalidad o duración.
 * Las fechas se calculan solo en PHP (RecurrenceCalculator) para que la vista previa sea exactamente lo que se guardará.
 */
export function useSchedulePreview(values: ContractFormValues): PreviewState {
    const [state, setState] = useState<PreviewState>({ preview: null, error: null, loading: false });
    const term = termMonths(values);
    const ready =
        /^\d{4}-\d{2}-\d{2}$/.test(values.start_date) &&
        (values.billing_type === 'one_time' || (values.interval_unit !== '' && Number(values.interval_count) > 0)) &&
        (!values.has_term || values.billing_type === 'one_time' || term !== null);

    useEffect(() => {
        if (!ready) {
            setState({ preview: null, error: null, loading: false });
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setState((current) => ({ ...current, loading: true }));
            postJson<SchedulePreview>(
                route('contracts.schedule'),
                {
                    start_date: values.start_date,
                    billing_type: values.billing_type,
                    interval_unit: values.interval_unit || null,
                    interval_count: values.interval_count ? Number(values.interval_count) : null,
                    term_months: term,
                },
                controller.signal,
            )
                .then((preview) => setState({ preview, error: null, loading: false }))
                .catch((error: unknown) => {
                    if (controller.signal.aborted) return;
                    setState({ preview: null, error: error instanceof HttpError ? error.message : 'No se pudo calcular el calendario.', loading: false });
                });
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [ready, values.start_date, values.billing_type, values.interval_unit, values.interval_count, term]);

    return state;
}
