import { PageHeader } from '@/components/layout/PageHeader';
import type { CatalogService } from '@/features/catalog/types';
import type { ClientOption } from '@/features/clients/ClientSelector';
import { QuoteEditor } from '@/features/quotes/QuoteEditor';
import type { CompanyBlock, QuoteDefaults, QuoteFormValues } from '@/features/quotes/types';

type QuotesEditProps = {
    quote: {
        id: number;
        number: string;
        status: string;
        pdf_url: string;
        public_url: string | null;
    };
    client: ClientOption;
    values: QuoteFormValues;
    company: CompanyBlock;
    defaults: QuoteDefaults;
    catalog: CatalogService[];
};

export default function QuotesEdit({ quote, client, values, company, defaults, catalog }: QuotesEditProps) {
    return (
        <>
            <PageHeader
                title={`Editar ${quote.number}`}
                eyebrow="Cotizaciones"
                description={quote.status === 'sent' ? 'Esta cotización ya fue enviada: el cliente verá los cambios en el enlace.' : undefined}
            />
            <QuoteEditor
                initialValues={values}
                initialClient={client}
                company={company}
                defaults={defaults}
                catalog={catalog}
                number={quote.number}
                method="put"
                action={route('quotes.update', quote.id)}
                cancelHref={route('quotes.show', quote.id)}
                submitLabel="Guardar cambios"
                links={quote}
            />
        </>
    );
}
