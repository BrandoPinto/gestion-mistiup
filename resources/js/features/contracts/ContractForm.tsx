import { MoneyInput } from '@/components/data/MoneyInput';
import { Button, buttonClasses } from '@/components/ui/Button';
import { Checkbox, Field, Input, Select, Textarea } from '@/components/ui/Field';
import { FormSection } from '@/components/ui/FormSection';
import { RecurrenceFields } from '@/features/billing/RecurrenceFields';
import type { CatalogService } from '@/features/catalog/types';
import { ClientSelector, type ClientOption } from '@/features/clients/ClientSelector';
import { formatDate } from '@/lib/dates';
import { Link, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { SchedulePanel } from './SchedulePanel';
import type { ContractFormValues, ScheduleItem } from './types';
import { useSchedulePreview } from './useSchedulePreview';

type ContractFormProps = {
    initialValues: ContractFormValues;
    initialClient: ClientOption | null;
    catalog: CatalogService[];
    method: 'post' | 'put';
    action: string;
    cancelHref: string;
    submitLabel: string;
    /** En edición el cliente no cambia. */
    lockClient?: boolean;
    /** Con cobros generados, moneda/modalidad/vigencia/primer cobro son de solo lectura. */
    scheduleLocked?: boolean;
};

export function ContractForm({ initialValues, initialClient, catalog, method, action, cancelHref, submitLabel, lockClient = false, scheduleLocked = false }: ContractFormProps) {
    const form = useForm<ContractFormValues>(initialValues);
    const { data, errors } = form;
    const [client, setClient] = useState<ClientOption | null>(initialClient);
    // En edición se respetan el ciclo y la fecha guardados hasta que el usuario cambie algo que los mueva.
    const [cycleTouched, setCycleTouched] = useState(method === 'put');
    const [dateTouched, setDateTouched] = useState(method === 'put');
    const { preview, error: previewError, loading: previewLoading } = useSchedulePreview(data);

    const cycleOptions: ScheduleItem[] = preview?.cycles ?? [];
    const selectedCycle = cycleOptions.find((item) => String(item.cycle) === data.first_cycle);

    // Mantener coherentes "primer cobro" y su fecha con el calendario recalculado.
    useEffect(() => {
        if (!preview) return;

        const valid = preview.cycles.some((item) => String(item.cycle) === data.first_cycle);
        const nextCycle = !cycleTouched || !valid ? String(preview.suggested_first_cycle ?? '') : data.first_cycle;
        const cycleItem = preview.cycles.find((item) => String(item.cycle) === nextCycle);

        form.setData((current) => ({
            ...current,
            first_cycle: nextCycle,
            first_charge_date: dateTouched && current.first_charge_date ? current.first_charge_date : (cycleItem?.due_date ?? current.first_charge_date),
        }));
    }, [preview]);

    function applyCatalogService(serviceId: string) {
        const service = catalog.find((item) => String(item.id) === serviceId);

        if (!service) {
            form.setData('service_id', null);
            return;
        }

        if (scheduleLocked) {
            // Con el calendario fijo, el catálogo solo puede cambiar nombre y descripción.
            form.setData((current) => ({ ...current, service_id: service.id, name: service.name, description: service.description ?? '' }));
            return;
        }

        form.setData((current) => ({
            ...current,
            service_id: service.id,
            name: service.name,
            description: service.description ?? '',
            currency: service.default_currency,
            price: service.default_price?.amount ?? current.price,
            billing_type: service.default_billing_type,
            interval_unit: service.default_interval_unit ?? '',
            interval_count: service.default_interval_count ? String(service.default_interval_count) : '',
            has_term: service.default_billing_type === 'recurring' ? current.has_term : false,
        }));
    }

    function chooseCycle(cycle: string) {
        setCycleTouched(true);
        const item = cycleOptions.find((option) => String(option.cycle) === cycle);
        form.setData((current) => ({
            ...current,
            first_cycle: cycle,
            first_charge_date: dateTouched ? current.first_charge_date : (item?.due_date ?? current.first_charge_date),
        }));
    }

    function resetChargeDate() {
        setDateTouched(false);
        if (selectedCycle) form.setData('first_charge_date', selectedCycle.due_date);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(method, action, { preserveScroll: true });
    }

    const isRecurring = data.billing_type === 'recurring';
    const dateMoved = selectedCycle !== undefined && data.first_charge_date !== '' && data.first_charge_date !== selectedCycle.due_date;

    return (
        <form onSubmit={submit} noValidate className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div className="flex min-w-0 flex-col">
                <FormSection title="Cliente y servicio" description="El servicio del catálogo solo precarga valores: todo se puede ajustar para este cliente.">
                    <Field label="Cliente" error={errors.client_id} required className="sm:col-span-2">
                        {({ id, invalid, describedBy }) => (
                            <ClientSelector
                                id={id}
                                value={client}
                                disabled={lockClient}
                                invalid={invalid}
                                describedBy={describedBy}
                                onChange={(option) => {
                                    setClient(option);
                                    form.setData('client_id', option?.id ?? null);
                                }}
                            />
                        )}
                    </Field>

                    <Field label="Servicio del catálogo" error={errors.service_id} hint="Opcional." className="sm:col-span-2">
                        {({ id, invalid, describedBy }) => (
                            <Select id={id} value={data.service_id ? String(data.service_id) : ''} onChange={(event) => applyCatalogService(event.target.value)} invalid={invalid} aria-describedby={describedBy}>
                                <option value="">Personalizado (sin catálogo)</option>
                                {catalog.map((service) => (
                                    <option key={service.id} value={service.id}>
                                        {service.name} — {service.billing_label}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>

                    <Field label="Nombre del servicio" error={errors.name} required className="sm:col-span-2">
                        {({ id, invalid, describedBy }) => (
                            <Input id={id} value={data.name} onChange={(event) => form.setData('name', event.target.value)} invalid={invalid} aria-describedby={describedBy} placeholder="Hosting empresarial" />
                        )}
                    </Field>

                    <Field label="Descripción" error={errors.description} className="sm:col-span-2">
                        {({ id, invalid, describedBy }) => (
                            <Textarea id={id} rows={2} value={data.description} onChange={(event) => form.setData('description', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                        )}
                    </Field>
                </FormSection>

                <FormSection title="Precio y modalidad" description="Precio con IGV incluido. En los recurrentes, es el importe de cada ciclo." columns={1}>
                    <Field label={isRecurring ? 'Precio por ciclo' : 'Precio'} error={errors.price ?? errors.currency} required className="max-w-xs">
                        {({ id, invalid, describedBy }) => (
                            <MoneyInput
                                id={id}
                                amount={data.price}
                                currency={data.currency}
                                onAmountChange={(price) => form.setData('price', price)}
                                onCurrencyChange={scheduleLocked ? undefined : (currency) => form.setData('currency', currency)}
                                invalid={invalid}
                                describedBy={describedBy}
                            />
                        )}
                    </Field>

                    {scheduleLocked ? (
                        <div className="flex items-start gap-2 rounded-md border border-line bg-cream-50 px-3 py-2.5 text-sm text-ink-700">
                            <Lock className="mt-0.5 size-4 shrink-0 text-ink-500" aria-hidden />
                            <span>
                                Este servicio ya tiene cobros generados: la moneda, la modalidad, la vigencia y el calendario no se pueden cambiar. Un nuevo precio se aplicará a los cobros que aún no se generaron.
                            </span>
                        </div>
                    ) : (
                        <RecurrenceFields
                            value={{ billing_type: data.billing_type, interval_unit: data.interval_unit, interval_count: data.interval_count }}
                            onChange={(recurrence) =>
                                form.setData((current) => ({
                                    ...current,
                                    billing_type: recurrence.billing_type,
                                    interval_unit: recurrence.interval_unit,
                                    interval_count: recurrence.interval_count,
                                    has_term: recurrence.billing_type === 'recurring' ? current.has_term : false,
                                }))
                            }
                            error={errors.interval_count ?? errors.interval_unit ?? errors.billing_type}
                        />
                    )}
                </FormSection>

                {!scheduleLocked && (
                    <>
                        <FormSection title="Vigencia" description={isRecurring ? 'El inicio es el ancla: cada ciclo vence en la misma fecha del periodo.' : 'Fecha de inicio del servicio.'}>
                            <Field label="Fecha de inicio" error={errors.start_date} required>
                                {({ id, invalid, describedBy }) => (
                                    <Input id={id} type="date" value={data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                                )}
                            </Field>
        
                            {isRecurring && (
                                <div className="flex flex-col gap-2 sm:col-span-2">
                                    <Checkbox label="Tiene duración determinada" checked={data.has_term} onChange={(event) => form.setData('has_term', event.target.checked)} />
                                    {data.has_term && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Input
                                                aria-label="Duración"
                                                inputMode="numeric"
                                                value={data.term_count}
                                                onChange={(event) => form.setData('term_count', event.target.value.replace(/\D/g, '').slice(0, 3))}
                                                invalid={Boolean(errors.term_count)}
                                                className="numeric w-20 text-center"
                                            />
                                            <Select aria-label="Unidad de duración" value={data.term_unit} onChange={(event) => form.setData('term_unit', event.target.value as ContractFormValues['term_unit'])} className="w-28">
                                                <option value="month">meses</option>
                                                <option value="year">años</option>
                                            </Select>
                                            {preview?.end_date && <span className="text-sm text-ink-500">Termina el {formatDate(preview.end_date)}</span>}
                                        </div>
                                    )}
                                    {errors.term_count && <p className="text-xs text-danger-600">{errors.term_count}</p>}
                                    {!data.has_term && <p className="text-sm text-ink-500">Sin fecha de fin: se cobra hasta que lo canceles.</p>}
                                </div>
                            )}
                        </FormSection>
        
                        <FormSection title="Primer cobro" description="Desde qué ciclo cobra el sistema. Los anteriores se consideran cobrados fuera del sistema.">
                            {isRecurring && (
                                <Field label="Ciclo" error={errors.first_cycle} required>
                                    {({ id, invalid, describedBy }) => (
                                        <Select id={id} value={data.first_cycle} onChange={(event) => chooseCycle(event.target.value)} invalid={invalid} aria-describedby={describedBy} disabled={cycleOptions.length === 0}>
                                            {cycleOptions.length === 0 && <option value="">—</option>}
                                            {preview && preview.suggested_first_cycle === null && <option value="">Contrato ya terminado</option>}
                                            {cycleOptions.map((item) => (
                                                <option key={item.cycle} value={item.cycle}>
                                                    Ciclo {item.cycle} — {formatDate(item.due_date)}
                                                    {item.cycle === preview?.suggested_first_cycle ? ' (próximo)' : ''}
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </Field>
                            )}
        
                            <Field
                                label="Vence el"
                                error={errors.first_charge_date}
                                hint={dateMoved ? 'Fecha movida a mano: los siguientes ciclos vuelven a su fecha ancla.' : 'Puedes moverla si acordaste otra fecha con el cliente.'}
                                required
                            >
                                {({ id, invalid, describedBy }) => (
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id={id}
                                            type="date"
                                            value={data.first_charge_date}
                                            onChange={(event) => {
                                                setDateTouched(true);
                                                form.setData('first_charge_date', event.target.value);
                                            }}
                                            invalid={invalid}
                                            aria-describedby={describedBy}
                                        />
                                        {dateMoved && (
                                            <button type="button" onClick={resetChargeDate} className="shrink-0 text-sm font-medium text-brand-700 hover:underline">
                                                Restablecer
                                            </button>
                                        )}
                                    </div>
                                )}
                            </Field>
                        </FormSection>
                    </>
                )}

                <FormSection title="Notas" description="Internas: no aparecen en documentos del cliente." columns={1}>
                    <Field label="Notas" error={errors.notes}>
                        {({ id, invalid, describedBy }) => (
                            <Textarea id={id} rows={3} value={data.notes} onChange={(event) => form.setData('notes', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                        )}
                    </Field>
                </FormSection>

                <div className="sticky bottom-14 z-10 -mx-4 flex justify-end gap-2 border-t border-line bg-canvas/95 px-4 py-3 backdrop-blur-sm sm:static sm:mx-0 sm:mt-2 sm:border-0 sm:bg-transparent sm:px-0 sm:backdrop-blur-none">
                    <Link href={cancelHref} className={buttonClasses('secondary')}>
                        Cancelar
                    </Link>
                    <Button type="submit" loading={form.processing}>
                        {submitLabel}
                    </Button>
                </div>
            </div>

            <aside className="xl:sticky xl:top-20 xl:self-start">
                <SchedulePanel values={data} preview={preview} error={previewError} loading={previewLoading} />
            </aside>
        </form>
    );
}
