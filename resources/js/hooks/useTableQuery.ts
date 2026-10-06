import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

type QueryValue = string | number | null | undefined;

/**
 * Sincroniza filtros/orden/página de un listado con la URL (compartible, sobrevive a recargar).
 * Cada cambio hace una visita parcial que solo recarga las props indicadas en `only`.
 */
export function useTableQuery<TFilters extends Record<string, QueryValue>>(
    routeName: string,
    filters: TFilters,
    only: string[],
    /** Valores por defecto del servidor: no se escriben en la URL. */
    defaults: Partial<TFilters> = {},
) {
    const [loading, setLoading] = useState(false);

    const apply = useCallback(
        (changes: Partial<TFilters> & { page?: number }) => {
            const next: Record<string, QueryValue> = { ...filters, page: undefined, ...changes };

            // No ensuciar la URL con valores vacíos.
            const query = Object.fromEntries(
                Object.entries(next).filter(([key, value]) => value !== null && value !== undefined && value !== '' && (defaults as Record<string, QueryValue>)[key] !== value),
            );

            router.get(route(routeName), query, {
                only,
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
        [filters, only, routeName, defaults],
    );

    return { apply, loading };
}
