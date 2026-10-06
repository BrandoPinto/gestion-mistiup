import { DataTable, type DataTableColumn } from '@/components/data/DataTable';
import { DateDisplay } from '@/components/data/DateDisplay';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Link } from '@inertiajs/react';
import { Layers, Repeat } from 'lucide-react';
import type { ReactNode } from 'react';
import { ContractStatusBadge } from './ContractStatusBadge';
import type { ContractListItem } from './types';

type ContractsTableProps = {
    contracts: ContractListItem[];
    /** Ocultar la columna cliente cuando la tabla vive dentro de la ficha del cliente. */
    showClient?: boolean;
    loading?: boolean;
    empty: { title: string; description?: string; action?: ReactNode };
};

export function ContractsTable({ contracts, showClient = true, loading = false, empty }: ContractsTableProps) {
    const columns: DataTableColumn<ContractListItem>[] = [
        {
            key: 'service',
            header: 'Servicio',
            cell: (contract) => (
                <div className="max-w-[44vw] sm:max-w-sm">
                    <p className="truncate font-medium text-ink-900">{contract.name}</p>
                    {showClient ? (
                        <Link href={route('clients.show', contract.client.id)} className="block truncate text-xs text-ink-500 hover:text-brand-700">
                            {contract.client.name}
                        </Link>
                    ) : (
                        <p className="text-xs text-ink-500">{contract.billing_label}</p>
                    )}
                </div>
            ),
        },
        {
            key: 'billing',
            header: 'Modalidad',
            className: showClient ? 'max-lg:hidden' : 'hidden',
            cell: (contract) =>
                contract.billing_type === 'recurring' ? (
                    <span className="inline-flex items-center gap-1.5">
                        <Repeat className="size-3.5 text-brand-600" aria-hidden /> {contract.billing_label}
                    </span>
                ) : (
                    <span className="text-ink-500">{contract.billing_label}</span>
                ),
        },
        {
            key: 'price',
            header: 'Precio',
            numeric: true,
            className: 'max-sm:hidden',
            cell: (contract) => <MoneyDisplay value={contract.price} />,
        },
        {
            key: 'next',
            header: 'Próximo cobro',
            cell: (contract) =>
                contract.next_charge_date ? (
                    <DateDisplay value={contract.next_charge_date} format="short" relative />
                ) : (
                    <span className="text-ink-400">—</span>
                ),
        },
        {
            key: 'term',
            header: 'Vigencia',
            className: 'max-md:hidden',
            cell: (contract) => (
                <span className="text-sm text-ink-500">
                    {contract.end_date ? (
                        <>
                            hasta <DateDisplay value={contract.end_date} format="short" />
                        </>
                    ) : contract.billing_type === 'recurring' ? (
                        'Sin fecha de fin'
                    ) : (
                        '—'
                    )}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Estado',
            className: 'max-sm:hidden',
            cell: (contract) => <ContractStatusBadge status={contract.status} />,
        },
    ];

    return (
        <DataTable
            columns={columns}
            rows={contracts}
            rowKey={(contract) => contract.id}
            rowHref={(contract) => route('contracts.show', contract.id)}
            loading={loading}
            empty={{ icon: Layers, ...empty }}
        />
    );
}
