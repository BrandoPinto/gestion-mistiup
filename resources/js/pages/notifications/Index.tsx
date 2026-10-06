import { Pagination } from '@/components/data/Pagination';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Panel } from '@/components/ui/Panel';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { NotificationRow } from '@/features/notifications/NotificationRow';
import type { NotificationItem } from '@/features/notifications/types';
import type { Paginated } from '@/types/pagination';
import { router, usePage } from '@inertiajs/react';
import { BellOff, CheckCheck } from 'lucide-react';

type NotificationsIndexProps = {
    notifications: Paginated<NotificationItem>;
    filters: { unread: boolean };
};

export default function NotificationsIndex({ notifications, filters }: NotificationsIndexProps) {
    const { notifications: shared } = usePage().props;

    function filter(unread: boolean, page?: number) {
        router.get(
            route('notifications.index'),
            {
                ...(unread ? { unread: 1 } : {}),
                ...(page && page > 1 ? { page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <PageHeader
                title="Notificaciones"
                eyebrow="Control"
                description="Avisos internos de cobros por vencer, vencidos y contratos que terminan."
                actions={
                    shared.unread > 0 && (
                        <Button variant="secondary" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}>
                            <CheckCheck /> Marcar todas como leídas
                        </Button>
                    )
                }
            />

            <Panel
                flush
                actions={
                    <SegmentedControl
                        label="Filtrar notificaciones"
                        value={filters.unread ? 'unread' : 'all'}
                        onChange={(value) => filter(value === 'unread')}
                        options={[
                            { value: 'all', label: 'Todas' },
                            {
                                value: 'unread',
                                label: `Sin leer (${shared.unread})`,
                            },
                        ]}
                    />
                }
            >
                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={BellOff}
                        title={filters.unread ? 'Todo leído' : 'Sin notificaciones'}
                        description="Los avisos se generan automáticamente según tus reglas de recordatorio."
                    />
                ) : (
                    <div className="divide-y divide-line">
                        {notifications.data.map((item) => (
                            <NotificationRow key={item.id} notification={item} />
                        ))}
                    </div>
                )}
                <Pagination
                    meta={notifications.meta}
                    noun={{
                        singular: 'notificación',
                        plural: 'notificaciones',
                    }}
                    onPageChange={(page) => filter(filters.unread, page)}
                />
            </Panel>
        </>
    );
}
