import { FlashToaster } from '@/components/layout/FlashToaster';
import { MobileDrawer, MobileTabBar } from '@/components/layout/MobileNav';
import { NotificationBell } from '@/components/layout/NotificationBell';
import { Sidebar } from '@/components/layout/Sidebar';
import { UserMenu } from '@/components/layout/UserMenu';
import { IconButton } from '@/components/ui/Button';
import { useLocalPreference } from '@/hooks/useLocalPreference';
import { cn } from '@/lib/cn';
import { Menu } from 'lucide-react';
import { useState, type ReactNode } from 'react';

export function AppLayout({ children }: { children: ReactNode }) {
    const [collapsed, setCollapsed] = useLocalPreference('sidebar.collapsed', false);
    const [drawerOpen, setDrawerOpen] = useState(false);

    return (
        <div className="min-h-dvh">
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-30 hidden transition-[width] duration-200 lg:block',
                    collapsed ? 'w-16' : 'w-60',
                )}
            >
                <Sidebar collapsed={collapsed} onToggleCollapsed={() => setCollapsed(!collapsed)} />
            </aside>

            <MobileDrawer open={drawerOpen} onOpenChange={setDrawerOpen} />

            <div className={cn('flex min-h-dvh flex-col transition-[padding] duration-200', collapsed ? 'lg:pl-16' : 'lg:pl-60')}>
                <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-line bg-canvas/95 px-4 backdrop-blur-sm sm:px-6">
                    <IconButton label="Abrir menú" className="-ml-2 lg:hidden" onClick={() => setDrawerOpen(true)}>
                        <Menu />
                    </IconButton>
                    <div className="flex-1" />
                    <NotificationBell />
                    <UserMenu />
                </header>

                <main className="mx-auto w-full max-w-[1400px] flex-1 px-4 py-6 pb-24 sm:px-6 sm:pb-10 lg:px-8">{children}</main>
            </div>

            <MobileTabBar onOpenMenu={() => setDrawerOpen(true)} />
            <FlashToaster />
        </div>
    );
}
