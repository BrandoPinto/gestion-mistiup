import * as RadixTooltip from '@radix-ui/react-tooltip';
import type { ReactNode } from 'react';

type TooltipProps = {
    content: ReactNode;
    side?: 'top' | 'right' | 'bottom' | 'left';
    /** Permite desactivar el tooltip sin cambiar el árbol (p. ej. sidebar expandido). */
    disabled?: boolean;
    children: ReactNode;
};

/** Requiere <TooltipProvider> en la raíz (ya incluido en app.tsx). */
export function Tooltip({ content, side = 'top', disabled = false, children }: TooltipProps) {
    if (disabled) {
        return <>{children}</>;
    }

    return (
        <RadixTooltip.Root>
            <RadixTooltip.Trigger asChild>{children}</RadixTooltip.Trigger>
            <RadixTooltip.Portal>
                <RadixTooltip.Content
                    side={side}
                    sideOffset={6}
                    className="z-50 rounded-sm bg-navy-900 px-2 py-1 text-xs font-medium text-cream-100 data-[state=delayed-open]:animate-fade-in"
                >
                    {content}
                </RadixTooltip.Content>
            </RadixTooltip.Portal>
        </RadixTooltip.Root>
    );
}

export const TooltipProvider = RadixTooltip.Provider;
