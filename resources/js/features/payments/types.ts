import type { Currency, Money } from '@/types/money';

/** Debe coincidir con App\Http\Resources\PaymentPresenter. */
export type PaymentItem = {
    id: number;
    paid_on: string;
    amount: Money;
    method: string;
    reference: string | null;
    notes: string | null;
    created_by: string | null;
    created_at: string;
    voided_at: string | null;
    void_reason: string | null;
    receipt: { id: number; name: string; mime_type: string; url: string } | null;
    charge: { id: number; description: string } | null;
    client: { id: number; name: string } | null;
};

export type PaymentFilters = {
    search: string;
    method: number | null;
    currency: Currency | null;
    from: string | null;
    to: string | null;
    voided: boolean;
};

export type PaymentTotals = { currency: Currency; amount: string; count: number }[];
