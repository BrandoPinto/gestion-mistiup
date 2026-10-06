import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { PageHeader } from '@/components/layout/PageHeader';
import { buttonClasses } from '@/components/ui/Button';
import { Select } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { QuotesTable } from '@/features/quotes/QuotesTable';
import type { QuoteFilters, QuoteListItem } from '@/features/quotes/types';
import { useTableQuery } from '@/hooks/useTableQuery';
import type { Paginated } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type QuotesIndexProps = {
    quotes: Paginated<QuoteListItem>;
    filters: QuoteFilters;
};

const ONLY = ['quotes', 'filters'];
const DEFAULTS: Partial<QuoteFilters> = { status: 'open' };

const STATUS_LABEL: Record<QuoteFilters['status'], string> = {
    open: 'Abiertas',
    expired: 'Vencidas',
    draft: 'Borradores',
    sent: 'Enviadas',
    partially_converted: 'Convertidas parcial',
    converted: 'Convertidas',
    cancelled: 'Canceladas',
    all: 'Todas',
};

export default function QuotesIndex({ quotes, filters }: QuotesIndexProps) {
    const { apply, loading } = useTableQuery('quotes.index', filters, ONLY, DEFAULTS);
    const newQuote = (
        <Link href={route('quotes.create')} className={buttonClasses('primary')}>
            <Plus /> Nueva cotización
        </Link>
    );

    return (
        <>
            <PageHeader
                title="Cotizaciones"
                eyebrow="Gestión"
                description="Propuestas a clientes. Las abiertas son borradores y enviadas."
                actions={newQuote}
            />

            <Panel flush>
                <div className="flex flex-col gap-2 border-b border-line p-3 sm:flex-row sm:items-center">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder="Buscar por número o cliente"
                        className="sm:max-w-sm sm:flex-1"
                    />
                    <div className="grid grid-cols-2 gap-2 sm:flex">
                        <Select
                            aria-label="Estado"
                            value={filters.status}
                            onChange={(event) =>
                                apply({
                                    status: event.target.value as QuoteFilters['status'],
                                })
                            }
                            className="sm:w-44"
                        >
                            {(Object.keys(STATUS_LABEL) as QuoteFilters['status'][]).map((status) => (
                                <option key={status} value={status}>
                                    {STATUS_LABEL[status]}
                                </option>
                            ))}
                        </Select>
                        <Select
                            aria-label="Moneda"
                            value={filters.currency ?? ''}
                            onChange={(event) =>
                                apply({
                                    currency: (event.target.value || null) as QuoteFilters['currency'],
                                })
                            }
                            className="sm:w-32"
                        >
                            <option value="">Moneda: todas</option>
                            <option value="PEN">Soles</option>
                            <option value="USD">Dólares</option>
                        </Select>
                    </div>
                </div>

                <QuotesTable
                    quotes={quotes.data}
                    loading={loading}
                    empty={
                        filters.status === 'open' && !filters.search
                            ? {
                                  title: 'No hay cotizaciones abiertas',
                                  description: 'Crea una propuesta y compártela con tu cliente.',
                                  action: newQuote,
                              }
                            : {
                                  title: 'Sin resultados',
                                  description: 'Ninguna cotización coincide con los filtros.',
                              }
                    }
                />

                <Pagination meta={quotes.meta} noun={{ singular: 'cotización', plural: 'cotizaciones' }} onPageChange={(page) => apply({ page })} />
            </Panel>
        </>
    );
}
