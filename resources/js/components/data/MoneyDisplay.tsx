import { cn } from '@/lib/cn';
import { CURRENCY_SYMBOL, formatAmount } from '@/lib/money';
import type { Money } from '@/types/money';

type MoneyDisplayProps = {
    value: Money;
    /** `muted` atenúa el símbolo; `danger` para saldos vencidos. */
    tone?: 'default' | 'muted' | 'danger' | 'success';
    size?: 'sm' | 'base' | 'lg' | 'xl';
    className?: string;
};

const tones = {
    default: 'text-ink-900',
    muted: 'text-ink-500',
    danger: 'text-danger-600',
    success: 'text-success-700',
} as const;

const sizes = {
    sm: 'text-sm',
    base: 'text-base',
    lg: 'text-lg font-semibold',
    xl: 'text-2xl font-semibold tracking-[-0.02em]',
} as const;

/** Importe con cifras tabulares y símbolo atenuado. Siempre muestra la moneda: nunca se mezclan PEN y USD. */
export function MoneyDisplay({ value, tone = 'default', size = 'base', className }: MoneyDisplayProps) {
    return (
        <span className={cn('numeric inline-flex items-baseline gap-1 whitespace-nowrap', tones[tone], sizes[size], className)}>
            <span className="text-[0.8em] font-normal text-ink-500">{CURRENCY_SYMBOL[value.currency]}</span>
            <span>{formatAmount(value.amount)}</span>
        </span>
    );
}
