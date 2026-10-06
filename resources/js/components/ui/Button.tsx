import { cn } from '@/lib/cn';
import { LoaderCircle } from 'lucide-react';
import type { ButtonHTMLAttributes } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md';

const base =
    'inline-flex items-center justify-center gap-2 rounded-sm font-medium whitespace-nowrap transition-colors select-none ' +
    'disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0';

const variants: Record<ButtonVariant, string> = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800',
    secondary: 'border border-line-strong bg-surface text-ink-900 hover:bg-cream-50 hover:border-ink-300',
    ghost: 'text-ink-700 hover:bg-cream-100 hover:text-ink-900',
    danger: 'bg-danger-600 text-white hover:bg-danger-700',
};

const sizes: Record<ButtonSize, string> = {
    sm: 'h-8 px-3 text-sm',
    md: 'h-9 px-3.5 text-base max-sm:h-11',
};

/** Clases de botón para usar en <Link> u otros elementos que deban verse como botón. */
export function buttonClasses(variant: ButtonVariant = 'primary', size: ButtonSize = 'md', className?: string): string {
    return cn(base, variants[variant], sizes[size], className);
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
};

export function Button({ variant = 'primary', size = 'md', loading = false, disabled, className, children, type = 'button', ...props }: ButtonProps) {
    return (
        <button
            type={type}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            className={buttonClasses(variant, size, className)}
            {...props}
        >
            {loading && <LoaderCircle className="animate-spin" aria-hidden />}
            {children}
        </button>
    );
}

type IconButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    label: string;
    variant?: 'ghost' | 'secondary';
};

/** Botón cuadrado solo-ícono. `label` es obligatorio por accesibilidad. */
export function IconButton({ label, variant = 'ghost', className, children, type = 'button', ...props }: IconButtonProps) {
    return (
        <button
            type={type}
            aria-label={label}
            title={label}
            className={cn(
                'inline-flex size-9 items-center justify-center rounded-sm transition-colors max-sm:size-11 [&_svg]:size-[18px]',
                'disabled:pointer-events-none disabled:opacity-50',
                variants[variant],
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}
