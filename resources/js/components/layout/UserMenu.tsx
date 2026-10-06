import { DropdownItem, DropdownMenu, DropdownSeparator } from '@/components/ui/DropdownMenu';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, Settings, UserRound } from 'lucide-react';

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export function UserMenu() {
    const { auth } = usePage().props;
    const user = auth.user;

    if (!user) {
        return null;
    }

    return (
        <DropdownMenu
            width="w-60"
            trigger={
                <button
                    type="button"
                    className="flex h-9 items-center gap-2 rounded-sm pr-1.5 pl-1 transition-colors hover:bg-cream-100 max-sm:h-11"
                    aria-label="Menú de usuario"
                >
                    <span className="flex size-7 items-center justify-center rounded-sm bg-navy-900 text-xs font-semibold text-cream-100">
                        {initials(user.name)}
                    </span>
                    <span className="hidden max-w-36 truncate text-base font-medium text-ink-900 md:block">{user.name}</span>
                    <ChevronDown className="hidden size-4 text-ink-500 md:block" aria-hidden />
                </button>
            }
        >
            <div className="px-2.5 py-2">
                <p className="truncate text-base font-medium text-ink-900">{user.name}</p>
                <p className="truncate text-sm text-ink-500">{user.email}</p>
            </div>
            <DropdownSeparator />
            <DropdownItem icon={UserRound} onSelect={() => router.visit(route('account.edit'))}>
                Mi cuenta
            </DropdownItem>
            <DropdownItem icon={Settings} onSelect={() => router.visit(route('settings.index'))}>
                Configuración
            </DropdownItem>
            <DropdownSeparator />
            <DropdownItem icon={LogOut} onSelect={() => router.post(route('logout'))}>
                Cerrar sesión
            </DropdownItem>
        </DropdownMenu>
    );
}
