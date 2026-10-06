import { DataTable, type DataTableColumn } from '@/components/data/DataTable';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button, IconButton } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { DropdownItem, DropdownMenu, DropdownSeparator } from '@/components/ui/DropdownMenu';
import { Select } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { ServiceFormDialog } from '@/features/catalog/ServiceFormDialog';
import type { CatalogService, ServiceFilters } from '@/features/catalog/types';
import { useTableQuery } from '@/hooks/useTableQuery';
import type { Paginated } from '@/types/pagination';
import { router } from '@inertiajs/react';
import { BookOpen, CircleOff, CirclePlay, MoreHorizontal, Pencil, Plus, Repeat, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

type CatalogIndexProps = {
    services: Paginated<CatalogService>;
    filters: ServiceFilters;
};

const ONLY = ['services', 'filters'];

export default function CatalogIndex({ services, filters }: CatalogIndexProps) {
    const { apply, loading } = useTableQuery('services.index', filters, ONLY);
    const [editing, setEditing] = useState<CatalogService | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<CatalogService | null>(null);
    const [processing, setProcessing] = useState(false);

    function openForm(service: CatalogService | null) {
        setEditing(service);
        setFormOpen(true);
    }

    function toggleStatus(service: CatalogService) {
        router.patch(route('services.status', service.id), { is_active: !service.is_active }, { preserveScroll: true });
    }

    function destroy() {
        if (!deleting) return;
        router.delete(route('services.destroy', deleting.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = useMemo<DataTableColumn<CatalogService>[]>(
        () => [
            {
                key: 'name',
                header: 'Servicio',
                cell: (service) => (
                    <div className="max-w-[38vw] sm:max-w-md">
                        <p className={service.is_active ? 'truncate font-medium text-ink-900' : 'truncate font-medium text-ink-500'}>{service.name}</p>
                        {service.description && <p className="truncate text-xs text-ink-500">{service.description}</p>}
                    </div>
                ),
            },
            {
                key: 'billing',
                header: 'Modalidad',
                className: 'max-md:hidden',
                cell: (service) =>
                    service.default_billing_type === 'recurring' ? (
                        <span className="inline-flex items-center gap-1.5 text-ink-700">
                            <Repeat className="size-3.5 text-brand-600" aria-hidden />
                            {service.billing_label}
                        </span>
                    ) : (
                        <span className="text-ink-500">{service.billing_label}</span>
                    ),
            },
            {
                key: 'price',
                header: 'Precio',
                numeric: true,
                cell: (service) =>
                    service.default_price ? <MoneyDisplay value={service.default_price} /> : <span className="text-sm text-ink-400">A cotizar</span>,
            },
            {
                key: 'status',
                header: 'Estado',
                className: 'max-sm:hidden',
                cell: (service) => (service.is_active ? <Badge tone="success">Activo</Badge> : <Badge tone="neutral">Inactivo</Badge>),
            },
            {
                key: 'actions',
                header: '',
                headerClassName: 'w-12',
                cell: (service) => (
                    <DropdownMenu
                        trigger={
                            <IconButton label={`Acciones de ${service.name}`}>
                                <MoreHorizontal />
                            </IconButton>
                        }
                    >
                        <DropdownItem icon={Pencil} onSelect={() => openForm(service)}>
                            Editar
                        </DropdownItem>
                        <DropdownItem icon={service.is_active ? CircleOff : CirclePlay} onSelect={() => toggleStatus(service)}>
                            {service.is_active ? 'Desactivar' : 'Activar'}
                        </DropdownItem>
                        <DropdownSeparator />
                        <DropdownItem icon={Trash2} tone="danger" onSelect={() => setDeleting(service)}>
                            Eliminar
                        </DropdownItem>
                    </DropdownMenu>
                ),
            },
        ],
        [],
    );

    const hasFilters = Boolean(filters.search || filters.status);

    return (
        <>
            <PageHeader
                title="Catálogo de servicios"
                eyebrow="Sistema"
                description="Plantillas con precio y modalidad sugeridos. Cada contrato puede tener sus propias condiciones."
                actions={
                    <Button onClick={() => openForm(null)}>
                        <Plus /> Nuevo servicio
                    </Button>
                }
            />

            <Panel flush>
                <div className="flex flex-col gap-2 border-b border-line p-3 sm:flex-row sm:items-center">
                    <SearchInput
                        value={filters.search}
                        onSearch={(search) => apply({ search })}
                        placeholder="Buscar servicio"
                        className="sm:max-w-sm sm:flex-1"
                    />
                    <Select
                        aria-label="Filtrar por estado"
                        value={filters.status ?? ''}
                        onChange={(event) =>
                            apply({
                                status: (event.target.value || null) as ServiceFilters['status'],
                            })
                        }
                        className="sm:w-40"
                    >
                        <option value="">Estado: todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    rows={services.data}
                    rowKey={(service) => service.id}
                    loading={loading}
                    empty={
                        hasFilters
                            ? {
                                  icon: BookOpen,
                                  title: 'Sin resultados',
                                  description: 'Ningún servicio coincide con la búsqueda.',
                              }
                            : {
                                  icon: BookOpen,
                                  title: 'Tu catálogo está vacío',
                                  description: 'Agrega los servicios que ofreces: hosting, dominios, desarrollo, soporte…',
                                  action: (
                                      <Button onClick={() => openForm(null)}>
                                          <Plus /> Nuevo servicio
                                      </Button>
                                  ),
                              }
                    }
                />

                <Pagination meta={services.meta} noun={{ singular: 'servicio', plural: 'servicios' }} onPageChange={(page) => apply({ page })} />
            </Panel>

            <ServiceFormDialog open={formOpen} onOpenChange={setFormOpen} service={editing} />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="¿Eliminar este servicio del catálogo?"
                description={`«${deleting?.name ?? ''}» dejará de estar disponible. Los contratos y cotizaciones existentes no cambian: guardan su propia copia. Si solo quieres dejar de ofrecerlo, desactívalo.`}
                confirmLabel="Eliminar"
                tone="danger"
                processing={processing}
                onConfirm={destroy}
            />
        </>
    );
}
