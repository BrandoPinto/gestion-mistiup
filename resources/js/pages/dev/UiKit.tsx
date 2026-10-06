import { DateDisplay } from '@/components/data/DateDisplay';
import { LedgerStrip } from '@/components/data/LedgerStrip';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button, IconButton } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog } from '@/components/ui/Dialog';
import { DropdownItem, DropdownMenu, DropdownSeparator } from '@/components/ui/DropdownMenu';
import { EmptyState } from '@/components/ui/EmptyState';
import { Checkbox, Field, Input, Select, Textarea } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { Skeleton } from '@/components/ui/Skeleton';
import { Table, TBody, Td, Th, THead, Tr } from '@/components/ui/Table';
import { Tooltip } from '@/components/ui/Tooltip';
import { Copy, Eye, MoreHorizontal, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';

/** Página solo-local para revisar el Design System. No se registra en producción. */
export default function UiKit() {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <>
            <PageHeader
                title="Design System"
                eyebrow="Solo desarrollo"
                description="Referencia viva de componentes y tokens."
                actions={
                    <>
                        <Button variant="secondary">
                            <Eye /> Vista previa
                        </Button>
                        <Button>
                            <Plus /> Nuevo cliente
                        </Button>
                    </>
                }
            />

            <div className="flex flex-col gap-6">
                <Section title="Colores">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                        <Swatch name="navy-900" className="bg-navy-900" />
                        <Swatch name="brand-600" className="bg-brand-600" />
                        <Swatch name="cream-100" className="bg-cream-100" />
                        <Swatch name="success-600" className="bg-success-600" />
                        <Swatch name="warning-600" className="bg-warning-600" />
                        <Swatch name="danger-600" className="bg-danger-600" />
                    </div>
                </Section>

                <Section title="Métricas (franja contable)">
                    <LedgerStrip
                        entries={[
                            {
                                label: 'Cobrado este mes',
                                value: (
                                    <>
                                        <MoneyDisplay
                                            value={{
                                                amount: '12450.00',
                                                currency: 'PEN',
                                            }}
                                            size="xl"
                                        />
                                        <MoneyDisplay
                                            value={{
                                                amount: '850.00',
                                                currency: 'USD',
                                            }}
                                            size="base"
                                            tone="muted"
                                        />
                                    </>
                                ),
                            },
                            {
                                label: 'Pendiente',
                                value: (
                                    <MoneyDisplay
                                        value={{
                                            amount: '6380.50',
                                            currency: 'PEN',
                                        }}
                                        size="xl"
                                    />
                                ),
                            },
                            {
                                label: 'Vencido',
                                value: (
                                    <MoneyDisplay
                                        value={{
                                            amount: '1200.00',
                                            currency: 'PEN',
                                        }}
                                        size="xl"
                                        tone="danger"
                                    />
                                ),
                                hint: '3 cobros',
                            },
                            {
                                label: 'Cotizaciones abiertas',
                                value: <span className="numeric text-2xl font-semibold">7</span>,
                            },
                        ]}
                    />
                </Section>

                <Section title="Botones">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button>Guardar</Button>
                        <Button variant="secondary">Cancelar</Button>
                        <Button variant="ghost">Ver detalle</Button>
                        <Button variant="danger">Anular pago</Button>
                        <Button loading>Guardando</Button>
                        <Button size="sm" variant="secondary">
                            <Copy /> Copiar enlace
                        </Button>
                        <Tooltip content="Editar">
                            <IconButton label="Editar" variant="secondary">
                                <Pencil />
                            </IconButton>
                        </Tooltip>
                        <Button variant="secondary" onClick={() => toast.success('Enlace copiado')}>
                            Probar toast
                        </Button>
                    </div>
                </Section>

                <Section title="Formulario">
                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Razón social" required>
                            {({ id }) => <Input id={id} placeholder="Empresa ABC S.A.C." />}
                        </Field>
                        <Field label="RUC" error="El RUC debe tener 11 dígitos.">
                            {({ id, invalid, describedBy }) => <Input id={id} defaultValue="2012345" invalid={invalid} aria-describedby={describedBy} />}
                        </Field>
                        <Field label="Moneda" hint="Se usará por defecto en las cotizaciones.">
                            {({ id, describedBy }) => (
                                <Select id={id} aria-describedby={describedBy}>
                                    <option>PEN — Soles</option>
                                    <option>USD — Dólares</option>
                                </Select>
                            )}
                        </Field>
                        <Field label="Notas">{({ id }) => <Textarea id={id} placeholder="Notas internas" />}</Field>
                        <Checkbox label="Cliente activo" defaultChecked />
                    </div>
                </Section>

                <Panel
                    title="Tabla"
                    eyebrow="Ejemplo"
                    flush
                    actions={
                        <Button size="sm" variant="secondary">
                            Exportar
                        </Button>
                    }
                >
                    <Table>
                        <THead>
                            <tr>
                                <Th>Cliente</Th>
                                <Th>Estado</Th>
                                <Th>Próximo cobro</Th>
                                <Th align="right">Pendiente</Th>
                                <Th className="w-12">
                                    <span className="sr-only">Acciones</span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            <SampleRow
                                name="Empresa ABC S.A.C."
                                doc="RUC 20123456789"
                                badge={<Badge tone="success">Pagado</Badge>}
                                date="2026-11-10"
                                amount="0.00"
                            />
                            <SampleRow
                                name="Comercial Los Andes"
                                doc="RUC 20601234567"
                                badge={<Badge tone="warning">Parcial</Badge>}
                                date="2026-10-20"
                                amount="1200.00"
                            />
                            <SampleRow
                                name="Clínica San Pedro"
                                doc="RUC 20512345678"
                                badge={<Badge tone="danger">Vencido</Badge>}
                                date="2026-09-28"
                                amount="350.00"
                            />
                            <SampleRow
                                name="María Torres"
                                doc="DNI 45678912"
                                badge={<Badge tone="brand">Enviada</Badge>}
                                date="2026-10-04"
                                amount="80.00"
                                currency="USD"
                            />
                        </TBody>
                    </Table>
                </Panel>

                <Section title="Overlays">
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => setDialogOpen(true)}>
                            Abrir modal
                        </Button>
                        <Button variant="secondary" onClick={() => setConfirmOpen(true)}>
                            Confirmación destructiva
                        </Button>
                        <DropdownMenu trigger={<Button variant="secondary">Menú contextual</Button>} align="start">
                            <DropdownItem icon={Eye}>Ver</DropdownItem>
                            <DropdownItem icon={Pencil}>Editar</DropdownItem>
                            <DropdownSeparator />
                            <DropdownItem icon={Trash2} tone="danger">
                                Eliminar
                            </DropdownItem>
                        </DropdownMenu>
                    </div>
                </Section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Section title="Skeleton">
                        <div className="flex flex-col gap-3">
                            <Skeleton className="h-4 w-1/3" />
                            <Skeleton className="h-9 w-full" />
                            <Skeleton className="h-9 w-full" />
                            <Skeleton className="h-9 w-2/3" />
                        </div>
                    </Section>
                    <Panel>
                        <EmptyState
                            icon={Users}
                            title="Aún no tienes clientes"
                            description="Registra tu primer cliente para asignarle servicios y cobros."
                            action={
                                <Button>
                                    <Plus /> Nuevo cliente
                                </Button>
                            }
                        />
                    </Panel>
                </div>
            </div>

            <Dialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                title="Registrar pago"
                description="Cobro Hosting anual · Empresa ABC S.A.C."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={() => setDialogOpen(false)}>Registrar pago</Button>
                    </>
                }
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Monto">{({ id }) => <Input id={id} inputMode="decimal" defaultValue="350.00" className="numeric text-right" />}</Field>
                    <Field label="Fecha">{({ id }) => <Input id={id} type="date" defaultValue="2026-10-04" />}</Field>
                </div>
            </Dialog>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="¿Anular este pago?"
                description="El pago quedará en el historial como anulado y el saldo del cobro se recalculará."
                confirmLabel="Anular pago"
                tone="danger"
                onConfirm={() => setConfirmOpen(false)}
            />
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return <Panel title={title}>{children}</Panel>;
}

