import { Badge } from '@/components/ui/Badge';
import type { ContractStatus } from './types';

const STATUS: Record<ContractStatus, { label: string; tone: 'success' | 'neutral' | 'danger' }> = {
    active: { label: 'Activo', tone: 'success' },
    completed: { label: 'Finalizado', tone: 'neutral' },
    cancelled: { label: 'Cancelado', tone: 'danger' },
};

export function ContractStatusBadge({ status }: { status: ContractStatus }) {
    return <Badge tone={STATUS[status].tone}>{STATUS[status].label}</Badge>;
}
