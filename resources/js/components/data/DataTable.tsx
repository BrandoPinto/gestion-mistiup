import { EmptyState } from '@/components/ui/EmptyState';
import { Skeleton } from '@/components/ui/Skeleton';
import { TBody, Td, Th, THead, Tr } from '@/components/ui/Table';
import { cn } from '@/lib/cn';
import type { SortDirection } from '@/types/pagination';
import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, type LucideIcon } from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';

export type DataTableColumn<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    align?: 'left' | 'right' | 'center';
    numeric?: boolean;
    /** Si se indica, la cabecera ordena por este campo del servidor. */
    sortKey?: string;
    /** Clases para ocultar columnas secundarias en pantallas chicas, p. ej. 'max-md:hidden'. */
    className?: string;
    headerClassName?: string;
};

type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /** Toda la fila navega a este destino (los clics en botones/enlaces internos no lo activan). */
    rowHref?: (row: T) => string;
    sort?: { sort: string; direction: SortDirection };
    onSort?: (sortKey: string, direction: SortDirection) => void;
    loading?: boolean;
    empty: { icon?: LucideIcon; title: string; description?: string; action?: ReactNode };
};

/**
 * Tabla administrativa: orden del lado del servidor, fila clicable, columnas responsivas.
 * En móvil no se transforma en tarjetas: se ocultan columnas secundarias y se conserva la lectura en filas.
 */
export function DataTable<T>({ columns, rows, rowKey, rowHref, sort, onSort, loading = false, empty }: DataTableProps<T>) {
    function handleRowClick(event: MouseEvent<HTMLTableRowElement>, row: T) {
        if (!rowHref) return;
        const target = event.target as HTMLElement;
        if (target.closest('a, button, [role="menuitem"], input, label')) return;

        const href = rowHref(row);
        if (event.metaKey || event.ctrlKey) {
            window.open(href, '_blank');
            return;
        }
        router.visit(href);
    }

    if (!loading && rows.length === 0) {
        return <EmptyState {...empty} />;
    }

    return (
        <div className="overflow-x-auto">
            <table className={cn('w-full border-collapse text-base transition-opacity', loading && rows.length > 0 && 'opacity-60')}>
                <THead>
                    <tr>
                        {columns.map((column) => (
                            <Th key={column.key} align={column.align ?? (column.numeric ? 'right' : 'left')} className={cn(column.className, column.headerClassName)}>
                                {column.sortKey && onSort ? (
                                    <SortButton column={column} sort={sort} onSort={onSort} />
                                ) : (
                                    column.header
                                )}
                            </Th>
                        ))}
                    </tr>
                </THead>
                <TBody>
                    {rows.length === 0
                        ? Array.from({ length: 6 }, (_, index) => (
                              <tr key={index}>
                                  {columns.map((column) => (
                                      <Td key={column.key} className={column.className}>
                                          <Skeleton className="h-4 w-3/4" />
                                      </Td>
                                  ))}
                              </tr>
                          ))
                        : rows.map((row) => (
                              <Tr
                                  key={rowKey(row)}
                                  onClick={(event) => handleRowClick(event, row)}
                                  className={cn(rowHref && 'cursor-pointer')}
                              >
                                  {columns.map((column) => (
                                      <Td key={column.key} align={column.align} numeric={column.numeric} className={column.className}>
                                          {column.cell(row)}
                                      </Td>
                                  ))}
                              </Tr>
                          ))}
                </TBody>
            </table>
        </div>
    );
}

type SortButtonProps<T> = {
    column: DataTableColumn<T>;
    sort?: { sort: string; direction: SortDirection };
    onSort: (sortKey: string, direction: SortDirection) => void;
};

function SortButton<T>({ column, sort, onSort }: SortButtonProps<T>) {
    const active = sort?.sort === column.sortKey;
    const direction = active ? sort?.direction : undefined;
    const Icon = direction === 'asc' ? ArrowUp : direction === 'desc' ? ArrowDown : ArrowUpDown;

    return (
        <button
            type="button"
            onClick={() => onSort(column.sortKey!, active && direction === 'asc' ? 'desc' : 'asc')}
            className={cn('-mx-1 inline-flex items-center gap-1 rounded-sm px-1 py-0.5 uppercase transition-colors hover:text-ink-900', active && 'text-ink-900')}
            aria-sort={direction === 'asc' ? 'ascending' : direction === 'desc' ? 'descending' : undefined}
        >
            {column.header}
            <Icon className={cn('size-3', !active && 'opacity-50')} aria-hidden />
        </button>
    );
}
