import { cn } from '@/lib/cn';
import type { ReactNode } from 'react';

export type LedgerEntry = {
    label: string;
    /** Normalmente uno o dos <MoneyDisplay> (PEN y USD en líneas separadas). */
    value: ReactNode;
    hint?: ReactNode;
};

const COLUMNS = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-3',
    4: 'md:grid-cols-4',
} as const;

type LedgerStripProps = {
    entries: LedgerEntry[];
    /** Columnas en escritorio; por defecto, una por métrica. */
    columns?: keyof typeof COLUMNS;
    className?: string;
};

/**
 * Franja tipo libro contable: métricas en una sola banda separadas por reglas verticales.
 * Alternativa deliberada a la grilla de tarjetas idénticas.
 */
export function LedgerStrip({ entries, columns, className }: LedgerStripProps) {
    const desktopColumns = columns ?? (Math.min(Math.max(entries.length, 2), 4) as keyof typeof COLUMNS);

    return (
        <dl className={cn('grid grid-cols-2 overflow-hidden rounded-lg border border-line bg-surface', COLUMNS[desktopColumns], className)}>
            {entries.map((entry) => (
                // Cada celda dibuja su borde derecho e inferior; el contenedor recorta los sobrantes del borde exterior.
                <div key={entry.label} className="-mr-px -mb-px flex min-w-0 flex-col gap-2 border-r border-b border-line px-4 py-4 sm:px-5">
                    <dt className="eyebrow">{entry.label}</dt>
                    <dd className="flex flex-col gap-0.5">{entry.value}</dd>
                    {entry.hint && <dd className="text-xs text-ink-500">{entry.hint}</dd>}
                </div>
            ))}
        </dl>
    );
}
