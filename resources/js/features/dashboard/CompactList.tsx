import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type CompactRow = {
    key: string | number;
    href: string;
    title: string;
    subtitle?: ReactNode;
    value?: ReactNode;
    meta?: ReactNode;
};

/** Lista compacta de filas enlazadas para los paneles del resumen (no una tabla completa). */
export function CompactList({ rows, empty }: { rows: CompactRow[]; empty: string }) {
    if (rows.length === 0) {
        return <p className="px-4 py-6 text-center text-base text-ink-500">{empty}</p>;
    }

    return (
        <ul className="divide-y divide-line">
            {rows.map((row) => (
                <li key={row.key}>
                    <Link href={row.href} className={cn('flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-cream-50')}>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-base text-ink-900">{row.title}</p>
                            {row.subtitle && <p className="truncate text-xs text-ink-500">{row.subtitle}</p>}
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-0.5 text-right">
                            {row.value}
                            {row.meta && <span className="text-xs text-ink-500">{row.meta}</span>}
                        </div>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
