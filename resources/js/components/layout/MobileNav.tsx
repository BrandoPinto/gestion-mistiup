import { cn } from '@/lib/cn';
import * as RadixDialog from '@radix-ui/react-dialog';
import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { isActive, mobilePrimary } from './navigation';
import { Sidebar } from './Sidebar';

type MobileDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Cajón lateral con la navegación completa (tablet y móvil). */
export function MobileDrawer({ open, onOpenChange }: MobileDrawerProps) {
    return (
        <RadixDialog.Root open={open} onOpenChange={onOpenChange}>
            <RadixDialog.Portal>
                <RadixDialog.Overlay className="fixed inset-0 z-50 bg-navy-950/50 data-[state=open]:animate-fade-in lg:hidden" />
                <RadixDialog.Content className="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] focus:outline-none data-[state=open]:animate-fade-in lg:hidden">
                    <RadixDialog.Title className="sr-only">Menú</RadixDialog.Title>
                    <RadixDialog.Description className="sr-only">Navegación principal</RadixDialog.Description>
                    <Sidebar collapsed={false} onNavigate={() => onOpenChange(false)} />
                </RadixDialog.Content>
            </RadixDialog.Portal>
        </RadixDialog.Root>
    );
}

/** Barra inferior en teléfonos: accesos diarios al alcance del pulgar + "Más" abre el cajón. */
export function MobileTabBar({ onOpenMenu }: { onOpenMenu: () => void }) {
    const { url } = usePage();

    return (
        <nav
            aria-label="Accesos rápidos"
            className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] sm:hidden"
        >
            {mobilePrimary.map((item) => {
                const active = isActive(item, url);
                const Icon = item.icon;

                return (
                    <Link
                        key={item.route}
                        href={route(item.route)}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'flex h-14 flex-col items-center justify-center gap-1 text-2xs font-medium transition-colors',
                            active ? 'text-brand-700' : 'text-ink-500',
                        )}
                    >
                        <Icon className="size-5" aria-hidden />
                        {item.label}
                    </Link>
                );
            })}
            <button type="button" onClick={onOpenMenu} className="flex h-14 flex-col items-center justify-center gap-1 text-2xs font-medium text-ink-500">
                <Menu className="size-5" aria-hidden />
                Más
            </button>
        </nav>
    );
}
