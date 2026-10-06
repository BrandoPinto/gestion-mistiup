import { DataTable, type DataTableColumn } from '@/components/data/DataTable';
import { DateDisplay } from '@/components/data/DateDisplay';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import type { ReactNode } from 'react';
import { QuoteStatusBadge } from './QuoteStatusBadge';
import type { QuoteListItem } from './types';

type QuotesTableProps = {
    quotes: QuoteListItem[];
    showClient?: boolean;
    loading?: boolean;
    empty: { title: string; description?: string; action?: ReactNode };
};

export function QuotesTable({ quotes, showClient = true, loading = false, empty }: QuotesTableProps) {
    const columns: DataTableColumn<QuoteListItem>[] = [
        {
            key: 'number',
            header: 'Cotización',
            cell: (quote) => (
                <div className="max-w-[48vw] sm:max-w-sm">
                    <p className="numeric font-medium text-ink-900">{quote.number}</p>
                    {showClient && (
                        <Link href={route('clients.show', quote.client.id)} className="block truncate text-xs text-ink-500 hover:text-brand-700">
                            {quote.client.name}
                        </Link>
                    )}
                </div>
            ),
        },
        {
            key: 'issue',
            header: 'Emisión',
            className: 'max-md:hidden',
            cell: (quote) => <DateDisplay value={quote.issue_date} format="short" className="text-ink-500" />,
        },
        {
            key: 'valid',
            header: 'Válida hasta',
            className: 'max-sm:hidden',
            cell: (quote) =>
                quote.status === 'draft' || quote.status === 'sent' ? (
                    <DateDisplay value={quote.valid_until} format="short" relative />
                ) : (
                    <DateDisplay value={quote.valid_until} format="short" className="text-ink-500" />
                ),
        },
        {
            key: 'total',
            header: 'Total',
            numeric: true,
            cell: (quote) => <MoneyDisplay value={quote.total} tone={quote.status === 'cancelled' ? 'muted' : 'default'} />,
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (quote) => <QuoteStatusBadge status={quote.display_status} />,
        },
    ];

    return (
        <DataTable
            columns={columns}
            rows={quotes}
            rowKey={(quote) => quote.id}
            rowHref={(quote) => route('quotes.show', quote.id)}
            loading={loading}
            empty={{ icon: FileText, ...empty }}
        />
    );
}
