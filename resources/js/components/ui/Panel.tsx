import { cn } from '@/lib/cn';
import type { ReactNode } from 'react';

type PanelProps = {
    title?: string;
    eyebrow?: string;
    actions?: ReactNode;
    /** Sin padding interno: para tablas que llegan al borde. */
    flush?: boolean;
    className?: string;
    children: ReactNode;
};

/** Superficie blanca con borde fino. Agrupa contenido relacionado; no usar para cada dato suelto. */
export function Panel({ title, eyebrow, actions, flush = false, className, children }: PanelProps) {
    const hasHeader = Boolean(title || eyebrow || actions);

    return (
        <section className={cn('rounded-lg border border-line bg-surface', className)}>
            {hasHeader && (
                <header className="flex min-h-12 items-center justify-between gap-4 border-b border-line px-4 py-2.5">
                    <div className="min-w-0">
                        {eyebrow && <p className="eyebrow">{eyebrow}</p>}
                        {title && <h2 className="truncate text-md font-semibold text-ink-900">{title}</h2>}
                    </div>
                    {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                </header>
            )}
            <div className={flush ? undefined : 'p-4'}>{children}</div>
        </section>
    );
}
