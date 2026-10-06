import { cn } from '@/lib/cn';
import * as RadixMenu from '@radix-ui/react-dropdown-menu';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type DropdownMenuProps = {
    trigger: ReactNode;
    align?: 'start' | 'end';
    width?: string;
    children: ReactNode;
};

export function DropdownMenu({ trigger, align = 'end', width = 'w-52', children }: DropdownMenuProps) {
    return (
        <RadixMenu.Root>
            <RadixMenu.Trigger asChild>{trigger}</RadixMenu.Trigger>
            <RadixMenu.Portal>
                <RadixMenu.Content
                    align={align}
                    sideOffset={6}
                    className={cn(
                        'z-50 rounded-md border border-line bg-surface p-1 shadow-overlay data-[state=open]:animate-pop-in',
                        width,
                    )}
                >
                    {children}
                </RadixMenu.Content>
            </RadixMenu.Portal>
        </RadixMenu.Root>
    );
}

type DropdownItemProps = {
    icon?: LucideIcon;
    tone?: 'default' | 'danger';
    onSelect?: () => void;
    disabled?: boolean;
    children: ReactNode;
};

export function DropdownItem({ icon: Icon, tone = 'default', onSelect, disabled, children }: DropdownItemProps) {
    return (
        <RadixMenu.Item
            onSelect={onSelect}
            disabled={disabled}
            className={cn(
                'flex h-9 cursor-default items-center gap-2.5 rounded-sm px-2.5 text-base outline-none select-none max-sm:h-11',
                'data-[disabled]:opacity-50',
                tone === 'danger'
                    ? 'text-danger-600 data-[highlighted]:bg-danger-50'
                    : 'text-ink-700 data-[highlighted]:bg-cream-100 data-[highlighted]:text-ink-900',
            )}
        >
            {Icon && <Icon className="size-4 shrink-0" aria-hidden />}
            {children}
        </RadixMenu.Item>
    );
}

export function DropdownSeparator() {
    return <RadixMenu.Separator className="my-1 h-px bg-line" />;
}

export function DropdownLabel({ children }: { children: ReactNode }) {
    return <RadixMenu.Label className="px-2.5 pt-2 pb-1 eyebrow">{children}</RadixMenu.Label>;
}
