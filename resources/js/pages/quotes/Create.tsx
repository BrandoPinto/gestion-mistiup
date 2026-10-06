import { PageHeader } from '@/components/layout/PageHeader';
import type { CatalogService } from '@/features/catalog/types';
import type { ClientOption } from '@/features/clients/ClientSelector';
import { QuoteEditor } from '@/features/quotes/QuoteEditor';
import { emptyItem } from '@/features/quotes/QuoteItemsEditor';
import type { CompanyBlock, QuoteDefaults, QuoteFormValues } from '@/features/quotes/types';
import { addDays } from '@/lib/dates';

type QuotesCreateProps = {
    client: ClientOption | null;
    company: CompanyBlock;
    defaults: QuoteDefaults;
    catalog: CatalogService[];
    today: string;
};

export default function QuotesCreate({ client, company, defaults, catalog, today }: QuotesCreateProps) {
    const initialValues: QuoteFormValues = {
        client_id: client?.id ?? null,
        currency: defaults.currency,
        issue_date: today,
        valid_until: addDays(today, defaults.validity_days),
        delivery_date: '',
        // "18.00" → "18" (solo se recortan ceros decimales: "20" sigue siendo "20").
        tax_rate: defaults.tax_rate.includes('.') ? defaults.tax_rate.replace(/\.?0+$/, '') : defaults.tax_rate,
        global_discount_type: '',
        global_discount_value: '',
        intro: defaults.intro ?? '',
        observations: '',
        terms: defaults.terms ?? '',
        internal_notes: '',
        items: [emptyItem()],
    };

    return (
        <>
            <PageHeader title="Nueva cotización" eyebrow="Cotizaciones" description="El número se asigna al guardar." />
            <QuoteEditor
                initialValues={initialValues}
                initialClient={client}
                company={company}
                defaults={defaults}
                catalog={catalog}
                number="COT-____-____"
                method="post"
                action={route('quotes.store')}
                cancelHref={
                    client
                        ? route('clients.show', {
                              client: client.id,
                              tab: 'quotes',
                          })
                        : route('quotes.index')
                }
                submitLabel="Guardar borrador"
            />
        </>
    );
}
