import { cn } from '@/lib/cn';
import { getJson } from '@/lib/http';
import { Building2, ChevronsUpDown, LoaderCircle, Search, X } from 'lucide-react';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';

/** Debe coincidir con ClientPresenter::option(). Los datos de contacto se usan en documentos. */
export type ClientOption = {
    id: number;
    name: string;
    document: string | null;
    address?: string | null;
    email?: string | null;
    phone?: string | null;
    contact_name?: string | null;
};

type ClientSelectorProps = {
    id?: string;
    value: ClientOption | null;
    onChange: (client: ClientOption | null) => void;
    invalid?: boolean;
    disabled?: boolean;
    describedBy?: string;
};

/**
 * Combobox de clientes con búsqueda en el servidor (solo activos). Accesible con teclado:
 * flechas para moverse, Enter para elegir, Esc para cerrar.
 */
export function ClientSelector({ id, value, onChange, invalid = false, disabled = false, describedBy }: ClientSelectorProps) {
    const listId = useId();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<ClientOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [highlight, setHighlight] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            getJson<{ data: ClientOption[] }>(route('clients.search', { q: query }), controller.signal)
                .then((response) => {
                    setOptions(response.data);
                    setHighlight(0);
                })
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 200);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, query]);

    // Cerrar al hacer clic fuera.
    useEffect(() => {
        if (!open) return;

        function onPointerDown(event: PointerEvent) {
            if (!containerRef.current?.contains(event.target as Node)) setOpen(false);
        }

        document.addEventListener('pointerdown', onPointerDown);
        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    function select(option: ClientOption) {
        onChange(option);
        setOpen(false);
        setQuery('');
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlight((current) => Math.min(current + 1, options.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlight((current) => Math.max(current - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const option = options[highlight];
            if (option) select(option);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    }

    if (value && !open) {
        return (
            <div
                className={cn(
                    'flex h-11 items-center gap-3 rounded-sm border bg-surface px-3',
                    invalid ? 'border-danger-600' : 'border-line-strong',
                    disabled && 'bg-cream-50',
                )}
            >
                <Building2 className="size-4 shrink-0 text-ink-400" aria-hidden />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-base font-medium text-ink-900">{value.name}</p>
                    {value.document && <p className="numeric truncate text-xs leading-tight text-ink-500">{value.document}</p>}
                </div>
                {!disabled && (
                    <button
                        type="button"
                        onClick={() => {
                            setOpen(true);
                            window.setTimeout(() => inputRef.current?.focus(), 0);
                        }}
                        className="rounded-sm px-2 py-1 text-sm font-medium text-brand-700 transition-colors hover:bg-brand-50"
                    >
                        Cambiar
                    </button>
                )}
            </div>
        );
    }

    return (
        <div ref={containerRef} className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
            <input
                ref={inputRef}
                id={id}
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-invalid={invalid || undefined}
                aria-describedby={describedBy}
                autoComplete="off"
                disabled={disabled}
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
                placeholder="Buscar cliente por nombre o documento"
                className={cn(
                    'h-11 w-full rounded-sm border bg-surface pr-9 pl-9 text-base text-ink-900 transition-colors placeholder:text-ink-400 focus:ring-2 focus:outline-none',
                    invalid ? 'border-danger-600 focus:ring-danger-600/20' : 'border-line-strong hover:border-ink-300 focus:border-brand-600 focus:ring-brand-600/25',
                )}
            />
            {value ? (
                <button
                    type="button"
                    aria-label="Cancelar cambio"
                    onClick={() => setOpen(false)}
                    className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-sm text-ink-400 hover:bg-cream-100"
                >
                    <X className="size-4" />
                </button>
            ) : (
                <ChevronsUpDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
            )}

            {open && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute inset-x-0 top-full z-40 mt-1 max-h-72 overflow-y-auto rounded-md border border-line bg-surface p-1 shadow-overlay animate-pop-in"
                >
                    {loading && options.length === 0 && (
                        <li className="flex items-center gap-2 px-3 py-2.5 text-base text-ink-500">
                            <LoaderCircle className="size-4 animate-spin" aria-hidden /> Buscando…
                        </li>
                    )}
                    {!loading && options.length === 0 && <li className="px-3 py-2.5 text-base text-ink-500">Sin resultados entre los clientes activos.</li>}
                    {options.map((option, index) => (
                        <li
                            key={option.id}
                            role="option"
                            aria-selected={index === highlight}
                            onPointerDown={(event) => event.preventDefault()}
                            onClick={() => select(option)}
                            onMouseEnter={() => setHighlight(index)}
                            className={cn('cursor-pointer rounded-sm px-3 py-2', index === highlight && 'bg-cream-100')}
                        >
                            <p className="truncate text-base text-ink-900">{option.name}</p>
                            {option.document && <p className="numeric text-xs text-ink-500">{option.document}</p>}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
