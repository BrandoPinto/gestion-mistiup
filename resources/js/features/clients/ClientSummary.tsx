import { ActivityList, type ActivityItem } from '@/components/data/ActivityList';
import { DateDisplay } from '@/components/data/DateDisplay';
import { LedgerStrip } from '@/components/data/LedgerStrip';
import { MoneyByCurrency } from '@/components/data/MoneyByCurrency';
import { Panel } from '@/components/ui/Panel';
import { formatTimestamp } from '@/lib/dates';
import { formatPhone, whatsappUrl } from '@/lib/phone';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CLIENT_TYPE_LABEL, DOCUMENT_TYPE_LABEL, type ClientDetail, type ClientStats } from './types';

const FIELD_LABELS: Record<string, string> = {
    type: 'tipo',
    name: 'nombre',
    trade_name: 'nombre comercial',
    document_type: 'tipo de documento',
    document_number: 'documento',
    phone: 'teléfono',
    whatsapp: 'WhatsApp',
    email: 'correo',
    address: 'dirección',
    contact_name: 'contacto',
    notes: 'notas',
    status: 'estado',
};

/** Pestaña "Resumen" de la ficha: relación comercial, datos del cliente y actividad. */
type ClientSummaryProps = {
    client: ClientDetail;
    stats: ClientStats;
    activity: ActivityItem[];
};

export function ClientSummary({ client, stats, activity }: ClientSummaryProps) {
    return (
        <div className="flex flex-col gap-6">
            <LedgerStrip
                entries={[
                    {
                        label: 'Servicios activos',
                        value: (
                            <Link href={route('clients.show', { client: client.id, tab: 'services' })} className="numeric text-2xl font-semibold text-ink-900 hover:text-brand-700">
                                {stats.active_contracts}
                            </Link>
                        ),
                    },
                    {
                        label: 'Pendiente por cobrar',
                        value: <MoneyByCurrency items={stats.pending} />,
                    },
                    {
                        label: 'Cotizaciones abiertas',
                        value: (
                            <Link href={route('clients.show', { client: client.id, tab: 'quotes' })} className="numeric text-2xl font-semibold text-ink-900 hover:text-brand-700">
                                {stats.open_quotes}
                            </Link>
                        ),
                    },
                    {
                        label: 'Vencido',
                        value: <MoneyByCurrency items={stats.overdue} tone="danger" empty="Al día" />,
                    },
                    {
                        label: 'Próximo vencimiento',
                        value: stats.next_due_date ? (
                            <DateDisplay value={stats.next_due_date} format="short" relative className="text-lg font-semibold text-ink-900" />
                        ) : (
                            <span className="text-lg font-semibold text-ink-300">—</span>
                        ),
                    },
                ]}
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <Panel title="Datos del cliente">
                    <dl className="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                        <Item label="Tipo">{CLIENT_TYPE_LABEL[client.type]}</Item>
                        <Item label="Documento">{client.document_number ? `${DOCUMENT_TYPE_LABEL[client.document_type]} ${client.document_number}` : null}</Item>
                        {client.type === 'company' && <Item label="Nombre comercial">{client.trade_name}</Item>}
                        {client.type === 'company' && <Item label="Persona de contacto">{client.contact_name}</Item>}
                        <Item label="WhatsApp">
                            {client.whatsapp && (
                                <a href={whatsappUrl(client.whatsapp)} target="_blank" rel="noopener noreferrer" className="numeric text-brand-700 hover:underline">
                                    {formatPhone(client.whatsapp)}
                                </a>
                            )}
                        </Item>
                        <Item label="Teléfono">
                            {client.phone && (
                                <a href={`tel:${client.phone}`} className="numeric text-brand-700 hover:underline">
                                    {formatPhone(client.phone)}
                                </a>
                            )}
                        </Item>
                        <Item label="Correo">
                            {client.email && (
                                <a href={`mailto:${client.email}`} className="break-all text-brand-700 hover:underline">
                                    {client.email}
                                </a>
                            )}
                        </Item>
                        <Item label="Dirección">{client.address}</Item>
                        <Item label="Registrado">{formatTimestamp(client.created_at)}</Item>
                        <Item label="Última actualización">{formatTimestamp(client.updated_at)}</Item>
                    </dl>

                    {client.notes && (
                        <div className="mt-5 border-t border-line pt-4">
                            <p className="eyebrow mb-1.5">Notas internas</p>
                            <p className="text-base whitespace-pre-line text-ink-700">{client.notes}</p>
                        </div>
                    )}
                </Panel>

                <Panel title="Actividad" eyebrow="Historial">
                    <ActivityList items={activity} fieldLabels={FIELD_LABELS} />
                </Panel>
            </div>
        </div>
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
