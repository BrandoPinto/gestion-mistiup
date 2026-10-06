import { ActivityList, type ActivityItem } from '@/components/data/ActivityList';
import { DateDisplay } from '@/components/data/DateDisplay';
import { LedgerStrip } from '@/components/data/LedgerStrip';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Button, IconButton } from '@/components/ui/Button';
import { DropdownItem, DropdownMenu } from '@/components/ui/DropdownMenu';
import { Panel } from '@/components/ui/Panel';
import { AdjustAmountDialog } from '@/features/charges/AdjustAmountDialog';
import { ChargeStatusBadge } from '@/features/charges/ChargeStatusBadge';
import { ReasonDialog } from '@/features/charges/ReasonDialog';
import { RegisterPaymentDialog } from '@/features/charges/RegisterPaymentDialog';
import type { ChargeDetail, PaymentMethodOption } from '@/features/charges/types';
import { PaymentList } from '@/features/payments/PaymentList';
import type { PaymentItem } from '@/features/payments/types';
import { formatDate, formatTimestamp } from '@/lib/dates';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Ban, MoreHorizontal, Pencil, Wallet } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type ChargesShowProps = {
    charge: ChargeDetail;
    payments: PaymentItem[];
    paymentMethods: PaymentMethodOption[];
    today: string;
    activity: ActivityItem[];
};

export default function ChargesShow({ charge, payments, paymentMethods, today, activity }: ChargesShowProps) {
    const [paying, setPaying] = useState(false);
    const [adjusting, setAdjusting] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [voiding, setVoiding] = useState<PaymentItem | null>(null);
    const hasMenu = charge.can.adjust || charge.can.cancel;

    return (
        <>
            <Head title={`${charge.description} · ${charge.client.name}`} />

            <Link
                href={route('clients.show', {
                    client: charge.client.id,
                    tab: 'charges',
                })}
                className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900"
            >
                <ArrowLeft className="size-4" aria-hidden /> {charge.client.name}
            </Link>

            <header className="mb-6 flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    <p className="eyebrow mb-1">
                        Cobro
                        {charge.cycle_number ? ` · ciclo ${charge.cycle_number}` : ''}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink-900">{charge.description}</h1>
                        <ChargeStatusBadge status={charge.display_status} daysOverdue={charge.days_overdue} />
                    </div>
                    {charge.period_start && charge.period_end && (
                        <p className="numeric mt-1 text-base text-ink-500">
                            Periodo {formatDate(charge.period_start)} – {formatDate(charge.period_end)}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    {charge.can.pay && (
                        <Button onClick={() => setPaying(true)}>
                            <Wallet /> Registrar pago
                        </Button>
                    )}
                    {hasMenu && (
                        <DropdownMenu
                            trigger={
                                <IconButton label="Más acciones" variant="secondary">
                                    <MoreHorizontal />
                                </IconButton>
                            }
                        >
                            {charge.can.adjust && (
                                <DropdownItem icon={Pencil} onSelect={() => setAdjusting(true)}>
                                    Ajustar importe
                                </DropdownItem>
                            )}
                            {charge.can.cancel && (
                                <DropdownItem icon={Ban} tone="danger" onSelect={() => setCancelling(true)}>
                                    Cancelar cobro
                                </DropdownItem>
                            )}
                        </DropdownMenu>
                    )}
                </div>
            </header>

            <LedgerStrip
                entries={[
                    {
                        label: 'Importe',
                        value: <MoneyDisplay value={charge.amount} size="xl" tone={charge.status === 'cancelled' ? 'muted' : 'default'} />,
                    },
                    {
                        label: 'Pagado',
                        value: <MoneyDisplay value={charge.amount_paid} size="xl" tone={charge.amount_paid.amount === '0.00' ? 'muted' : 'success'} />,
                    },
                    {
                        label: 'Saldo',
                        value:
                            charge.status === 'cancelled' ? (
                                <span className="text-2xl font-semibold text-ink-300">—</span>
                            ) : (
                                <MoneyDisplay value={charge.balance} size="xl" tone={charge.display_status === 'overdue' ? 'danger' : 'default'} />
                            ),
                    },
                    {
                        label: charge.status === 'paid' ? 'Pagado el' : 'Vence',
                        value:
                            charge.status === 'paid' && charge.paid_on ? (
                                <DateDisplay value={charge.paid_on} format="short" className="text-lg font-semibold text-ink-900" />
                            ) : (
                                <DateDisplay
                                    value={charge.due_date}
                                    format="short"
                                    relative={charge.status !== 'cancelled'}
                                    className="text-lg font-semibold text-ink-900"
                                />
                            ),
                    },
                ]}
            />

            {charge.status === 'cancelled' && (
                <div className="mt-4 rounded-md border border-line bg-cream-100 px-4 py-3 text-base text-ink-700">
                    Cancelado {charge.cancelled_at && `el ${formatTimestamp(charge.cancelled_at)}`}
                    {charge.cancel_reason && ` · ${charge.cancel_reason}`}
                </div>
            )}

            <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <Panel title="Pagos" eyebrow="Historial">
                    <PaymentList payments={payments} onVoid={setVoiding} />
                </Panel>

                <div className="flex flex-col gap-6">
                    <Panel title="Detalle">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Item label="Cliente">
                                <Link href={route('clients.show', charge.client.id)} className="text-brand-700 hover:underline">
                                    {charge.client.name}
                                </Link>
                            </Item>
                            <Item label="Servicio">
                                {charge.contract && (
                                    <Link href={route('contracts.show', charge.contract.id)} className="text-brand-700 hover:underline">
                                        {charge.contract.name}
                                    </Link>
                                )}
                            </Item>
                            <Item label="Op. gravada">
                                <MoneyDisplay value={charge.base_amount} size="sm" />
                            </Item>
                            <Item label={`IGV ${Number(charge.tax_rate)}%`}>
                                <MoneyDisplay value={charge.tax_amount} size="sm" />
                            </Item>
                            <Item label="Vencimiento">
                                <DateDisplay value={charge.due_date} format="short" />
                            </Item>
                            <Item label="Registrado">{formatTimestamp(charge.created_at)}</Item>
                        </dl>
                        {charge.notes && (
                            <div className="mt-4 border-t border-line pt-4">
                                <p className="eyebrow mb-1.5">Notas</p>
                                <p className="text-base whitespace-pre-line text-ink-700">{charge.notes}</p>
                            </div>
                        )}
                    </Panel>

                    <Panel title="Actividad" eyebrow="Historial">
                        <ActivityList items={activity} />
                    </Panel>
                </div>
            </div>

            {charge.can.pay && <RegisterPaymentDialog open={paying} onOpenChange={setPaying} charge={charge} paymentMethods={paymentMethods} today={today} />}
            {charge.can.adjust && <AdjustAmountDialog open={adjusting} onOpenChange={setAdjusting} charge={charge} />}
            <ReasonDialog
                open={cancelling}
                onOpenChange={setCancelling}
                title="¿Cancelar este cobro?"
                description="Dejará de figurar como deuda del cliente. Queda en el historial como cancelado."
                confirmLabel="Cancelar cobro"
                action={route('charges.cancel', charge.id)}
            />
            <ReasonDialog
                open={voiding !== null}
                onOpenChange={(open) => !open && setVoiding(null)}
                title="¿Anular este pago?"
                description="El pago quedará en el historial como anulado y el saldo del cobro se recalculará."
                confirmLabel="Anular pago"
                action={voiding ? route('payments.void', voiding.id) : ''}
                reasonRequired
            />
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
