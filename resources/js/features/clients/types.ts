import type { Currency } from '@/types/money';

/** Deben coincidir con App\Http\Resources\ClientPresenter. */

export type ClientType = 'company' | 'person';
export type ClientStatus = 'active' | 'inactive';
export type DocumentType = 'RUC' | 'DNI' | 'CE' | 'NONE';

export type ClientListItem = {
    id: number;
    type: ClientType;
    name: string;
    trade_name: string | null;
    document: string | null;
    phone: string | null;
    whatsapp: string | null;
    email: string | null;
    contact_name: string | null;
    status: ClientStatus;
    created_at: string;
};

export type ClientDetail = ClientListItem & {
    document_type: DocumentType;
    document_number: string | null;
    address: string | null;
    notes: string | null;
    updated_at: string;
};

export type ClientFormValues = {
    type: ClientType;
    name: string;
    trade_name: string;
    document_type: DocumentType;
    document_number: string;
    phone: string;
    whatsapp: string;
    email: string;
    address: string;
    contact_name: string;
    notes: string;
    status: ClientStatus;
};

export type ClientFilters = {
    search: string;
    status: ClientStatus | null;
    type: ClientType | null;
    sort: 'name' | 'created_at';
    direction: 'asc' | 'desc';
    per_page: number;
};

export type ClientTab = 'summary' | 'services' | 'charges' | 'quotes';

/** Debe coincidir con ClientController@show (stats) y ClientBalanceQuery. */
export type ClientStats = {
    active_contracts: number;
    open_quotes: number;
    pending: { currency: Currency; amount: string }[];
    overdue: { currency: Currency; amount: string }[];
    next_due_date: string | null;
};

export const CLIENT_TYPE_LABEL: Record<ClientType, string> = {
    company: 'Empresa',
    person: 'Persona',
};

export const DOCUMENT_TYPE_LABEL: Record<DocumentType, string> = {
    RUC: 'RUC',
    DNI: 'DNI',
    CE: 'Carné de extranjería',
    NONE: 'Sin documento',
};

export const EMPTY_CLIENT: ClientFormValues = {
    type: 'company',
    name: '',
    trade_name: '',
    document_type: 'RUC',
    document_number: '',
    phone: '',
    whatsapp: '',
    email: '',
    address: '',
    contact_name: '',
    notes: '',
    status: 'active',
};
