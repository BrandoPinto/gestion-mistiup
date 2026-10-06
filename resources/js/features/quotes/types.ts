import type { DiscountType } from '@/lib/quote-math';
import type { Currency, Money } from '@/types/money';

/** Deben coincidir con App\Http\Resources\QuotePresenter y CompanyPresenter. */

export type QuoteStatus = 'draft' | 'sent' | 'partially_converted' | 'converted' | 'cancelled';
/** Estado para mostrar: agrega los calculados ("expired"; "viewed" en la Fase 8). */
export type QuoteDisplayStatus = QuoteStatus | 'expired' | 'viewed';

export type QuoteListItem = {
    id: number;
    number: string;
    client: { id: number; name: string };
    issue_date: string;
    valid_until: string;
    total: Money;
    status: QuoteStatus;
    display_status: QuoteDisplayStatus;
};

export type BankAccount = {
    bank: string;
    currency: Currency | null;
    number: string;
    cci: string | null;
    holder: string | null;
};

export type CompanyBlock = {
    name: string;
    legal_name: string | null;
    ruc: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    logo_url: string | null;
    bank_accounts: BankAccount[];
    yape_phone: string | null;
    plin_phone: string | null;
    wallet_holder: string | null;
};

export type DocumentClient = {
    name: string;
    document: string | null;
    address: string | null;
    email: string | null;
    phone: string | null;
    contact_name: string | null;
};

export type DocumentItem = {
    name: string;
    description: string | null;
    quantity: string;
    unit_price: string;
    discount_type: DiscountType | null;
    discount_value: string | null;
    line_discount: string;
    line_total: string;
};

/** Documento listo para mostrar: vista previa, enlace público y PDF consumen esta misma forma. */
export type QuoteDocument = {
    number: string;
    issue_date: string;
    valid_until: string;
    delivery_date: string | null;
    currency: Currency;
    tax_rate: string;
    client: DocumentClient | null;
    items: DocumentItem[];
    global_discount_type: DiscountType | null;
    global_discount_value: string | null;
    subtotal: string;
    global_discount: string;
    discount_total: string;
    total: string;
    tax_base: string;
    tax_amount: string;
    intro: string | null;
    observations: string | null;
    terms: string | null;
};

export type QuoteItemForm = {
    /** Clave estable para React (no se envía al servidor). */
    key: string;
    service_id: number | null;
    name: string;
    description: string;
    quantity: string;
    unit_price: string;
    discount_type: DiscountType | '';
    discount_value: string;
};

export type QuoteFormValues = {
    client_id: number | null;
    currency: Currency;
    issue_date: string;
    valid_until: string;
    delivery_date: string;
    tax_rate: string;
    global_discount_type: DiscountType | '';
    global_discount_value: string;
    intro: string;
    observations: string;
    terms: string;
    internal_notes: string;
    items: QuoteItemForm[];
};

export type QuoteDefaults = {
    currency: Currency;
    tax_rate: string;
    validity_days: number;
    intro: string | null;
    terms: string | null;
};

export type QuoteFilters = {
    search: string;
    status: 'open' | 'expired' | QuoteStatus | 'all';
    currency: Currency | null;
};
