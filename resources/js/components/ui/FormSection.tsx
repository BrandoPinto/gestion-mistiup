import { cn } from '@/lib/cn';
import type { ReactNode } from 'react';

type FormSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    /** Grilla de 2 columnas para los campos (por defecto) o una sola. */
    columns?: 1 | 2;
};

/** Sección editorial de formulario: título y explicación a la izquierda, campos a la derecha. */
export function FormSection({ title, description, children, columns = 2 }: FormSectionProps) {
    return (
        <section className="grid gap-x-10 gap-y-4 border-b border-line py-6 first:pt-0 lg:grid-cols-[220px_minmax(0,1fr)]">
            <div>
                <h2 className="text-md font-semibold text-ink-900">{title}</h2>
                {description && <p className="mt-1 text-sm text-ink-500">{description}</p>}
            </div>
            <div className={cn('grid max-w-2xl gap-4', columns === 2 && 'sm:grid-cols-2')}>{children}</div>
        </section>
    );
}
