import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';

type BillingProps = {
    charge_lead_days: number;
};

export default function SettingsBilling({ charge_lead_days }: BillingProps) {
    const form = useForm({ charge_lead_days: String(charge_lead_days) });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(route('settings.billing.update'), { preserveScroll: true });
    }

    return (
        <>
            <Link href={route('settings.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900">
                <ArrowLeft className="size-4" aria-hidden /> Configuración
            </Link>
            <PageHeader title="Cobros" eyebrow="Configuración" />

            <Panel title="Generación automática" className="max-w-xl">
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <Field
                        label="Días de anticipación"
                        error={form.errors.charge_lead_days}
                        hint="El cobro de cada ciclo se crea cuando faltan estos días para su vencimiento (entre 1 y 180). Cambiarlo no altera los cobros ya generados."
                    >
                        {({ id, invalid, describedBy }) => (
                            <div className="flex items-center gap-2">
                                <Input
                                    id={id}
                                    inputMode="numeric"
                                    value={form.data.charge_lead_days}
                                    onChange={(event) => form.setData('charge_lead_days', event.target.value.replace(/\D/g, '').slice(0, 3))}
                                    invalid={invalid}
                                    aria-describedby={describedBy}
                                    className="numeric w-24"
                                />
                                <span className="text-base text-ink-500">días</span>
                            </div>
                        )}
                    </Field>
                    <Button type="submit" loading={form.processing} className="self-start">
                        Guardar
                    </Button>
                </form>
            </Panel>
        </>
    );
}
