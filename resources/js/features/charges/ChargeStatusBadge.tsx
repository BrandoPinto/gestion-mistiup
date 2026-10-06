import { Badge, type BadgeTone } from '@/components/ui/Badge';
import type { ChargeDisplayStatus } from './types';

const STATUS: Record<ChargeDisplayStatus, { label: string; tone: BadgeTone }> = {
    pending: { label: 'Pendiente', tone: 'warning' },
    partial: { label: 'Parcial', tone: 'warning' },
    paid: { label: 'Pagado', tone: 'success' },
    cancelled: { label: 'Cancelado', tone: 'neutral' },
    overdue: { label: 'Vencido', tone: 'danger' },
};

type ChargeStatusBadgeProps = {
    status: ChargeDisplayStatus;
    /** En vencidos, muestra "Vencido · 5 d". */
    daysOverdue?: number;
};

export function ChargeStatusBadge({ status, daysOverdue = 0 }: ChargeStatusBadgeProps) {
    const { label, tone } = STATUS[status];

    return (
        <Badge tone={tone}>
            {label}
            {status === 'overdue' && daysOverdue > 0 && <span className="numeric font-normal opacity-80">· {daysOverdue} d</span>}
        </Badge>
    );
}
