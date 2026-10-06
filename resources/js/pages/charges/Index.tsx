import { LedgerStrip } from '@/components/data/LedgerStrip';
import { MoneyByCurrency } from '@/components/data/MoneyByCurrency';
import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input, Select } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { ChargesTable } from '@/features/charges/ChargesTable';
import { ManualChargeDialog } from '@/features/charges/ManualChargeDialog';
import type { ChargeFilters, ChargeListItem, ChargeTotals } from '@/features/charges/types';
import { useTableQuery } from '@/hooks/useTableQuery';
import type { Paginated } from '@/types/pagination';
import { Plus } from 'lucide-react';
import { useState } from 'react';

type ChargesIndexProps = {
    charges: Paginated<ChargeListItem>;
    totals: ChargeTotals;
    filters: ChargeFilters;
};

const ONLY = ['charges', 'totals', 'filters'];
const DEFAULTS: Partial<ChargeFilters> = { status: 'open' };

const STATUS_LABEL: Record<ChargeFilters['status'], string> = {
    open: 'Por cobrar',
    overdue: 'Vencidos',
    pending: 'Pendientes',
    partial: 'Parciales',
    paid: 'Pagados',
    cancelled: 'Cancelados',
    all: 'Todos',
};

export default function ChargesIndex({ charges, totals, filters }: ChargesIndexProps) {
    const { apply, loading } = useTableQuery('charges.index', filters, ONLY, DEFAULTS);
    const [creating, setCreating] = useState(false);
    const isOpenView = filters.status === 'open' || filters.status === 'overdue' || filters.status === 'pending' || filters.status === 'partial';
    const count = totals.reduce((sum, row) => sum + row.count, 0);

    return (
        <>
            <PageHeader
                title="Cobros"
                eyebrow="Gestión"
                description="Lo que tus clientes deben pagar. Los vencidos se calculan según la fecha de hoy."
                actions={
                    <Button onClick={() => setCreating(true)}>
                        <Plus /> Nuevo cobro
                    </Button>
                }
            />

            <LedgerStrip
                className="mb-6"
                entries={[
                    {
                        label: isOpenView ? `Saldo · ${STATUS_LABEL[filters.status].toLowerCase()}` : `Importe · ${STATUS_LABEL[filters.status].toLowerCase()}`,
                        value: (
                            <MoneyByCurrency
                                items={totals.map((row) => ({
                                    currency: row.currency,
                                    amount: isOpenView ? row.balance : row.amount,
                                }))}
                                tone={filters.status === 'overdue' ? 'danger' : 'default'}
                            />
                        ),
                    },
                    {
                        label: 'Ya pagado (parciales incluidos)',
                        value: (
                            <MoneyByCurrency
                                items={totals.map((row) => ({
                                    currency: row.currency,
                                    amount: row.paid,
                                }))}
                                size="lg"
                            />
                        ),
                    },
                    {
                        label: 'Cobros',
                        value: <span className="numeric text-2xl font-semibold text-ink-900">{count}</span>,
                        hint: 'Según los filtros aplicados',
                    },
                ]}
            />

            <Panel flush>
                <div className="flex flex-col gap-2 border-b border-line p-3 lg:flex-row lg:items-center">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder="Buscar por concepto o cliente"
                        className="lg:max-w-xs lg:flex-1"
                    />
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <Select
                            aria-label="Estado"
                            value={filters.status}
                            onChange={(event) =>
                                apply({
                                    status: event.target.value as ChargeFilters['status'],
                                })
                            }
                            className="sm:w-36"
                        >
                            {(Object.keys(STATUS_LABEL) as ChargeFilters['status'][]).map((status) => (
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
                                    currency: (event.target.value || null) as ChargeFilters['currency'],
                                })
                            }
                            className="sm:w-32"
                        >
                            <option value="">Moneda: todas</option>
                            <option value="PEN">Soles</option>
                            <option value="USD">Dólares</option>
                        </Select>
                        <Input
                            aria-label="Vence desde"
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(event) => apply({ from: event.target.value || null })}
                            className="sm:w-40"
                        />
                        <Input
                            aria-label="Vence hasta"
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(event) => apply({ to: event.target.value || null })}
                            className="sm:w-40"
                        />
                    </div>
                </div>

                <ChargesTable
                    charges={charges.data}
                    loading={loading}
                    empty={
                        filters.status === 'open' && !filters.search
                            ? {
                                  title: 'No hay cobros pendientes',
                                  description: 'Cuando generes cobros desde tus servicios contratados aparecerán aquí.',
                              }
                            : {
                                  title: 'Sin resultados',
                                  description: 'Ningún cobro coincide con los filtros.',
                              }
                    }
                />

                <Pagination meta={charges.meta} noun={{ singular: 'cobro', plural: 'cobros' }} onPageChange={(page) => apply({ page })} />
            </Panel>

            <ManualChargeDialog open={creating} onOpenChange={setCreating} />
        </>
    );
}
