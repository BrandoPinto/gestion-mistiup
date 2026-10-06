import { ActivityList, type ActivityItem } from '@/components/data/ActivityList';
import { DateDisplay } from '@/components/data/DateDisplay';
import { LedgerStrip } from '@/components/data/LedgerStrip';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { buttonClasses, IconButton } from '@/components/ui/Button';
import { DropdownItem, DropdownMenu } from '@/components/ui/DropdownMenu';
import { ChargesTable } from '@/features/charges/ChargesTable';
import type { ChargeListItem } from '@/features/charges/types';
import { Panel } from '@/components/ui/Panel';
import { CancelContractDialog } from '@/features/contracts/CancelContractDialog';
import { ContractStatusBadge } from '@/features/contracts/ContractStatusBadge';
import { ScheduleList } from '@/features/contracts/ScheduleList';
import type { ContractDetail, ScheduleItem } from '@/features/contracts/types';
import { formatTimestamp } from '@/lib/dates';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Ban, MoreHorizontal, Pencil } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type ContractsShowProps = {
    contract: ContractDetail;
    upcoming: ScheduleItem[];
    charges: ChargeListItem[];
    activity: ActivityItem[];
};

const FIELD_LABELS: Record<string, string> = {
    name: 'nombre',
    description: 'descripción',
    price: 'precio',
    currency: 'moneda',
    billing_type: 'modalidad',
    interval_unit: 'frecuencia',
    interval_count: 'frecuencia',
    start_date: 'inicio',
    term_months: 'duración',
    end_date: 'fin',
    next_cycle_number: 'primer cobro',
    next_charge_date: 'fecha de cobro',
    service_id: 'servicio del catálogo',
    notes: 'notas',
};

export default function ContractsShow({ contract, upcoming, charges, activity }: ContractsShowProps) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const isActive = contract.status === 'active';

    return (
        <>
            <Head title={`${contract.name} · ${contract.client.name}`} />

            <Link
                href={route('clients.show', {
                    client: contract.client.id,
                    tab: 'services',
                })}
                className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900"
            >
                <ArrowLeft className="size-4" aria-hidden /> {contract.client.name}
            </Link>

            <header className="mb-6 flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    <p className="eyebrow mb-1">Servicio contratado</p>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink-900">{contract.name}</h1>
                        <ContractStatusBadge status={contract.status} />
                    </div>
                    {contract.description && <p className="mt-1 max-w-2xl text-base text-ink-500">{contract.description}</p>}
                </div>

                {isActive && (
                    <div className="flex items-center gap-2">
                        <Link href={route('contracts.edit', contract.id)} className={buttonClasses('secondary')}>
                            <Pencil /> Editar
                        </Link>
                        <DropdownMenu
                            trigger={
                                <IconButton label="Más acciones" variant="secondary">
                                    <MoreHorizontal />
                                </IconButton>
                            }
                        >
                            <DropdownItem icon={Ban} tone="danger" onSelect={() => setCancelOpen(true)}>
                                Cancelar contrato
                            </DropdownItem>
                        </DropdownMenu>
                    </div>
                )}
            </header>

            <LedgerStrip
                entries={[
                    {
                        label: contract.billing_type === 'recurring' ? 'Precio por ciclo' : 'Precio',
                        value: <MoneyDisplay value={contract.price} size="xl" />,
                    },
                    {
                        label: 'Modalidad',
                        value: <span className="text-lg font-semibold text-ink-900">{contract.billing_label}</span>,
                    },
                    {
                        label: 'Próximo cobro',
                        value: contract.next_charge_date ? (
                            <DateDisplay value={contract.next_charge_date} format="short" relative className="text-lg font-semibold text-ink-900" />
                        ) : (
                            <span className="text-lg font-semibold text-ink-300">—</span>
                        ),
                    },
                    {
                        label: 'Vigencia',
                        value: (
                            <span className="text-lg font-semibold text-ink-900">
                                {contract.end_date ? (
                                    <DateDisplay value={contract.end_date} format="short" />
                                ) : contract.billing_type === 'recurring' ? (
                                    'Sin fin'
                                ) : (
                                    '—'
                                )}
                            </span>
                        ),
                        hint: contract.total_cycles && contract.billing_type === 'recurring' ? `${contract.total_cycles} ciclos` : undefined,
                    },
                ]}
            />

            {contract.status === 'cancelled' && (
                <div className="mt-4 rounded-md border border-danger-600/20 bg-danger-50 px-4 py-3 text-base text-danger-700">
                    Cancelado {contract.cancelled_at && `el ${formatTimestamp(contract.cancelled_at)}`}
                    {contract.cancel_reason && ` · ${contract.cancel_reason}`}
                </div>
            )}

            <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div className="flex flex-col gap-6">
                    <Panel title="Próximos cobros" eyebrow="Calendario">
                        {upcoming.length > 0 ? (
                            <ScheduleList items={upcoming} price={contract.price} totalCycles={contract.total_cycles} />
                        ) : (
                            <p className="text-base text-ink-500">Este contrato no tiene cobros pendientes de generar.</p>
                        )}
                    </Panel>

                    <Panel title="Cobros generados" eyebrow="Historial" flush>
                        <ChargesTable
                            charges={charges}
                            showClient={false}
                            empty={{
                                title: 'Aún no hay cobros',
                                description: 'Se generan automáticamente con anticipación a cada vencimiento.',
                            }}
                        />
                    </Panel>
                </div>

                <div className="flex flex-col gap-6">
                    <Panel title="Condiciones">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Item label="Cliente">
                                <Link href={route('clients.show', contract.client.id)} className="text-brand-700 hover:underline">
                                    {contract.client.name}
                                </Link>
                            </Item>
                            <Item label="Catálogo">{contract.service?.name}</Item>
                            {contract.origin_quote && (
                                <Item label="Origen">
                                    <Link href={route('quotes.show', contract.origin_quote.id)} className="numeric text-brand-700 hover:underline">
                                        {contract.origin_quote.number}
                                    </Link>
                                </Item>
                            )}
                            <Item label="Inicio">
                                <DateDisplay value={contract.start_date} format="short" />
                            </Item>
                            <Item label="Registrado">{formatTimestamp(contract.created_at)}</Item>
                        </dl>
                        {contract.notes && (
                            <div className="mt-4 border-t border-line pt-4">
                                <p className="eyebrow mb-1.5">Notas internas</p>
                                <p className="text-base whitespace-pre-line text-ink-700">{contract.notes}</p>
                            </div>
                        )}
                    </Panel>

                    <Panel title="Actividad" eyebrow="Historial">
                        <ActivityList items={activity} fieldLabels={FIELD_LABELS} />
                    </Panel>
                </div>
            </div>

            {isActive && <CancelContractDialog open={cancelOpen} onOpenChange={setCancelOpen} contract={contract} />}
        </>
    );
}

function Item({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="eyebrow mb-1">{label}</dt>
            <dd className="text-base text-ink-900">{children || <span className="text-ink-400">—</span>}</dd>
        </div>
    );
}
