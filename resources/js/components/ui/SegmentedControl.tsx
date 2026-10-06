import { cn } from '@/lib/cn';

type Option<T extends string> = { value: T; label: string };

type SegmentedControlProps<T extends string> = {
    value: T;
    options: Option<T>[];
    onChange: (value: T) => void;
    label: string;
    className?: string;
};

/** Selección exclusiva entre 2–4 opciones cortas (radio group accesible). */
export function SegmentedControl<T extends string>({ value, options, onChange, label, className }: SegmentedControlProps<T>) {
    return (
        <div role="radiogroup" aria-label={label} className={cn('inline-flex rounded-sm border border-line-strong bg-cream-50 p-0.5', className)}>
            {options.map((option) => {
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'h-8 flex-1 rounded-[3px] px-3 text-base transition-colors max-sm:h-10',
                            selected ? 'bg-surface font-medium text-ink-900 shadow-subtle' : 'text-ink-500 hover:text-ink-900',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
