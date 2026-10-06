import {
    Bell,
    BookOpen,
    CalendarClock,
    FileText,
    LayoutGrid,
    Layers,
    Receipt,
    Settings,
    UserCog,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';

export type NavItem = {
    label: string;
    route: string;
    icon: LucideIcon;
};

export type NavGroup = {
    label: string | null;
    items: NavItem[];
};

export const navigation: NavGroup[] = [
    {
        label: null,
        items: [{ label: 'Resumen', route: 'dashboard', icon: LayoutGrid }],
    },
    {
        label: 'Gestión',
        items: [
            { label: 'Clientes', route: 'clients.index', icon: Users },
            { label: 'Servicios', route: 'contracts.index', icon: Layers },
            { label: 'Cobros', route: 'charges.index', icon: Receipt },
            { label: 'Pagos', route: 'payments.index', icon: Wallet },
            { label: 'Cotizaciones', route: 'quotes.index', icon: FileText },
        ],
    },
    {
        label: 'Control',
        items: [
            { label: 'Vencimientos', route: 'due.index', icon: CalendarClock },
            { label: 'Notificaciones', route: 'notifications.index', icon: Bell },
        ],
    },
    {
        label: 'Sistema',
        items: [
            { label: 'Catálogo', route: 'services.index', icon: BookOpen },
            { label: 'Configuración', route: 'settings.index', icon: Settings },
            { label: 'Usuarios', route: 'users.index', icon: UserCog },
        ],
    },
];

/** Accesos de la barra inferior en móvil: lo que se usa a diario. */
export const mobilePrimary: NavItem[] = [
    { label: 'Resumen', route: 'dashboard', icon: LayoutGrid },
    { label: 'Clientes', route: 'clients.index', icon: Users },
    { label: 'Cobros', route: 'charges.index', icon: Receipt },
    { label: 'Cotizaciones', route: 'quotes.index', icon: FileText },
];

/**
 * Activo si la URL actual (de Inertia) es la sección o una subruta de ella: /clientes/15 activa "Clientes".
 * "Resumen" (/) solo se activa con coincidencia exacta.
 */
export function isActive(item: NavItem, currentUrl: string): boolean {
    const sectionPath = new URL(route(item.route)).pathname;
    const currentPath = currentUrl.split('?')[0] ?? '/';

    if (sectionPath === '/') {
        return currentPath === '/';
    }

    return currentPath === sectionPath || currentPath.startsWith(`${sectionPath}/`);
}
