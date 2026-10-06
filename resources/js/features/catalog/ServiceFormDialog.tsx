import { MoneyInput } from '@/components/data/MoneyInput';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Checkbox, Field, Input, Textarea } from '@/components/ui/Field';
import { RecurrenceFields } from '@/features/billing/RecurrenceFields';
import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import { EMPTY_SERVICE, toFormValues, type CatalogService, type ServiceFormValues } from './types';

type ServiceFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** null = nuevo servicio. */
    service: CatalogService | null;
};

const FORM_ID = 'service-form';

export function ServiceFormDialog({ open, onOpenChange, service }: ServiceFormDialogProps) {
    const form = useForm<ServiceFormValues>(EMPTY_SERVICE);
    const { data, errors } = form;

    // Cargar los valores del servicio cada vez que se abre el modal.
    useEffect(() => {
        if (open) {
            form.setData(service ? toFormValues(service) : EMPTY_SERVICE);
            form.clearErrors();
        }
    }, [open, service?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (service) {
            form.put(route('services.update', service.id), options);
        } else {
            form.post(route('services.store'), options);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !form.processing && onOpenChange(next)}
            title={service ? 'Editar servicio' : 'Nuevo servicio'}
            description="Los valores del catálogo son sugerencias: al contratar o cotizar se pueden ajustar."
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={form.processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={FORM_ID} loading={form.processing}>
                        {service ? 'Guardar cambios' : 'Agregar al catálogo'}
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} noValidate className="flex flex-col gap-5">
                <Field label="Nombre" error={errors.name} required>
                    {({ id, invalid, describedBy }) => (
                        <Input id={id} value={data.name} onChange={(event) => form.setData('name', event.target.value)} invalid={invalid} aria-describedby={describedBy} placeholder="Hosting empresarial" />
                    )}
                </Field>

                <Field label="Descripción" error={errors.description} hint="Se usará como texto sugerido en cotizaciones.">
                    {({ id, invalid, describedBy }) => (
                        <Textarea id={id} rows={2} value={data.description} onChange={(event) => form.setData('description', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                    )}
                </Field>

                <Field label="Precio sugerido" error={errors.default_price ?? errors.default_currency} hint="Con IGV incluido. Opcional si el precio varía por proyecto.">
                    {({ id, invalid, describedBy }) => (
                        <MoneyInput
                            id={id}
                            amount={data.default_price}
                            currency={data.default_currency}
                            onAmountChange={(amount) => form.setData('default_price', amount)}
                            onCurrencyChange={(currency) => form.setData('default_currency', currency)}
                            invalid={invalid}
                            describedBy={describedBy}
                        />
                    )}
                </Field>

                <div className="flex flex-col gap-1.5">
                    <span className="text-sm font-medium text-ink-700">Modalidad sugerida</span>
                    <RecurrenceFields
                        value={{
                            billing_type: data.default_billing_type,
                            interval_unit: data.default_interval_unit,
                            interval_count: data.default_interval_count,
                        }}
                        onChange={(recurrence) =>
                            form.setData((current) => ({
                                ...current,
                                default_billing_type: recurrence.billing_type,
                                default_interval_unit: recurrence.interval_unit,
                                default_interval_count: recurrence.interval_count,
                            }))
                        }
                        error={errors.default_interval_count ?? errors.default_interval_unit ?? errors.default_billing_type}
                    />
                </div>

                <Checkbox label="Disponible para contratar y cotizar" checked={data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />
            </form>
        </Dialog>
    );
}
