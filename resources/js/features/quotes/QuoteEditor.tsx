import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Button, buttonClasses } from '@/components/ui/Button';
import { Field, Input, Select, Textarea } from '@/components/ui/Field';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { Tooltip } from '@/components/ui/Tooltip';
import type { CatalogService } from '@/features/catalog/types';
import { ClientSelector, type ClientOption } from '@/features/clients/ClientSelector';
import { QuickClientDialog } from '@/features/clients/QuickClientDialog';
import { cn } from '@/lib/cn';
import { addDays } from '@/lib/dates';
import { CURRENCY_SYMBOL } from '@/lib/money';
import { Link, useForm } from '@inertiajs/react';
import { Eye, FileDown, Link2, PencilLine, UserPlus } from 'lucide-react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { buildDocument, calculateFromForm } from './buildDocument';
import { QuoteItemsEditor } from './QuoteItemsEditor';
import { QuotePreview } from './QuotePreview';
import { useCopyQuoteLink } from './useCopyQuoteLink';
import type { CompanyBlock, QuoteDefaults, QuoteFormValues } from './types';

type QuoteEditorProps = {
    initialValues: QuoteFormValues;
    initialClient: ClientOption | null;
    company: CompanyBlock;
    defaults: QuoteDefaults;
    catalog: CatalogService[];
    /** Número ya asignado (edición) o texto provisional (nueva). */
    number: string;
    method: 'post' | 'put';
    action: string;
    cancelHref: string;
    submitLabel: string;
    /** Solo en cotizaciones guardadas: habilita PDF y "Copiar enlace". */
    links?: {
        id: number;
        status: string;
        pdf_url: string;
        public_url: string | null;
    };
};

