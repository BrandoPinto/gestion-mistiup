import { DataTable, type DataTableColumn } from '@/components/data/DataTable';
import { DateDisplay } from '@/components/data/DateDisplay';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { formatDate } from '@/lib/dates';
import { Link } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import type { ReactNode } from 'react';
import { ChargeStatusBadge } from './ChargeStatusBadge';
import type { ChargeListItem } from './types';

type ChargesTableProps = {
    charges: ChargeListItem[];
    /** Oculta el cliente cuando la tabla vive dentro de su ficha. */
    showClient?: boolean;
    loading?: boolean;
    empty: { title: string; description?: string; action?: ReactNode };
};

export function ChargesTable({ charges, showClient = true, loading = false, empty }: ChargesTableProps) {
    const columns: DataTableColumn<ChargeListItem>[] = [
        {
            key: 'concept',
            header: 'Concepto',
            cell: (charge) => (
                <div className="max-w-[56vw] sm:max-w-sm">
                    <p className="truncate font-medium text-ink-900">{charge.description}</p>
                    {showClient ? (
                        <Link href={route('clients.show', charge.client.id)} className="block truncate text-xs text-ink-500 hover:text-brand-700">
                            {charge.client.name}
                        </Link>
                    ) : (
                        charge.period_start &&
                        charge.period_end && (
                            <p className="numeric truncate text-xs text-ink-500">
                                {formatDate(charge.period_start)} – {formatDate(charge.period_end)}
                            </p>
                        )
                    )}
                    <p className="text-xs sm:hidden">
                        <DueDate charge={charge} />
                    </p>
                </div>
            ),
        },
        {
            key: 'due',
            header: 'Vence',
            className: 'max-sm:hidden',
            cell: (charge) => <DueDate charge={charge} />,
        },
        {
            key: 'amount',
            header: 'Importe',
            numeric: true,
            className: 'max-md:hidden',
            cell: (charge) => <MoneyDisplay value={charge.amount} tone={charge.status === 'cancelled' ? 'muted' : 'default'} />,
        },
        {
            key: 'balance',
            header: 'Saldo',
            numeric: true,
            cell: (charge) =>
                charge.status === 'cancelled' ? (
                    <span className="text-ink-400">—</span>
                ) : (
                    <MoneyDisplay
                        value={charge.balance}
                        tone={charge.display_status === 'overdue' ? 'danger' : charge.status === 'paid' ? 'muted' : 'default'}
                    />
                ),
        },
        {
            key: 'status',
            header: 'Estado',
            className: 'max-sm:hidden',
            cell: (charge) => <ChargeStatusBadge status={charge.display_status} daysOverdue={charge.days_overdue} />,
        },
    ];

    return (
        <DataTable
            columns={columns}
            rows={charges}
            rowKey={(charge) => charge.id}
            rowHref={(charge) => route('charges.show', charge.id)}
            loading={loading}
            empty={{ icon: Receipt, ...empty }}
        />
    );
}

function DueDate({ charge }: { charge: ChargeListItem }) {
    return charge.status === 'pending' || charge.status === 'partial' ? (
        <DateDisplay value={charge.due_date} format="short" relative />
    ) : (
        <DateDisplay value={charge.due_date} format="short" className="text-ink-500" />
    );
}
