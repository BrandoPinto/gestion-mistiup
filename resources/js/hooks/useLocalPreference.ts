import { useCallback, useState } from 'react';

const PREFIX = 'gestion.';

function read<T>(key: string, fallback: T): T {
    try {
        const raw = window.localStorage.getItem(PREFIX + key);
        return raw === null ? fallback : (JSON.parse(raw) as T);
    } catch {
        return fallback;
    }
}

/**
 * Preferencia visual por navegador (sidebar contraído, pestaña elegida…).
 * Nunca para datos de negocio: localStorage puede no estar disponible.
 */
export function useLocalPreference<T>(key: string, fallback: T): [T, (value: T) => void] {
    const [value, setValue] = useState<T>(() => read(key, fallback));

    const update = useCallback(
        (next: T) => {
            setValue(next);
            try {
                window.localStorage.setItem(PREFIX + key, JSON.stringify(next));
            } catch {
                // Sin almacenamiento disponible: la preferencia dura solo esta sesión.
            }
        },
        [key],
    );

    return [value, update];
}
