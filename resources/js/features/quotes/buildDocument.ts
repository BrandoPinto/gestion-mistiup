import { calculateQuote, QuoteMathError, type QuoteMathResult } from '@/lib/quote-math';
import type { ClientOption } from '@/features/clients/ClientSelector';
import type { QuoteDocument, QuoteFormValues } from './types';

export type EditorCalculation = { result: QuoteMathResult; error: null } | { result: null; error: QuoteMathError };

/** Cálculo en vivo con la misma lógica que el backend (quote-math). Un descuento imposible devuelve el error del campo. */
export function calculateFromForm(values: QuoteFormValues): EditorCalculation {
    try {
        const result = calculateQuote({
            items: values.items.map((item) => ({
                quantity: item.quantity,
                unit_price: item.unit_price,
                discount_type: item.discount_type || null,
                discount_value: item.discount_type ? item.discount_value : null,
            })),
            global_discount_type: values.global_discount_type || null,
            global_discount_value: values.global_discount_type ? values.global_discount_value : null,
            tax_rate: values.tax_rate,
        });

        return { result, error: null };
    } catch (error) {
        if (error instanceof QuoteMathError) return { result: null, error };
        throw error;
    }
}

/** Documento de vista previa a partir del formulario (antes de guardar). */
export function buildDocument(values: QuoteFormValues, client: ClientOption | null, number: string, calculation: QuoteMathResult | null): QuoteDocument {
    const empty = '0.00';

    return {
        number,
        issue_date: values.issue_date,
        valid_until: values.valid_until,
        delivery_date: values.delivery_date || null,
        currency: values.currency,
        tax_rate: values.tax_rate,
        client: client
            ? {
                  name: client.name,
                  document: client.document,
                  address: client.address ?? null,
                  email: client.email ?? null,
                  phone: client.phone ?? null,
                  contact_name: client.contact_name ?? null,
              }
            : null,
        items: values.items.map((item, index) => ({
            name: item.name,
            description: item.description || null,
            quantity: item.quantity,
            unit_price: item.unit_price,
            discount_type: item.discount_type || null,
            discount_value: item.discount_type ? item.discount_value : null,
            line_discount: calculation?.lines[index]?.discount ?? empty,
            line_total: calculation?.lines[index]?.total ?? '',
        })),
        global_discount_type: values.global_discount_type || null,
        global_discount_value: values.global_discount_type ? values.global_discount_value : null,
        subtotal: calculation?.subtotal ?? '',
        global_discount: calculation?.global_discount ?? empty,
        discount_total: calculation?.discount_total ?? empty,
        total: calculation?.total ?? '',
        tax_base: calculation?.tax_base ?? '',
        tax_amount: calculation?.tax_amount ?? '',
        intro: values.intro || null,
        observations: values.observations || null,
        terms: values.terms || null,
    };
}
