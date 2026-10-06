import { Badge, type BadgeTone } from '@/components/ui/Badge';
import type { QuoteDisplayStatus } from './types';

const STATUS: Record<QuoteDisplayStatus, { label: string; tone: BadgeTone }> = {
    draft: { label: 'Borrador', tone: 'neutral' },
    sent: { label: 'Enviada', tone: 'brand' },
    viewed: { label: 'Vista', tone: 'brand' },
    expired: { label: 'Vencida', tone: 'warning' },
    partially_converted: { label: 'Convertida parcial', tone: 'success' },
    converted: { label: 'Convertida', tone: 'success' },
    cancelled: { label: 'Cancelada', tone: 'danger' },
};

export function QuoteStatusBadge({ status }: { status: QuoteDisplayStatus }) {
    return <Badge tone={STATUS[status].tone}>{STATUS[status].label}</Badge>;
}
