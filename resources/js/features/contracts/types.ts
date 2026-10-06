import type { BillingType, IntervalUnit } from '@/lib/recurrence';
import type { Currency, Money } from '@/types/money';

/** Deben coincidir con App\Http\Resources\ContractPresenter. */

export type ContractStatus = 'active' | 'completed' | 'cancelled';

export type ContractListItem = {
    id: number;
    client: { id: number; name: string };
    name: string;
    billing_type: BillingType;
    billing_label: string;
    price: Money;
    start_date: string;
    end_date: string | null;
    next_charge_date: string | null;
    next_cycle_number: number;
    total_cycles: number | null;
    status: ContractStatus;
};

export type ContractDetail = ContractListItem & {
    description: string | null;
    notes: string | null;
    term_months: number | null;
    service: { id: number; name: string } | null;
    origin_quote: { id: number; number: string } | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    created_at: string;
};

export type ScheduleItem = {
    cycle: number;
    due_date: string;
    period_start: string | null;
    period_end: string | null;
    adjusted: boolean;
};

/** Respuesta de POST /contratos/calendario. */
export type SchedulePreview = {
    label: string;
    suggested_first_cycle: number | null;
    total_cycles: number | null;
    end_date: string | null;
    cycles: ScheduleItem[];
};

export type ContractFormValues = {
    client_id: number | null;
    service_id: number | null;
    name: string;
    description: string;
    currency: Currency;
    price: string;
    billing_type: BillingType;
    interval_unit: IntervalUnit | '';
    interval_count: string;
    start_date: string;
    has_term: boolean;
    term_unit: IntervalUnit;
    term_count: string;
    first_cycle: string;
    first_charge_date: string;
    notes: string;
};

export type ContractFilters = {
    search: string;
    status: ContractStatus | 'all';
    billing_type: BillingType | null;
};
