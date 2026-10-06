import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';

export type TabNavItem = {
    key: string;
    label: string;
    href: string;
    count?: number;
};

/**
 * Pestañas como enlaces: la pestaña activa vive en la URL (?tab=), se puede compartir
 * y el servidor puede cargar solo los datos de esa pestaña.
 */
export function TabNav({ items, active, label }: { items: TabNavItem[]; active: string; label: string }) {
    return (
        <nav aria-label={label} className="-mb-px flex gap-1 overflow-x-auto border-b border-line [scrollbar-width:none]">
            {items.map((item) => {
                const isActive = item.key === active;

                return (
                    <Link
                        key={item.key}
                        href={item.href}
                        preserveScroll
                        aria-current={isActive ? 'page' : undefined}
                        className={cn(
                            'relative flex h-10 shrink-0 items-center gap-2 px-3 text-base whitespace-nowrap transition-colors max-sm:h-11',
                            isActive ? 'font-medium text-ink-900' : 'text-ink-500 hover:text-ink-900',
                        )}
                    >
                        {item.label}
                        {item.count !== undefined && <span className="numeric rounded-sm bg-cream-100 px-1.5 text-xs text-ink-500">{item.count}</span>}
                        <span aria-hidden className={cn('absolute inset-x-2 bottom-0 h-0.5 rounded-t-sm bg-brand-600 transition-opacity', isActive ? 'opacity-100' : 'opacity-0')} />
                    </Link>
                );
            })}
        </nav>
    );
}
