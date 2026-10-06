import { cn } from '@/lib/cn';
import { Building2, User } from 'lucide-react';
import type { ClientType } from './types';

type ClientIdentityProps = {
    name: string;
    type: ClientType;
    secondary?: string | null;
    size?: 'sm' | 'lg';
};

/** Nombre del cliente con su marca de tipo (empresa/persona) y una línea secundaria (documento o nombre comercial). */
export function ClientIdentity({ name, type, secondary, size = 'sm' }: ClientIdentityProps) {
    const Icon = type === 'company' ? Building2 : User;

    return (
        <div className="flex min-w-0 items-center gap-3">
            <span
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-md border border-line bg-cream-50 text-ink-500',
                    size === 'lg' ? 'size-12 [&_svg]:size-6' : 'size-8 [&_svg]:size-4',
                )}
                aria-hidden
            >
                <Icon />
            </span>
            <div className="min-w-0">
                <p className={cn('font-medium text-ink-900', size === 'lg' ? 'text-xl leading-tight font-semibold tracking-[-0.01em] break-words' : 'truncate')}>{name}</p>
                {secondary && <p className={cn('numeric truncate text-ink-500', size === 'lg' ? 'text-base' : 'text-xs')}>{secondary}</p>}
            </div>
        </div>
    );
}
