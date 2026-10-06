/** Debe coincidir con NotificationController::present(). */
export type NotificationItem = {
    id: string;
    kind: 'charge_due' | 'charge_overdue' | 'contract_end' | string;
    tone: 'warning' | 'danger' | 'brand' | 'neutral' | string;
    title: string;
    body: string;
    read_at: string | null;
    created_at: string;
};
