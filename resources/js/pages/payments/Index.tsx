import { DataTable, type DataTableColumn } from '@/components/data/DataTable';
import { DateDisplay } from '@/components/data/DateDisplay';
import { LedgerStrip } from '@/components/data/LedgerStrip';
import { MoneyByCurrency } from '@/components/data/MoneyByCurrency';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Checkbox, Input, Select } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import type { PaymentMethodOption } from '@/features/charges/types';
import type { PaymentFilters, PaymentItem, PaymentTotals } from '@/features/payments/types';
import { useTableQuery } from '@/hooks/useTableQuery';
import { cn } from '@/lib/cn';
import type { Paginated } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { Paperclip, Wallet } from 'lucide-react';

type PaymentsIndexProps = {
    payments: Paginated<PaymentItem>;
    totals: PaymentTotals;
    filters: PaymentFilters;
    paymentMethods: PaymentMethodOption[];
};

const ONLY = ['payments', 'totals', 'filters'];

const columns: DataTableColumn<PaymentItem>[] = [
    {
        key: 'date',
        header: 'Fecha',
        className: 'max-sm:hidden',
        cell: (payment) => <DateDisplay value={payment.paid_on} format="short" />,
    },
    {
        key: 'concept',
        header: 'Cobro',
        cell: (payment) => (
            <div className="max-w-[56vw] sm:max-w-sm">
                <p className="truncate text-ink-900">{payment.charge?.description}</p>
                <p className="truncate text-xs text-ink-500">
                    <DateDisplay value={payment.paid_on} format="short" className="sm:hidden" />
                    <span className="sm:hidden"> · </span>
                    {payment.client?.name}
                </p>
            </div>
        ),
    },
    {
        key: 'method',
        header: 'Método',
        className: 'max-md:hidden',
        cell: (payment) => (
            <span className="inline-flex items-center gap-1.5">
                {payment.method}
                {payment.reference && <span className="numeric text-xs text-ink-500">{payment.reference}</span>}
                {payment.receipt && <Paperclip className="size-3.5 text-ink-400" aria-label="Con comprobante" />}
            </span>
        ),
    },
    {
        key: 'amount',
        header: 'Monto',
        numeric: true,
        cell: (payment) => (
            <span className="inline-flex flex-col items-end gap-0.5">
                <MoneyDisplay value={payment.amount} className={cn(payment.voided_at && 'line-through opacity-60')} />
                {payment.voided_at && <Badge tone="danger">Anulado</Badge>}
            </span>
        ),
    },
];

export default function PaymentsIndex({ payments, totals, filters, paymentMethods }: PaymentsIndexProps) {
    const { apply, loading } = useTableQuery('payments.index', { ...filters, voided: filters.voided ? 1 : null }, ONLY, {});

    return (
        <>
            <PageHeader title="Pagos" eyebrow="Gestión" description="Todo el dinero recibido. Los pagos anulados se conservan en el historial." />

            <LedgerStrip
                className="mb-6"
                entries={[
                    {
                        label: 'Total cobrado · según filtros',
                        value: <MoneyByCurrency items={totals} tone="success" />,
                    },
                    {
                        label: 'Pagos',
                        value: <span className="numeric text-2xl font-semibold text-ink-900">{totals.reduce((sum, row) => sum + row.count, 0)}</span>,
                        hint: 'Sin contar anulados',
                    },
                ]}
            />

            <Panel flush>
                <div className="flex flex-col gap-2 border-b border-line p-3 lg:flex-row lg:flex-wrap lg:items-center">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder="Buscar por cliente, cobro u operación"
                        className="lg:max-w-xs lg:flex-1"
                    />
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                        <Select
                            aria-label="Método"
                            value={filters.method ?? ''}
                            onChange={(event) =>
                                apply({
                                    method: event.target.value ? Number(event.target.value) : null,
                                })
                            }
                            className="sm:w-40"
                        >
                            <option value="">Método: todos</option>
                            {paymentMethods.map((method) => (
                                <option key={method.id} value={method.id}>
                                    {method.name}
                                </option>
                            ))}
                        </Select>
                        <Select
                            aria-label="Moneda"
                            value={filters.currency ?? ''}
                            onChange={(event) =>
                                apply({
                                    currency: (event.target.value || null) as PaymentFilters['currency'],
                                })
                            }
                            className="sm:w-32"
                        >
                            <option value="">Moneda: todas</option>
                            <option value="PEN">Soles</option>
                            <option value="USD">Dólares</option>
                        </Select>
                        <Input
                            aria-label="Desde"
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(event) => apply({ from: event.target.value || null })}
                            className="sm:w-40"
                        />
                        <Input
                            aria-label="Hasta"
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(event) => apply({ to: event.target.value || null })}
                            className="sm:w-40"
                        />
                        <Checkbox
                            label="Incluir anulados"
                            checked={filters.voided}
                            onChange={(event) =>
                                apply({
                                    voided: event.target.checked ? 1 : null,
                                })
                            }
                            className="col-span-2 sm:ml-1"
                        />
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    rows={payments.data}
                    rowKey={(payment) => payment.id}
                    rowHref={(payment) => (payment.charge ? route('charges.show', payment.charge.id) : '#')}
                    loading={loading}
                    empty={{
                        icon: Wallet,
                        title: 'Sin pagos',
                        description: 'Los pagos se registran desde cada cobro.',
                        action: (
                            <Link href={route('charges.index')} className="text-base font-medium text-brand-700 hover:underline">
                                Ir a cobros
                            </Link>
                        ),
                    }}
                />

                <Pagination meta={payments.meta} noun={{ singular: 'pago', plural: 'pagos' }} onPageChange={(page) => apply({ page })} />
            </Panel>
        </>
    );
}
