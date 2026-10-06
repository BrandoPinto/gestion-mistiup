import type { route as routeFn } from 'ziggy-js';
import type { SharedProps } from './shared';

declare global {
    /** Inyectada por la directiva @routes de Ziggy en app.blade.php. */
    var route: typeof routeFn;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
