type LogoMarkProps = {
    /** `dark`: sobre azul noche (pieza izquierda crema). `light`: sobre fondos claros (pieza izquierda azul noche). */
    surface?: 'dark' | 'light';
    className?: string;
    title?: string;
};

/** Isotipo vectorizado a partir del logo original (resources/brand/logo-original.jpg). */
export function LogoMark({ surface = 'dark', className, title }: LogoMarkProps) {
    const leftFill = surface === 'dark' ? 'var(--color-cream-100)' : 'var(--color-navy-900)';

    return (
        <svg
            viewBox="235 300 655 490"
            className={className}
            role={title ? 'img' : undefined}
            aria-hidden={title ? undefined : true}
            aria-label={title}
        >
            <polygon fill={leftFill} points="450,300 527,475 420,605 505,605 430,790 235,790" />
            <polygon fill="var(--color-brand-600)" points="505,605 505,790 430,790" />
            <polygon fill="var(--color-brand-600)" points="630,300 890,790 592,790 592,605 677,605 563,466" />
        </svg>
    );
}
