import { LogoMark } from '@/components/brand/LogoMark';
import { Tooltip } from '@/components/ui/Tooltip';
import { cn } from '@/lib/cn';
import { Link, usePage } from '@inertiajs/react';
import { PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { isActive, navigation, type NavItem } from './navigation';

type SidebarProps = {
    collapsed: boolean;
    onToggleCollapsed?: () => void;
    /** Al navegar desde el cajón móvil, cerrarlo. */
    onNavigate?: () => void;
};

export function Sidebar({ collapsed, onToggleCollapsed, onNavigate }: SidebarProps) {
    const { props, url } = usePage();
    const { app } = props;

    return (
        <nav aria-label="Principal" className="flex h-full flex-col bg-navy-900 text-cream-100">
            <div className={cn('flex h-14 shrink-0 items-center gap-2.5 border-b border-navy-800', collapsed ? 'justify-center px-0' : 'px-4')}>
                <LogoMark className="h-6 w-auto shrink-0" title={app.name} />
                {!collapsed && <span className="truncate text-md font-semibold tracking-[-0.01em] text-cream-50">{app.name}</span>}
            </div>

            <div className="flex-1 overflow-y-auto py-3">
                {navigation.map((group, index) => (
                    <div key={group.label ?? index} className={cn(index > 0 && 'mt-5')}>
                        {group.label &&
                            (collapsed ? (
                                <div className="mx-auto mb-2 h-px w-6 bg-navy-700" aria-hidden />
                            ) : (
                                <p className="mb-1.5 px-5 text-2xs font-semibold tracking-[0.1em] text-navy-400 uppercase">{group.label}</p>
                            ))}
                        <ul className="flex flex-col gap-0.5 px-2">
                            {group.items.map((item) => (
                                <li key={item.route}>
                                    <SidebarLink item={item} active={isActive(item, url)} collapsed={collapsed} onNavigate={onNavigate} />
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>

            {onToggleCollapsed && (
                <div className="shrink-0 border-t border-navy-800 p-2">
                    <button
                        type="button"
                        onClick={onToggleCollapsed}
                        className={cn(
                            'flex h-9 w-full items-center gap-3 rounded-sm px-3 text-sm text-navy-400 transition-colors hover:bg-navy-800 hover:text-cream-100',
                            collapsed && 'justify-center px-0',
                        )}
                        aria-label={collapsed ? 'Expandir menú' : 'Contraer menú'}
                    >
                        {collapsed ? <PanelLeftOpen className="size-[18px]" /> : <PanelLeftClose className="size-[18px]" />}
                        {!collapsed && 'Contraer'}
                    </button>
                </div>
            )}
        </nav>
    );
}

type SidebarLinkProps = {
    item: NavItem;
    active: boolean;
    collapsed: boolean;
    onNavigate?: () => void;
};

function SidebarLink({ item, active, collapsed, onNavigate }: SidebarLinkProps) {
    const Icon = item.icon;

    return (
        <Tooltip content={item.label} side="right" disabled={!collapsed}>
            <Link
                href={route(item.route)}
                onClick={onNavigate}
                aria-current={active ? 'page' : undefined}
                className={cn(
                    'group relative flex h-9 items-center gap-3 rounded-sm text-base transition-colors max-lg:h-11',
                    collapsed ? 'justify-center' : 'px-3',
                    active ? 'bg-navy-800 font-medium text-cream-50' : 'text-cream-200/75 hover:bg-navy-800/60 hover:text-cream-50',
                )}
            >
                {/* Indicador de marca: barra azul eléctrico a la izquierda del ítem activo. */}
                <span
                    aria-hidden
                    className={cn(
                        'absolute top-1.5 bottom-1.5 left-0 w-[3px] rounded-r-sm bg-brand-600 transition-opacity',
                        active ? 'opacity-100' : 'opacity-0',
                    )}
                />
                <Icon className={cn('size-[18px] shrink-0', active ? 'text-brand-500' : 'text-navy-400 group-hover:text-cream-200')} aria-hidden />
                {!collapsed && <span className="truncate">{item.label}</span>}
                {collapsed && <span className="sr-only">{item.label}</span>}
            </Link>
        </Tooltip>
    );
}
