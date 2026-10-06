import { Badge } from '@/components/ui/Badge';
import type { ClientStatus } from './types';

export function ClientStatusBadge({ status }: { status: ClientStatus }) {
    return status === 'active' ? <Badge tone="success">Activo</Badge> : <Badge tone="neutral">Inactivo</Badge>;
}
