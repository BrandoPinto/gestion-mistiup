import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    /** Línea superior pequeña: número de documento, sección o contexto. */
    eyebrow?: string;
    description?: ReactNode;
    actions?: ReactNode;
};

/** Encabezado editorial de página: eyebrow + título + acciones, cerrado por una línea fina. También fija el <title>. */
export function PageHeader({ title, eyebrow, description, actions }: PageHeaderProps) {
    return (
        <>
            <Head title={title} />
            <header className="mb-6 flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    {eyebrow && <p className="mb-1 eyebrow">{eyebrow}</p>}
                    <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink-900">{title}</h1>
                    {description && <p className="mt-1 text-base text-ink-500">{description}</p>}
                </div>
                {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
            </header>
        </>
    );
}
