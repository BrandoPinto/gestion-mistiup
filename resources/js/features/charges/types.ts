import type { Currency, Money } from '@/types/money';

/** Deben coincidir con App\Http\Resources\ChargePresenter. */

export type ChargeStatus = 'pending' | 'partial' | 'paid' | 'cancelled';
/** Estado para mostrar: incluye "overdue", que se calcula y no se guarda. */
export type ChargeDisplayStatus = ChargeStatus | 'overdue';

export type ChargeListItem = {
    id: number;
    client: { id: number; name: string };
    contract: { id: number; name: string } | null;
    description: string;
    cycle_number: number | null;
    period_start: string | null;
    period_end: string | null;
    due_date: string;
    amount: Money;
    amount_paid: Money;
    balance: Money;
    status: ChargeStatus;
    display_status: ChargeDisplayStatus;
    days_overdue: number;
    paid_on: string | null;
};

export type ChargeDetail = ChargeListItem & {
    tax_rate: string;
    tax_amount: Money;
    base_amount: Money;
    notes: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    created_at: string;
    can: { pay: boolean; adjust: boolean; cancel: boolean };
};

export type ChargeFilterStatus = 'open' | 'overdue' | 'pending' | 'partial' | 'paid' | 'cancelled' | 'all';

export type ChargeFilters = {
    search: string;
    status: ChargeFilterStatus;
    currency: Currency | null;
    from: string | null;
    to: string | null;
};

/** Totales por moneda (ChargeIndexQuery::totals). */
export type ChargeTotals = {
    currency: Currency;
    amount: string;
    paid: string;
    balance: string;
    count: number;
}[];

export type PaymentMethodOption = { id: number; name: string };
