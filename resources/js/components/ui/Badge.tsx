import { cn } from '@/lib/cn';
import type { ReactNode } from 'react';

export type BadgeTone = 'neutral' | 'brand' | 'success' | 'warning' | 'danger';

const tones: Record<BadgeTone, { box: string; dot: string }> = {
    neutral: { box: 'bg-cream-100 text-ink-700', dot: 'bg-ink-400' },
    brand: { box: 'bg-brand-50 text-brand-700', dot: 'bg-brand-600' },
    success: { box: 'bg-success-50 text-success-700', dot: 'bg-success-600' },
    warning: { box: 'bg-warning-50 text-warning-700', dot: 'bg-warning-600' },
    danger: { box: 'bg-danger-50 text-danger-700', dot: 'bg-danger-600' },
};

type BadgeProps = {
    tone?: BadgeTone;
    /** Punto de color: útil para estados. Sin él, el badge es una etiqueta simple. */
    dot?: boolean;
    className?: string;
    children: ReactNode;
};

export function Badge({ tone = 'neutral', dot = true, className, children }: BadgeProps) {
    const style = tones[tone];

    return (
        <span className={cn('inline-flex h-[22px] items-center gap-1.5 rounded-md px-2 text-xs font-medium whitespace-nowrap', style.box, className)}>
            {dot && <span className={cn('size-1.5 rounded-full', style.dot)} aria-hidden />}
            {children}
        </span>
    );
}
