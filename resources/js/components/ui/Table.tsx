import { cn } from '@/lib/cn';
import type { HTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react';

/**
 * Primitivas de tabla administrativa. El DataTable con orden/filtros/paginación (Fase 2) se construye sobre estas.
 * La tabla vive dentro de un contenedor con scroll horizontal: en móvil no se convierte en tarjetas.
 */

export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
    return (
        <div className="overflow-x-auto">
            <table className={cn('w-full border-collapse text-base', className)} {...props} />
        </div>
    );
}

export function THead(props: HTMLAttributes<HTMLTableSectionElement>) {
    return <thead className="sticky top-0 z-[1] bg-cream-50" {...props} />;
}

export function TBody(props: HTMLAttributes<HTMLTableSectionElement>) {
    return <tbody className="divide-y divide-line" {...props} />;
}

type ThProps = ThHTMLAttributes<HTMLTableCellElement> & {
    align?: 'left' | 'right' | 'center';
};

export function Th({ align = 'left', className, ...props }: ThProps) {
    return (
        <th
            scope="col"
            className={cn(
                'h-10 border-b border-line px-3 eyebrow sm:px-4 whitespace-nowrap',
                align === 'right' && 'text-right',
                align === 'center' && 'text-center',
                align === 'left' && 'text-left',
                className,
            )}
            {...props}
        />
    );
}

type TdProps = TdHTMLAttributes<HTMLTableCellElement> & {
    align?: 'left' | 'right' | 'center';
    numeric?: boolean;
};

export function Td({ align, numeric = false, className, ...props }: TdProps) {
    const resolvedAlign = align ?? (numeric ? 'right' : 'left');

    return (
        <td
            className={cn(
                'h-12 px-3 py-2.5 align-middle sm:px-4 whitespace-nowrap text-ink-700',
                resolvedAlign === 'right' && 'text-right',
                resolvedAlign === 'center' && 'text-center',
                numeric && 'numeric',
                className,
            )}
            {...props}
        />
    );
}

export function Tr({ className, ...props }: HTMLAttributes<HTMLTableRowElement>) {
    return <tr className={cn('transition-colors hover:bg-cream-50', className)} {...props} />;
}
