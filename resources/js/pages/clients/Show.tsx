import type { ActivityItem } from '@/components/data/ActivityList';
import { Button, buttonClasses, IconButton } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { DropdownItem, DropdownMenu } from '@/components/ui/DropdownMenu';
import { Panel } from '@/components/ui/Panel';
import { TabNav } from '@/components/ui/TabNav';
import { ChargesTable } from '@/features/charges/ChargesTable';
import { ManualChargeDialog } from '@/features/charges/ManualChargeDialog';
import type { ChargeListItem } from '@/features/charges/types';
import { ClientIdentity } from '@/features/clients/ClientIdentity';
import { ClientStatusBadge } from '@/features/clients/ClientStatusBadge';
import { ClientSummary } from '@/features/clients/ClientSummary';
import type { ClientDetail, ClientStats, ClientTab } from '@/features/clients/types';
import { ContractsTable } from '@/features/contracts/ContractsTable';
import type { ContractListItem } from '@/features/contracts/types';
import { QuotesTable } from '@/features/quotes/QuotesTable';
import type { QuoteListItem } from '@/features/quotes/types';
import { whatsappUrl } from '@/lib/phone';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MessageCircle, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type ClientsShowProps = {
    client: ClientDetail;
    tab: ClientTab;
    stats: ClientStats;
    contracts: ContractListItem[];
    charges: ChargeListItem[];
    quotes: QuoteListItem[];
    activity: ActivityItem[];
};

export default function ClientsShow({ client, tab, stats, contracts, charges, quotes, activity }: ClientsShowProps) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [creatingCharge, setCreatingCharge] = useState(false);

    const tabHref = (key: ClientTab) => route('clients.show', key === 'summary' ? { client: client.id } : { client: client.id, tab: key });
    const newContractHref = route('contracts.create', { client: client.id });
    const newQuoteHref = route('quotes.create', { client: client.id });

    function destroy() {
        router.delete(route('clients.destroy', client.id), {
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onError: (errors) => {
                setConfirmDelete(false);
                if (errors.client) toast.error(errors.client);
            },
        });
    }

    return (
        <>
            <Head title={client.name} />

            <Link href={route('clients.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900">
                <ArrowLeft className="size-4" aria-hidden /> Clientes
            </Link>

            {/* Cabecera CRM: identidad, estado y acciones principales. */}
            <header className="mb-1 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-center gap-3">
                    <ClientIdentity name={client.name} type={client.type} secondary={client.document ?? client.trade_name} size="lg" />
                    <ClientStatusBadge status={client.status} />
                </div>

                <div className="flex items-center gap-2">
                    {client.whatsapp && (
                        <a href={whatsappUrl(client.whatsapp)} target="_blank" rel="noopener noreferrer" className={buttonClasses('secondary')}>
                            <MessageCircle className="text-success-600" /> WhatsApp
                        </a>
                    )}
                    <Link href={route('clients.edit', client.id)} className={buttonClasses('secondary')}>
                        <Pencil /> Editar
                    </Link>
                    <DropdownMenu
                        trigger={
                            <IconButton label="Más acciones" variant="secondary">
                                <MoreHorizontal />
                            </IconButton>
                        }
                    >
                        <DropdownItem icon={Trash2} tone="danger" onSelect={() => setConfirmDelete(true)}>
                            Eliminar cliente
                        </DropdownItem>
                    </DropdownMenu>
                </div>
            </header>

            <div className="mb-6">
                <TabNav
                    label="Secciones del cliente"
                    active={tab}
                    items={[
                        {
                            key: 'summary',
                            label: 'Resumen',
                            href: tabHref('summary'),
                        },
                        {
                            key: 'services',
                            label: 'Servicios',
                            href: tabHref('services'),
                        },
                        {
                            key: 'charges',
                            label: 'Cobros',
                            href: tabHref('charges'),
                        },
                        {
                            key: 'quotes',
                            label: 'Cotizaciones',
                            href: tabHref('quotes'),
                        },
                    ]}
                />
            </div>

            {tab === 'summary' ? (
                <ClientSummary client={client} stats={stats} activity={activity} />
            ) : tab === 'services' ? (
                <Panel
                    flush
                    title="Servicios contratados"
                    actions={
                        <Link href={newContractHref} className={buttonClasses('primary', 'sm')}>
                            <Plus /> Contratar servicio
                        </Link>
                    }
                >
                    <ContractsTable
                        contracts={contracts}
                        showClient={false}
                        empty={{
                            title: 'Sin servicios contratados',
                            description: 'Asigna un servicio para programar sus cobros.',
                            action: (
                                <Link href={newContractHref} className={buttonClasses('primary')}>
                                    <Plus /> Contratar servicio
                                </Link>
                            ),
                        }}
                    />
                </Panel>
            ) : tab === 'charges' ? (
                <Panel
                    flush
                    title="Cobros"
                    actions={
                        <Button size="sm" onClick={() => setCreatingCharge(true)}>
                            <Plus /> Nuevo cobro
                        </Button>
                    }
                >
                    <ChargesTable
                        charges={charges}
                        showClient={false}
                        empty={{
                            title: 'Sin cobros',
                            description: 'Los cobros de sus servicios contratados y los cobros sueltos aparecerán aquí.',
                        }}
                    />
                </Panel>
            ) : (
                <Panel
                    flush
                    title="Cotizaciones"
                    actions={
                        <Link href={newQuoteHref} className={buttonClasses('primary', 'sm')}>
                            <Plus /> Nueva cotización
                        </Link>
                    }
                >
                    <QuotesTable
                        quotes={quotes}
                        showClient={false}
                        empty={{
                            title: 'Sin cotizaciones',
                            description: 'Las propuestas que prepares para este cliente aparecerán aquí.',
                        }}
                    />
                </Panel>
            )}

            <ManualChargeDialog
                open={creatingCharge}
                onOpenChange={setCreatingCharge}
                client={{
                    id: client.id,
                    name: client.name,
                    document: client.document,
                }}
            />

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title="¿Eliminar este cliente?"
                description={`«${client.name}» dejará de aparecer en los listados. Su historial se conserva en el registro de actividad.`}
                confirmLabel="Eliminar cliente"
                tone="danger"
                processing={deleting}
                onConfirm={destroy}
            />
        </>
    );
}
