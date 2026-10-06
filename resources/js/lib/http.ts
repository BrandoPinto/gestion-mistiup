/**
 * Peticiones JSON auxiliares (búsquedas, vistas previas). Las acciones que cambian datos van por Inertia.
 * Envía la cookie XSRF-TOKEN de Laravel como cabecera para pasar la protección CSRF.
 */

export class HttpError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        /** Errores de validación de Laravel (422): { campo: [mensajes] }. */
        public readonly errors: Record<string, string[]> | null = null,
    ) {
        super(message);
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function request<T>(method: 'GET' | 'POST', url: string, body?: unknown, signal?: AbortSignal): Promise<T> {
    const response = await fetch(url, {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(body !== undefined ? { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const body = payload && typeof payload === 'object' ? (payload as { message?: unknown; errors?: unknown }) : {};
        const message = typeof body.message === 'string' ? body.message : 'No se pudo completar la solicitud.';
        const errors = body.errors && typeof body.errors === 'object' ? (body.errors as Record<string, string[]>) : null;
        throw new HttpError(response.status, message, errors);
    }

    return payload as T;
}

export function getJson<T>(url: string, signal?: AbortSignal): Promise<T> {
    return request<T>('GET', url, undefined, signal);
}

export function postJson<T>(url: string, body: unknown, signal?: AbortSignal): Promise<T> {
    return request<T>('POST', url, body, signal);
}
