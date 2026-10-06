import { cn } from '@/lib/cn';
import { daysBetween, formatDate, formatDateShort, todayInBusinessZone } from '@/lib/dates';

type DateDisplayProps = {
    /** Fecha de negocio "YYYY-MM-DD". */
    value: string;
    format?: 'numeric' | 'short';
    /** Muestra debajo "en 5 días" / "hace 3 días" / "hoy". */
    relative?: boolean;
    className?: string;
};

function relativeLabel(days: number): string {
    if (days === 0) return 'hoy';
    if (days === 1) return 'mañana';
    if (days === -1) return 'ayer';
    return days > 0 ? `en ${days} días` : `hace ${Math.abs(days)} días`;
}

export function DateDisplay({ value, format = 'numeric', relative = false, className }: DateDisplayProps) {
    const text = format === 'short' ? formatDateShort(value) : formatDate(value);

    if (!relative) {
        return (
            <time dateTime={value} className={cn('numeric whitespace-nowrap', className)}>
                {text}
            </time>
        );
    }

    return (
        <span className={cn('inline-flex flex-col leading-tight', className)}>
            <time dateTime={value} className="numeric whitespace-nowrap">
                {text}
            </time>
            <span className="text-xs text-ink-500">{relativeLabel(daysBetween(todayInBusinessZone(), value))}</span>
        </span>
    );
}
