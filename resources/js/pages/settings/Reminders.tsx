import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button, IconButton } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Field, Input, Select } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pause, Play, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Rule = {
    id: number;
    event: 'charge_due' | 'charge_overdue' | 'contract_end';
    days: number;
    label: string;
    is_active: boolean;
};

type RemindersProps = {
    rules: Rule[];
    events: { value: Rule['event']; label: string }[];
};

export default function SettingsReminders({ rules, events }: RemindersProps) {
    const form = useForm({ event: 'charge_due' as Rule['event'], days: '' });
    const [deleting, setDeleting] = useState<Rule | null>(null);

    function add(event: FormEvent) {
        event.preventDefault();
        form.post(route('settings.reminders.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('days'),
        });
    }

    function toggle(rule: Rule) {
        router.patch(route('settings.reminders.update', rule.id), { is_active: !rule.is_active }, { preserveScroll: true });
    }

    return (
        <>
            <Link href={route('settings.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900">
                <ArrowLeft className="size-4" aria-hidden /> Configuración
            </Link>
            <PageHeader
                title="Recordatorios"
                eyebrow="Configuración"
                description="Avisos internos en la campana. Cada aviso se emite una sola vez por cobro o contrato."
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div className="flex flex-col gap-6">
                    {events.map((event) => {
                        const eventRules = rules.filter((rule) => rule.event === event.value);

                        return (
                            <Panel key={event.value} title={event.label} flush>
                                {eventRules.length === 0 ? (
                                    <p className="px-4 py-4 text-base text-ink-500">Sin avisos para este caso.</p>
                                ) : (
                                    <ul className="divide-y divide-line">
                                        {eventRules.map((rule) => (
                                            <li key={rule.id} className="flex items-center gap-3 px-4 py-3">
                                                <span className="numeric flex-1 text-base text-ink-900">{rule.label}</span>
                                                {rule.is_active ? <Badge tone="success">Activo</Badge> : <Badge tone="neutral">Pausado</Badge>}
                                                <IconButton label={rule.is_active ? 'Pausar' : 'Activar'} onClick={() => toggle(rule)}>
                                                    {rule.is_active ? <Pause /> : <Play />}
                                                </IconButton>
                                                <IconButton label="Eliminar" onClick={() => setDeleting(rule)}>
                                                    <Trash2 />
                                                </IconButton>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        );
                    })}
                </div>

                <Panel title="Agregar aviso" className="self-start">
                    <form onSubmit={add} noValidate className="flex flex-col gap-4">
                        <Field label="Cuándo" error={form.errors.event}>
                            {({ id, invalid }) => (
                                <Select
                                    id={id}
                                    value={form.data.event}
                                    onChange={(event) => form.setData('event', event.target.value as Rule['event'])}
                                    invalid={invalid}
                                >
                                    {events.map((event) => (
                                        <option key={event.value} value={event.value}>
                                            {event.label}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </Field>
                        <Field
                            label="Días"
                            error={form.errors.days}
                            hint={form.data.event === 'charge_overdue' ? 'Días después del vencimiento.' : 'Días antes de la fecha.'}
                        >
                            {({ id, invalid, describedBy }) => (
                                <Input
                                    id={id}
                                    inputMode="numeric"
                                    value={form.data.days}
                                    onChange={(event) => form.setData('days', event.target.value.replace(/\D/g, '').slice(0, 3))}
                                    invalid={invalid}
                                    aria-describedby={describedBy}
                                    className="numeric w-28"
                                />
                            )}
                        </Field>
                        <Button type="submit" loading={form.processing} className="self-start">
                            <Plus /> Agregar
                        </Button>
                    </form>
                </Panel>
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="¿Eliminar este aviso?"
                description={deleting ? `Dejará de emitirse el aviso "${deleting.label}". Si solo quieres dejarlo de usar un tiempo, puedes pausarlo.` : ''}
                confirmLabel="Eliminar"
                tone="danger"
                onConfirm={() =>
                    deleting &&
                    router.delete(route('settings.reminders.destroy', deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}
