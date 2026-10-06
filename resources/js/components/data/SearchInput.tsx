import { cn } from '@/lib/cn';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type SearchInputProps = {
    value: string;
    onSearch: (value: string) => void;
    placeholder?: string;
    className?: string;
    /** Espera antes de buscar mientras se escribe. */
    debounceMs?: number;
};

export function SearchInput({ value, onSearch, placeholder = 'Buscar…', className, debounceMs = 300 }: SearchInputProps) {
    const [text, setText] = useState(value);
    const lastSent = useRef(value);

    useEffect(() => {
        if (text === lastSent.current) return;

        const timer = window.setTimeout(() => {
            lastSent.current = text;
            onSearch(text.trim());
        }, debounceMs);

        return () => window.clearTimeout(timer);
    }, [text, debounceMs, onSearch]);

    function clear() {
        setText('');
        lastSent.current = '';
        onSearch('');
    }

    return (
        <div className={cn('relative', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
            <input
                type="search"
                value={text}
                onChange={(event) => setText(event.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
                className="h-9 w-full rounded-sm border border-line-strong bg-surface pr-9 pl-9 text-base text-ink-900 transition-colors placeholder:text-ink-400 hover:border-ink-300 focus:border-brand-600 focus:ring-2 focus:ring-brand-600/25 focus:outline-none max-sm:h-11 [&::-webkit-search-cancel-button]:hidden"
            />
            {text && (
                <button
                    type="button"
                    onClick={clear}
                    aria-label="Limpiar búsqueda"
                    className="absolute top-1/2 right-1.5 flex size-7 -translate-y-1/2 items-center justify-center rounded-sm text-ink-400 transition-colors hover:bg-cream-100 hover:text-ink-900"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
