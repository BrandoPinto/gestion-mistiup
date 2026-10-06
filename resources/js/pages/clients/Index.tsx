import { DataTable, type DataTableColumn } from '@/components/data/DataTable';
import { DateDisplay } from '@/components/data/DateDisplay';
import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { PageHeader } from '@/components/layout/PageHeader';
import { buttonClasses } from '@/components/ui/Button';
import { Select } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { ClientIdentity } from '@/features/clients/ClientIdentity';
import { ClientStatusBadge } from '@/features/clients/ClientStatusBadge';
import { ContactLinks } from '@/features/clients/ContactLinks';
import type { ClientFilters, ClientListItem } from '@/features/clients/types';
import { useTableQuery } from '@/hooks/useTableQuery';
import { businessDateOf } from '@/lib/dates';
import type { Paginated } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';

type ClientsIndexProps = {
    clients: Paginated<ClientListItem>;
    filters: ClientFilters;
};

const ONLY = ['clients', 'filters'];
const DEFAULTS: Partial<ClientFilters> = {
    sort: 'name',
    direction: 'asc',
    per_page: 15,
};

const columns: DataTableColumn<ClientListItem>[] = [
    {
        key: 'name',
        header: 'Cliente',
        sortKey: 'name',
        cell: (client) => (
            // En móvil se recorta el nombre para que el estado siga visible sin desplazar la tabla.
            <div className="max-w-[52vw] sm:max-w-none">
                <ClientIdentity name={client.name} type={client.type} secondary={client.document ?? client.trade_name} />
            </div>
        ),
    },
    {
        key: 'contact',
        header: 'Contacto',
        className: 'max-md:hidden',
        cell: (client) => (
            <div className="flex flex-col gap-1">
                {client.contact_name && <span className="text-sm text-ink-900">{client.contact_name}</span>}
                <ContactLinks whatsapp={client.whatsapp} phone={client.phone} email={client.email} />
            </div>
        ),
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (client) => <ClientStatusBadge status={client.status} />,
    },
    {
        key: 'created_at',
        header: 'Registrado',
        sortKey: 'created_at',
        className: 'max-lg:hidden',
        cell: (client) => <DateDisplay value={businessDateOf(client.created_at)} format="short" className="text-sm text-ink-500" />,
    },
];

export default function ClientsIndex({ clients, filters }: ClientsIndexProps) {
    const { apply, loading } = useTableQuery('clients.index', filters, ONLY, DEFAULTS);
    const hasFilters = Boolean(filters.search || filters.status || filters.type);

    return (
        <>
            <PageHeader
                title="Clientes"
                eyebrow="Gestión"
                description="Tu cartera de clientes y su relación comercial."
                actions={
                    <Link href={route('clients.create')} className={buttonClasses('primary')}>
                        <Plus /> Nuevo cliente
                    </Link>
                }
            />

            <Panel flush>
                <div className="flex flex-col gap-2 border-b border-line p-3 sm:flex-row sm:items-center">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder="Buscar por nombre, RUC/DNI, correo o teléfono"
                        className="sm:max-w-sm sm:flex-1"
                    />
                    <div className="grid grid-cols-2 gap-2 sm:flex">
                        <Select
                            aria-label="Filtrar por estado"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                apply({
                                    status: (event.target.value || null) as ClientFilters['status'],
                                })
                            }
                            className="sm:w-36"
                        >
                            <option value="">Estado: todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </Select>
                        <Select
                            aria-label="Filtrar por tipo"
                            value={filters.type ?? ''}
                            onChange={(event) =>
                                apply({
                                    type: (event.target.value || null) as ClientFilters['type'],
                                })
                            }
                            className="sm:w-36"
                        >
                            <option value="">Tipo: todos</option>
                            <option value="company">Empresas</option>
                            <option value="person">Personas</option>
                        </Select>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    rows={clients.data}
                    rowKey={(client) => client.id}
                    rowHref={(client) => route('clients.show', client.id)}
                    sort={{ sort: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) =>
                        apply({
                            sort: sort as ClientFilters['sort'],
                            direction,
                        })
                    }
                    loading={loading}
                    empty={
                        hasFilters
                            ? {
                                  icon: Users,
                                  title: 'Sin resultados',
                                  description: 'Ningún cliente coincide con la búsqueda o los filtros.',
                              }
                            : {
                                  icon: Users,
                                  title: 'Aún no tienes clientes',
                                  description: 'Registra tu primer cliente para asignarle servicios, cobros y cotizaciones.',
                                  action: (
                                      <Link href={route('clients.create')} className={buttonClasses('primary')}>
                                          <Plus /> Nuevo cliente
                                      </Link>
                                  ),
                              }
                    }
                />

                <Pagination
                    meta={clients.meta}
                    noun={{ singular: 'cliente', plural: 'clientes' }}
                    onPageChange={(page) => apply({ page })}
                    onPerPageChange={(per_page) => apply({ per_page })}
                />
            </Panel>
        </>
    );
}