export function QuoteEditor({
    initialValues,
    initialClient,
    company,
    defaults,
    catalog,
    number,
    method,
    action,
    cancelHref,
    submitLabel,
    links,
}: QuoteEditorProps) {
    const form = useForm<QuoteFormValues>(initialValues);
    const { data } = form;
    const [client, setClient] = useState<ClientOption | null>(initialClient);
    const [quickClient, setQuickClient] = useState(false);
    const [mobileView, setMobileView] = useState<'edit' | 'preview'>('edit');
    const [validityTouched, setValidityTouched] = useState(method === 'put');
    const copyLink = useCopyQuoteLink(links ?? { id: 0, status: 'draft', public_url: null });

    const calculation = useMemo(() => calculateFromForm(data), [data]);
    const document = useMemo(() => buildDocument(data, client, number, calculation.result), [data, client, number, calculation.result]);

    // Errores del servidor + error del cálculo en vivo (mismo nombre de campo que el backend).
    const errors: Record<string, string> = {
        ...(form.errors as Record<string, string>),
    };
    if (calculation.error) errors[calculation.error.field] = calculation.error.message;

    function changeIssueDate(issueDate: string) {
        form.setData((current) => ({
            ...current,
            issue_date: issueDate,
            valid_until: !validityTouched && /^\d{4}-\d{2}-\d{2}$/.test(issueDate) ? addDays(issueDate, defaults.validity_days) : current.valid_until,
        }));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        // La clave de React no viaja al servidor.
        form.transform((values) => ({
            ...values,
            items: values.items.map(({ key: _key, ...item }) => item),
        }));
        form.submit(method, action, {
            preserveScroll: true,
            onError: () => setMobileView('edit'),
        });
    }

    return (
        <form onSubmit={submit} noValidate>
            <SegmentedControl
                label="Vista"
                value={mobileView}
                onChange={setMobileView}
                options={[
                    { value: 'edit', label: 'Editar' },
                    { value: 'preview', label: 'Vista previa' },
                ]}
                className="mb-4 w-full xl:hidden"
            />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,520px)_minmax(0,1fr)]">
                <div className={cn('flex min-w-0 flex-col gap-5', mobileView === 'preview' && 'max-xl:hidden')}>
                    <Section title="Cliente">
                        <Field label="Cliente" error={errors.client_id} required>
                            {({ id, invalid, describedBy }) => (
                                <ClientSelector
                                    id={id}
                                    value={client}
                                    invalid={invalid}
                                    describedBy={describedBy}
                                    onChange={(option) => {
                                        setClient(option);
                                        form.setData('client_id', option?.id ?? null);
                                    }}
                                />
                            )}
                        </Field>
                        <button
                            type="button"
                            onClick={() => setQuickClient(true)}
                            className="inline-flex items-center gap-1.5 self-start text-sm font-medium text-brand-700 hover:underline"
                        >
                            <UserPlus className="size-4" aria-hidden /> Registrar cliente nuevo
                        </button>
                    </Section>

                    <Section title="Datos">
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Fecha" error={errors.issue_date} required>
                                {({ id, invalid }) => (
                                    <Input
                                        id={id}
                                        type="date"
                                        value={data.issue_date}
                                        onChange={(event) => changeIssueDate(event.target.value)}
                                        invalid={invalid}
                                    />
                                )}
                            </Field>
                            <Field label="Válida hasta" error={errors.valid_until} required>
                                {({ id, invalid }) => (
                                    <Input
                                        id={id}
                                        type="date"
                                        value={data.valid_until}
                                        onChange={(event) => {
                                            setValidityTouched(true);
                                            form.setData('valid_until', event.target.value);
                                        }}
                                        invalid={invalid}
                                    />
                                )}
                            </Field>
                            <Field label="Entrega estimada" error={errors.delivery_date}>
                                {({ id, invalid }) => (
                                    <Input
                                        id={id}
                                        type="date"
                                        value={data.delivery_date}
                                        onChange={(event) => form.setData('delivery_date', event.target.value)}
                                        invalid={invalid}
                                    />
                                )}
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Moneda" error={errors.currency}>
                                    {({ id }) => (
                                        <Select
                                            id={id}
                                            value={data.currency}
                                            onChange={(event) => form.setData('currency', event.target.value as QuoteFormValues['currency'])}
                                        >
                                            <option value="PEN">S/</option>
                                            <option value="USD">$</option>
                                        </Select>
                                    )}
                                </Field>
                                <Field label="IGV %" error={errors.tax_rate}>
                                    {({ id, invalid }) => (
                                        <Input
                                            id={id}
                                            inputMode="decimal"
                                            value={data.tax_rate}
                                            onChange={(event) => form.setData('tax_rate', event.target.value.replace(/[^\d.]/g, ''))}
                                            invalid={invalid}
                                            className="numeric text-right"
                                        />
                                    )}
                                </Field>
                            </div>
                        </div>
                    </Section>

                    <Section title="Conceptos" hint="Precios con IGV incluido.">
                        <QuoteItemsEditor
                            items={data.items}
                            currency={data.currency}
                            catalog={catalog}
                            lines={calculation.result?.lines ?? null}
                            errors={errors}
                            onChange={(items) => form.setData('items', items)}
                        />
                        {errors.items && <p className="text-xs text-danger-600">{errors.items}</p>}
                    </Section>

                    <Section title="Descuento global">
                        <div className="flex flex-wrap items-center gap-2">
                            <Select
                                aria-label="Tipo de descuento global"
                                value={data.global_discount_type}
                                onChange={(event) =>
                                    form.setData((current) => ({
                                        ...current,
                                        global_discount_type: event.target.value as QuoteFormValues['global_discount_type'],
                                        global_discount_value: '',
                                    }))
                                }
                                className="w-44"
                            >
                                <option value="">Sin descuento</option>
                                <option value="percent">Porcentaje (%)</option>
                                <option value="amount">Monto ({CURRENCY_SYMBOL[data.currency]})</option>
                            </Select>
                            {data.global_discount_type && (
                                <Input
                                    aria-label="Descuento global"
                                    inputMode="decimal"
                                    value={data.global_discount_value}
                                    onChange={(event) => form.setData('global_discount_value', event.target.value.replace(',', '.').replace(/[^\d.]/g, ''))}
                                    invalid={Boolean(errors.global_discount_value)}
                                    className="numeric w-32 text-right"
                                />
                            )}
                        </div>
                        {errors.global_discount_value && <p className="text-xs text-danger-600">{errors.global_discount_value}</p>}
                        {calculation.result && (
                            <div className="flex items-center justify-between rounded-md bg-cream-100 px-3 py-2.5">
                                <span className="eyebrow">Total</span>
                                <MoneyDisplay
                                    value={{
                                        amount: calculation.result.total,
                                        currency: data.currency,
                                    }}
                                    size="lg"
                                />
                            </div>
                        )}
                    </Section>

                    <Section title="Textos" hint="Aparecen en el documento, salvo las notas internas.">
                        <Field label="Introducción" error={errors.intro}>
                            {({ id }) => <Textarea id={id} rows={2} value={data.intro} onChange={(event) => form.setData('intro', event.target.value)} />}
                        </Field>
                        <Field label="Observaciones" error={errors.observations}>
                            {({ id }) => (
                                <Textarea id={id} rows={3} value={data.observations} onChange={(event) => form.setData('observations', event.target.value)} />
                            )}
                        </Field>
                        <Field label="Condiciones" error={errors.terms}>
                            {({ id }) => <Textarea id={id} rows={3} value={data.terms} onChange={(event) => form.setData('terms', event.target.value)} />}
                        </Field>
                        <Field label="Notas internas" error={errors.internal_notes} hint="Solo para ti: no aparecen en la cotización.">
                            {({ id, describedBy }) => (
                                <Textarea
                                    id={id}
                                    rows={2}
                                    value={data.internal_notes}
                                    onChange={(event) => form.setData('internal_notes', event.target.value)}
                                    aria-describedby={describedBy}
                                    className="bg-cream-50"
                                />
                            )}
                        </Field>
                    </Section>
                </div>

                <div className={cn('min-w-0', mobileView === 'edit' && 'max-xl:hidden')}>
                    <div className="xl:sticky xl:top-20">
                        <p className="eyebrow mb-2 max-xl:hidden">Vista previa</p>
                        <div className="overflow-hidden rounded-md border border-line shadow-subtle xl:max-h-[calc(100dvh-12rem)] xl:overflow-y-auto">
                            <QuotePreview document={document} company={company} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Barra de acciones: fija abajo para tenerla siempre a mano en documentos largos. */}
            <div className="sticky bottom-14 z-10 -mx-4 mt-6 flex items-center justify-end gap-2 border-t border-line bg-canvas/95 px-4 py-3 backdrop-blur-sm sm:bottom-0 sm:mx-0 sm:rounded-md sm:border sm:px-4">
                <Link href={cancelHref} className={cn(buttonClasses('ghost'), 'mr-auto')}>
                    Cancelar
                </Link>
                {links ? (
                    <>
                        <Tooltip content={form.isDirty ? 'El PDF muestra la última versión guardada' : 'Ver PDF'}>
                            <a href={links.pdf_url} target="_blank" rel="noopener" className={cn(buttonClasses('secondary'), 'max-sm:hidden')}>
                                <FileDown /> PDF
                            </a>
                        </Tooltip>
                        <Button variant="secondary" onClick={copyLink} className="max-sm:hidden">
                            <Link2 /> Copiar enlace
                        </Button>
                    </>
                ) : (
                    <Tooltip content="Guarda la cotización para generar el PDF y el enlace">
                        <span className="max-sm:hidden">
                            <Button variant="secondary" disabled>
                                <FileDown /> PDF
                            </Button>
                        </span>
                    </Tooltip>
                )}
                <Button variant="secondary" className="xl:hidden" onClick={() => setMobileView(mobileView === 'edit' ? 'preview' : 'edit')}>
                    {mobileView === 'edit' ? <Eye /> : <PencilLine />}
                    <span className="max-sm:sr-only">{mobileView === 'edit' ? 'Vista previa' : 'Editar'}</span>
                </Button>
                <Button type="submit" loading={form.processing}>
                    {submitLabel}
                </Button>
            </div>

            <QuickClientDialog
                open={quickClient}
                onOpenChange={setQuickClient}
                onCreated={(created) => {
                    setClient(created);
                    form.setData('client_id', created.id);
                }}
            />
        </form>
    );
}

function Section({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4">
            <header className="flex items-baseline justify-between gap-2">
                <h2 className="text-md font-semibold text-ink-900">{title}</h2>
                {hint && <p className="text-xs text-ink-500">{hint}</p>}
            </header>
            {children}
        </section>
    );
}
