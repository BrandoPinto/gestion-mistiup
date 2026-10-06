import { IconButton } from '@/components/ui/Button';
import type { PaginationMeta } from '@/types/pagination';
import { ChevronLeft, ChevronRight } from 'lucide-react';

type PaginationProps = {
    meta: PaginationMeta;
    perPageOptions?: number[];
    onPageChange: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
    /** Sustantivo para el resumen: "12 clientes". */
    noun: { singular: string; plural: string };
};

export function Pagination({ meta, perPageOptions = [15, 25, 50], onPageChange, onPerPageChange, noun }: PaginationProps) {
    if (meta.total === 0) {
        return null;
    }

    const label = meta.total === 1 ? noun.singular : noun.plural;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-2.5 text-sm text-ink-500">
            <p className="numeric">
                {meta.from}–{meta.to} de <span className="font-medium text-ink-900">{meta.total}</span> {label}
            </p>

            <div className="flex items-center gap-3">
                {onPerPageChange && (
                    <label className="flex items-center gap-2 max-sm:hidden">
                        Mostrar
                        <select
                            value={meta.per_page}
                            onChange={(event) => onPerPageChange(Number(event.target.value))}
                            className="h-8 rounded-sm border border-line-strong bg-surface px-2 text-sm text-ink-900 focus:border-brand-600 focus:outline-none"
                        >
                            {perPageOptions.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                <div className="flex items-center gap-1">
                    <IconButton label="Página anterior" variant="secondary" className="size-8" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)}>
                        <ChevronLeft />
                    </IconButton>
                    <span className="numeric min-w-16 text-center">
                        {meta.current_page} / {meta.last_page}
                    </span>
                    <IconButton
                        label="Página siguiente"
                        variant="secondary"
                        className="size-8"
                        disabled={meta.current_page >= meta.last_page}
                        onClick={() => onPageChange(meta.current_page + 1)}
                    >
                        <ChevronRight />
                    </IconButton>
                </div>
            </div>
        </div>
    );
}