function Swatch({ name, className }: { name: string; className: string }) {
    return (
        <div className="overflow-hidden rounded-md border border-line">
            <div className={`h-14 ${className}`} />
            <p className="px-2 py-1.5 font-mono text-xs text-ink-700">{name}</p>
        </div>
    );
}

type SampleRowProps = {
    name: string;
    doc: string;
    badge: ReactNode;
    date: string;
    amount: string;
    currency?: 'PEN' | 'USD';
};

function SampleRow({ name, doc, badge, date, amount, currency = 'PEN' }: SampleRowProps) {
    return (
        <Tr>
            <Td>
                <p className="font-medium text-ink-900">{name}</p>
                <p className="numeric text-xs text-ink-500">{doc}</p>
            </Td>
            <Td>{badge}</Td>
            <Td>
                <DateDisplay value={date} relative />
            </Td>
            <Td numeric>
                <MoneyDisplay value={{ amount, currency }} />
            </Td>
            <Td>
                <DropdownMenu
                    trigger={
                        <IconButton label="Acciones">
                            <MoreHorizontal />
                        </IconButton>
                    }
                >
                    <DropdownItem icon={Eye}>Ver ficha</DropdownItem>
                    <DropdownItem icon={Pencil}>Editar</DropdownItem>
                </DropdownMenu>
            </Td>
        </Tr>
    );
}
