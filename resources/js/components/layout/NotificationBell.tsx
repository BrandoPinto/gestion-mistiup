import { IconButton } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { NotificationRow } from '@/features/notifications/NotificationRow';
import type { NotificationItem } from '@/features/notifications/types';
import { getJson } from '@/lib/http';
import * as RadixPopover from '@radix-ui/react-popover';
import { Link, router, usePage, usePoll } from '@inertiajs/react';
import { Bell, BellOff } from 'lucide-react';
import { useState } from 'react';

const POLL_MS = 60_000;

/**
 * Campana: contador de no leídas (prop compartida, refrescada cada minuto con una recarga parcial
 * liviana; sin WebSockets) y lista de las últimas notificaciones, cargada al abrir.
 */
export function NotificationBell() {
    const { notifications } = usePage().props;
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<NotificationItem[] | null>(null);

    usePoll(POLL_MS, { only: ['notifications'] });

    function load() {
        setItems(null);
        getJson<{ unread: number; data: NotificationItem[] }>(route('notifications.recent'))
            .then((response) => setItems(response.data))
            .catch(() => setItems([]));
    }

    function markAll() {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true, onSuccess: load });
    }

    const unread = notifications.unread;

    return (
        <RadixPopover.Root
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) load();
            }}
        >
            <RadixPopover.Trigger asChild>
                <IconButton label={unread > 0 ? `Notificaciones: ${unread} sin leer` : 'Notificaciones'} className="relative">
                    <Bell />
                    {unread > 0 && (
                        <span className="numeric absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] leading-none font-semibold text-white max-sm:top-2 max-sm:right-2">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </IconButton>
            </RadixPopover.Trigger>
            <RadixPopover.Portal>
                <RadixPopover.Content align="end" sideOffset={6} className="z-50 w-[22rem] max-w-[calc(100vw-2rem)] rounded-md border border-line bg-surface shadow-overlay data-[state=open]:animate-pop-in">
                    <div className="flex items-center justify-between border-b border-line px-3 py-2">
                        <p className="eyebrow">Notificaciones</p>
                        {unread > 0 && (
                            <button type="button" onClick={markAll} className="text-xs font-medium text-brand-700 hover:underline">
                                Marcar todas como leídas
                            </button>
                        )}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto p-1">
                        {items === null ? (
                            <div className="flex flex-col gap-3 p-3">
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ) : items.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
                                <BellOff className="size-5 text-ink-400" aria-hidden />
                                <p className="text-base text-ink-500">No tienes notificaciones.</p>
                            </div>
                        ) : (
                            items.map((item) => <NotificationRow key={item.id} notification={item} compact onOpened={() => setOpen(false)} />)
                        )}
                    </div>

                    <div className="border-t border-line p-1">
                        <Link
                            href={route('notifications.index')}
                            onClick={() => setOpen(false)}
                            className="flex h-9 items-center justify-center rounded-sm text-sm font-medium text-brand-700 transition-colors hover:bg-brand-50"
                        >
                            Ver todas
                        </Link>
                    </div>
                </RadixPopover.Content>
            </RadixPopover.Portal>
        </RadixPopover.Root>
    );
}
