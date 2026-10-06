import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { PageHeader } from '@/components/layout/PageHeader';
import { buttonClasses } from '@/components/ui/Button';
import { Select } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { ContractsTable } from '@/features/contracts/ContractsTable';
import type { ContractFilters, ContractListItem } from '@/features/contracts/types';
import { useTableQuery } from '@/hooks/useTableQuery';
import type { Paginated } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type ContractsIndexProps = {
    contracts: Paginated<ContractListItem>;
    filters: ContractFilters;
};

const ONLY = ['contracts', 'filters'];
const DEFAULTS: Partial<ContractFilters> = { status: 'active' };

export default function ContractsIndex({ contracts, filters }: ContractsIndexProps) {
    const { apply, loading } = useTableQuery('contracts.index', filters, ONLY, DEFAULTS);
    const hasFilters = Boolean(filters.search || filters.billing_type || filters.status !== 'active');

    return (
        <>
            <PageHeader
                title="Servicios contratados"
                eyebrow="Gestión"
                description="Lo que cada cliente tiene contratado y cuándo le toca el próximo cobro."
                actions={
                    <Link href={route('contracts.create')} className={buttonClasses('primary')}>
                        <Plus /> Contratar servicio
                    </Link>
                }
            />

            <Panel flush>
                <div className="flex flex-col gap-2 border-b border-line p-3 sm:flex-row sm:items-center">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder="Buscar por servicio o cliente"
                        className="sm:max-w-sm sm:flex-1"
                    />
                    <div className="grid grid-cols-2 gap-2 sm:flex">
                        <Select
                            aria-label="Filtrar por estado"
                            value={filters.status}
                            onChange={(event) =>
                                apply({
                                    status: event.target.value as ContractFilters['status'],
                                })
                            }
                            className="sm:w-36"
                        >
                            <option value="active">Activos</option>
                            <option value="completed">Finalizados</option>
                            <option value="cancelled">Cancelados</option>
                            <option value="all">Todos</option>
                        </Select>
                        <Select
                            aria-label="Filtrar por modalidad"
                            value={filters.billing_type ?? ''}
                            onChange={(event) =>
                                apply({
                                    billing_type: (event.target.value || null) as ContractFilters['billing_type'],
                                })
                            }
                            className="sm:w-40"
                        >
                            <option value="">Modalidad: todas</option>
                            <option value="recurring">Recurrentes</option>
                            <option value="one_time">Pago único</option>
                        </Select>
                    </div>
                </div>

                <ContractsTable
                    contracts={contracts.data}
                    loading={loading}
                    empty={
                        hasFilters
                            ? {
                                  title: 'Sin resultados',
                                  description: 'Ningún servicio contratado coincide con los filtros.',
                              }
                            : {
                                  title: 'Aún no hay servicios contratados',
                                  description: 'Asigna servicios a tus clientes para programar sus cobros.',
                                  action: (
                                      <Link href={route('contracts.create')} className={buttonClasses('primary')}>
                                          <Plus /> Contratar servicio
                                      </Link>
                                  ),
                              }
                    }
                />

                <Pagination meta={contracts.meta} noun={{ singular: 'servicio', plural: 'servicios' }} onPageChange={(page) => apply({ page })} />
            </Panel>
        </>
    );
}
