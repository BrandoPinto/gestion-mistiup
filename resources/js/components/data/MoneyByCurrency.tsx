import { cn } from '@/lib/cn';
import type { Currency } from '@/types/money';
import { MoneyDisplay } from './MoneyDisplay';

type MoneyByCurrencyProps = {
    items: { currency: Currency; amount: string }[];
    size?: 'base' | 'lg' | 'xl';
    tone?: 'default' | 'danger' | 'success';
    /** Texto cuando no hay importes. */
    empty?: string;
    className?: string;
};

/**
 * Un importe por moneda, uno debajo del otro. Nunca se suman PEN y USD.
 * La primera moneda se muestra con el tamaño indicado; las siguientes, más pequeñas.
 */
export function MoneyByCurrency({ items, size = 'xl', tone = 'default', empty = '—', className }: MoneyByCurrencyProps) {
    if (items.length === 0) {
        return <span className={cn('text-ink-300', size === 'xl' ? 'text-2xl font-semibold' : 'text-lg font-semibold', className)}>{empty}</span>;
    }

    return (
        <span className={cn('flex flex-col gap-0.5', className)}>
            {items.map((item, index) => (
                <MoneyDisplay key={item.currency} value={item} size={index === 0 ? size : 'base'} tone={index === 0 ? tone : tone === 'default' ? 'muted' : tone} />
            ))}
        </span>
    );
}
