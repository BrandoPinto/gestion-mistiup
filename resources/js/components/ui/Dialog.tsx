import { cn } from '@/lib/cn';
import * as RadixDialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';

type DialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    size?: 'sm' | 'md' | 'lg';
    footer?: ReactNode;
    children?: ReactNode;
};

const widths = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
} as const;

/**
 * Modal accesible (foco atrapado, Esc, aria). En móvil se ancla abajo como hoja para alcanzar los botones con el pulgar.
 */
export function Dialog({ open, onOpenChange, title, description, size = 'md', footer, children }: DialogProps) {
    return (
        <RadixDialog.Root open={open} onOpenChange={onOpenChange}>
            <RadixDialog.Portal>
                <RadixDialog.Overlay className="fixed inset-0 z-50 bg-navy-950/40 data-[state=open]:animate-fade-in" />
                <RadixDialog.Content
                    className={cn(
                        'fixed z-50 flex max-h-[90dvh] w-full flex-col bg-surface shadow-overlay focus:outline-none',
                        'inset-x-0 bottom-0 rounded-t-lg',
                        'sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-lg',
                        'data-[state=open]:animate-pop-in',
                        widths[size],
                    )}
                >
                    <header className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                        <div>
                            <RadixDialog.Title className="text-md font-semibold text-ink-900">{title}</RadixDialog.Title>
                            {description ? (
                                <RadixDialog.Description className="mt-1 text-base text-ink-500">{description}</RadixDialog.Description>
                            ) : (
                                <RadixDialog.Description className="sr-only">{title}</RadixDialog.Description>
                            )}
                        </div>
                        <RadixDialog.Close
                            className="-mt-1 -mr-2 inline-flex size-8 items-center justify-center rounded-sm text-ink-500 transition-colors hover:bg-cream-100 hover:text-ink-900"
                            aria-label="Cerrar"
                        >
                            <X className="size-4" />
                        </RadixDialog.Close>
                    </header>
                    {children && <div className="overflow-y-auto px-5 py-4">{children}</div>}
                    {footer && (
                        <footer className="flex flex-col-reverse gap-2 border-t border-line bg-cream-50 px-5 py-3 sm:flex-row sm:justify-end">
                            {footer}
                        </footer>
                    )}
                </RadixDialog.Content>
            </RadixDialog.Portal>
        </RadixDialog.Root>
    );
}
