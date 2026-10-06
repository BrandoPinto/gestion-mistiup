import { cn } from '@/lib/cn';
import { formatTimestamp } from '@/lib/dates';
import { router } from '@inertiajs/react';
import { AlarmClock, CalendarClock, CircleAlert, type LucideIcon } from 'lucide-react';
import type { NotificationItem } from './types';

const ICONS: Record<string, { icon: LucideIcon; className: string }> = {
    charge_due: { icon: AlarmClock, className: 'bg-warning-50 text-warning-700' },
    charge_overdue: { icon: CircleAlert, className: 'bg-danger-50 text-danger-700' },
    contract_end: { icon: CalendarClock, className: 'bg-brand-50 text-brand-700' },
};

type NotificationRowProps = {
    notification: NotificationItem;
    compact?: boolean;
    onOpened?: () => void;
};

/** Fila de notificación: al hacer clic se marca como leída y se abre el cobro o contrato relacionado. */
export function NotificationRow({ notification, compact = false, onOpened }: NotificationRowProps) {
    const { icon: Icon, className } = ICONS[notification.kind] ?? { icon: AlarmClock, className: 'bg-cream-100 text-ink-500' };
    const unread = notification.read_at === null;

    function open() {
        onOpened?.();
        router.post(route('notifications.open', notification.id));
    }

    return (
        <button
            type="button"
            onClick={open}
            className={cn(
                'flex w-full items-start gap-3 rounded-sm text-left transition-colors hover:bg-cream-50',
                compact ? 'px-2.5 py-2' : 'px-4 py-3',
                unread && !compact && 'bg-brand-50/40',
            )}
        >
            <span className={cn('mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md', className)} aria-hidden>
                <Icon className="size-4" />
            </span>
            <span className="min-w-0 flex-1">
                <span className={cn('block truncate text-base', unread ? 'font-medium text-ink-900' : 'text-ink-700')}>{notification.title}</span>
                <span className="numeric block text-sm text-ink-500">{notification.body}</span>
                <span className="numeric block text-xs text-ink-400">{formatTimestamp(notification.created_at)}</span>
            </span>
            {unread && <span className="mt-2 size-2 shrink-0 rounded-full bg-brand-600" aria-label="Sin leer" />}
        </button>
    );
}
