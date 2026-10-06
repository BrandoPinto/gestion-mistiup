import { cn } from '@/lib/cn';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type EmptyStateProps = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

/** Estado vacío sobrio: ícono pequeño, texto y una acción. Sin ilustraciones. */
export function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn('flex flex-col items-center px-6 py-12 text-center', className)}>
            {Icon && (
                <span className="mb-3 flex size-10 items-center justify-center rounded-md border border-line bg-cream-50 text-ink-500">
                    <Icon className="size-5" aria-hidden />
                </span>
            )}
            <p className="text-md font-medium text-ink-900">{title}</p>
            {description && <p className="mt-1 max-w-sm text-base text-ink-500">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
