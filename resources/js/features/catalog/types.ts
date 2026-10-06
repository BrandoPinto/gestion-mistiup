import type { BillingType, IntervalUnit } from '@/lib/recurrence';
import type { Currency, Money } from '@/types/money';

/** Debe coincidir con App\Http\Resources\ServicePresenter. */
export type CatalogService = {
    id: number;
    name: string;
    description: string | null;
    default_currency: Currency;
    default_price: Money | null;
    default_billing_type: BillingType;
    default_interval_unit: IntervalUnit | null;
    default_interval_count: number | null;
    billing_label: string;
    is_active: boolean;
};

export type ServiceFilters = {
    search: string;
    status: 'active' | 'inactive' | null;
};

export type ServiceFormValues = {
    name: string;
    description: string;
    default_currency: Currency;
    default_price: string;
    default_billing_type: BillingType;
    default_interval_unit: IntervalUnit | '';
    default_interval_count: string;
    is_active: boolean;
};

export const EMPTY_SERVICE: ServiceFormValues = {
    name: '',
    description: '',
    default_currency: 'PEN',
    default_price: '',
    default_billing_type: 'one_time',
    default_interval_unit: '',
    default_interval_count: '',
    is_active: true,
};

export function toFormValues(service: CatalogService): ServiceFormValues {
    return {
        name: service.name,
        description: service.description ?? '',
        default_currency: service.default_currency,
        default_price: service.default_price?.amount ?? '',
        default_billing_type: service.default_billing_type,
        default_interval_unit: service.default_interval_unit ?? '',
        default_interval_count: service.default_interval_count ? String(service.default_interval_count) : '',
        is_active: service.is_active,
    };
}
