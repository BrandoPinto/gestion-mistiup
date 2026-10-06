import { cn } from '@/lib/cn';
import { forwardRef, useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';

const controlBase =
    'w-full rounded-sm border bg-surface px-3 text-base text-ink-900 transition-colors placeholder:text-ink-400 ' +
    'focus:outline-none focus:ring-2 focus:ring-brand-600/25 focus:border-brand-600 ' +
    'disabled:bg-cream-50 disabled:text-ink-500 disabled:cursor-not-allowed';

function controlClasses(invalid: boolean, className?: string): string {
    return cn(
        controlBase,
        invalid ? 'border-danger-600 focus:border-danger-600 focus:ring-danger-600/20' : 'border-line-strong hover:border-ink-300',
        className,
    );
}

type FieldProps = {
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    /** Si se omite se genera uno; se pasa al control mediante render-prop. */
    id?: string;
    className?: string;
    children: (props: { id: string; invalid: boolean; describedBy?: string }) => ReactNode;
};

/** Etiqueta + control + ayuda/error con los atributos de accesibilidad conectados. */
export function Field({ label, error, hint, required, id, className, children }: FieldProps) {
    const generatedId = useId();
    const controlId = id ?? generatedId;
    const messageId = `${controlId}-message`;
    const message = error ?? hint;

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <label htmlFor={controlId} className="text-sm font-medium text-ink-700">
                {label}
                {required && <span className="ml-0.5 text-danger-600" aria-hidden>*</span>}
            </label>
            {children({ id: controlId, invalid: Boolean(error), describedBy: message ? messageId : undefined })}
            {message && (
                <p id={messageId} className={cn('text-xs', error ? 'text-danger-600' : 'text-ink-500')}>
                    {message}
                </p>
            )}
        </div>
    );
}

type InputProps = InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean };

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input({ invalid = false, className, ...props }, ref) {
    return <input ref={ref} aria-invalid={invalid || undefined} className={controlClasses(invalid, cn('h-9 max-sm:h-11', className))} {...props} />;
});

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & { invalid?: boolean };

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea({ invalid = false, className, rows = 3, ...props }, ref) {
    return <textarea ref={ref} rows={rows} aria-invalid={invalid || undefined} className={controlClasses(invalid, cn('py-2', className))} {...props} />;
});

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean };

/** Select nativo estilizado: en móvil abre el selector del sistema, que es el más cómodo. */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select({ invalid = false, className, children, ...props }, ref) {
    return (
        <select
            ref={ref}
            aria-invalid={invalid || undefined}
            className={controlClasses(
                invalid,
                cn(
                    'h-9 appearance-none bg-[length:16px] bg-[right_0.6rem_center] bg-no-repeat pr-9 max-sm:h-11',
                    "bg-[url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235b6278' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E\")]",
                    className,
                ),
            )}
            {...props}
        >
            {children}
        </select>
    );
});

type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { label: string };

export function Checkbox({ label, className, id, ...props }: CheckboxProps) {
    const generatedId = useId();
    const controlId = id ?? generatedId;

    return (
        <label htmlFor={controlId} className={cn('inline-flex cursor-pointer items-center gap-2 text-base text-ink-700 select-none', className)}>
            <input id={controlId} type="checkbox" className="size-4 rounded-sm border-line-strong accent-brand-600" {...props} />
            {label}
        </label>
    );
}
