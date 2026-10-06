import '../css/app.css';

import { TooltipProvider } from '@/components/ui/Tooltip';
import { AppLayout } from '@/layouts/AppLayout';
import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = document.querySelector('title')?.textContent ?? 'Gestión';

/** Páginas que no usan el layout administrativo (login, enlace público de cotización). */
const STANDALONE_PREFIXES = ['auth/', 'public/'];

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx');
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Página Inertia no encontrada: ${name}`);
        }

        return (await page()).default;
    },
    layout: (name) => (STANDALONE_PREFIXES.some((prefix) => name.startsWith(prefix)) ? undefined : AppLayout),
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        createRoot(el).render(
            <TooltipProvider delayDuration={300}>
                <App {...props} />
            </TooltipProvider>,
        );
    },
    progress: {
        color: '#2664EB',
        delay: 200,
    },
});
