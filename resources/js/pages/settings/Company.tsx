import { PageHeader } from '@/components/layout/PageHeader';
import { Button, IconButton } from '@/components/ui/Button';
import { Field, Input, Select, Textarea } from '@/components/ui/Field';
import { FormSection } from '@/components/ui/FormSection';
import type { BankAccount } from '@/features/quotes/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ImageUp, Plus, Trash2 } from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';

type CompanyValues = {
    trade_name: string;
    legal_name: string;
    ruc: string;
    address: string;
    phone: string;
    email: string;
    website: string;
    bank_accounts: BankAccount[];
    yape_phone: string;
    plin_phone: string;
    wallet_holder: string;
    quote_intro: string;
    quote_terms: string;
    default_tax_rate: string;
    default_currency: 'PEN' | 'USD';
    quote_validity_days: string;
};

type CompanyProps = {
    values: CompanyValues;
    logo_url: string | null;
};

const EMPTY_ACCOUNT: BankAccount = {
    bank: '',
    currency: 'PEN',
    number: '',
    cci: '',
    holder: '',
};

export default function SettingsCompany({ values, logo_url }: CompanyProps) {
    const form = useForm<CompanyValues>(values);
    const { data, errors } = form;
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const fieldErrors = errors as Record<string, string>;
    // Los errores del logo vienen de otra petición (router.post), no de este formulario.
    const pageErrors = (usePage().props.errors ?? {}) as Record<string, string>;

    function text(
        field: keyof CompanyValues,
        label: string,
        options: {
            hint?: string;
            type?: string;
            className?: string;
            numeric?: boolean;
        } = {},
    ) {
        return (
            <Field label={label} error={fieldErrors[field]} hint={options.hint} className={options.className}>
                {({ id, invalid, describedBy }) => (
                    <Input
                        id={id}
                        type={options.type ?? 'text'}
                        value={data[field] as string}
                        onChange={(event) => form.setData(field, event.target.value as never)}
                        invalid={invalid}
                        aria-describedby={describedBy}
                        className={options.numeric ? 'numeric' : undefined}
                    />
                )}
            </Field>
        );
    }

    function updateAccount(index: number, changes: Partial<BankAccount>) {
        form.setData(
            'bank_accounts',
            data.bank_accounts.map((account, position) => (position === index ? { ...account, ...changes } : account)),
        );
    }

    function uploadLogo(file: File | undefined) {
        if (!file) return;
        router.post(
            route('settings.company.logo'),
            { logo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => {
                    setUploading(false);
                    if (fileRef.current) fileRef.current.value = '';
                },
            },
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(route('settings.company.update'), { preserveScroll: true });
    }

    return (
        <>
            <Link href={route('settings.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900">
                <ArrowLeft className="size-4" aria-hidden /> Configuración
            </Link>
            <PageHeader title="Mi empresa" eyebrow="Configuración" description="Estos datos aparecen en tus cotizaciones y en sus PDF." />

            <FormSection title="Logo" description="PNG, JPG o WEBP de hasta 2 MB. Ideal: PNG horizontal con fondo transparente." columns={1}>
                <div className="flex flex-wrap items-center gap-4">
                    <div className="flex h-20 w-56 items-center justify-center rounded-md border border-dashed border-line-strong bg-cream-50 p-3">
                        {logo_url ? (
                            <img src={logo_url} alt="Logo actual" className="max-h-full max-w-full object-contain" />
                        ) : (
                            <span className="text-sm text-ink-400">Sin logo</span>
                        )}
                    </div>
                    <input
                        ref={fileRef}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="sr-only"
                        onChange={(event) => uploadLogo(event.target.files?.[0])}
                        aria-label="Subir logo"
                    />
                    <div className="flex gap-2">
                        <Button variant="secondary" loading={uploading} onClick={() => fileRef.current?.click()}>
                            <ImageUp /> {logo_url ? 'Cambiar logo' : 'Subir logo'}
                        </Button>
                        {logo_url && (
                            <Button variant="ghost" onClick={() => router.delete(route('settings.company.logo.delete'), { preserveScroll: true })}>
                                Quitar
                            </Button>
                        )}
                    </div>
                </div>
                {pageErrors.logo && <p className="text-xs text-danger-600">{pageErrors.logo}</p>}
            </FormSection>

            <form onSubmit={submit} noValidate>
                <FormSection title="Identidad" description="Nombre comercial para mostrar y datos fiscales.">
                    {text('trade_name', 'Nombre comercial')}
                    {text('legal_name', 'Razón social')}
                    {text('ruc', 'RUC', { numeric: true })}
                    {text('website', 'Sitio web')}
                </FormSection>

                <FormSection title="Contacto">
                    {text('address', 'Dirección', {
                        className: 'sm:col-span-2',
                    })}
                    {text('phone', 'Teléfono / WhatsApp', { numeric: true })}
                    {text('email', 'Correo', { type: 'email' })}
                </FormSection>

                <FormSection title="Datos de pago" description="Cuentas para depósitos. En cada cotización se muestran las de su moneda." columns={1}>
                    {data.bank_accounts.map((account, index) => (
                        <div key={index} className="grid gap-2 rounded-md border border-line p-3 sm:grid-cols-[1fr_100px_1.4fr_auto]">
                            <Input
                                aria-label="Banco"
                                placeholder="Banco"
                                value={account.bank}
                                onChange={(event) =>
                                    updateAccount(index, {
                                        bank: event.target.value,
                                    })
                                }
                                invalid={Boolean(fieldErrors[`bank_accounts.${index}.bank`])}
                            />
                            <Select
                                aria-label="Moneda"
                                value={account.currency ?? ''}
                                onChange={(event) =>
                                    updateAccount(index, {
                                        currency: (event.target.value || null) as BankAccount['currency'],
                                    })
                                }
                            >
                                <option value="PEN">Soles</option>
                                <option value="USD">Dólares</option>
                                <option value="">Ambas</option>
                            </Select>
                            <Input
                                aria-label="Número de cuenta"
                                placeholder="N.º de cuenta"
                                value={account.number}
                                onChange={(event) =>
                                    updateAccount(index, {
                                        number: event.target.value,
                                    })
                                }
                                invalid={Boolean(fieldErrors[`bank_accounts.${index}.number`])}
                                className="numeric"
                            />
                            <IconButton
                                label="Quitar cuenta"
                                onClick={() =>
                                    form.setData(
                                        'bank_accounts',
                                        data.bank_accounts.filter((_, position) => position !== index),
                                    )
                                }
                            >
                                <Trash2 />
                            </IconButton>
                            <Input
                                aria-label="CCI"
                                placeholder="CCI (opcional)"
                                value={account.cci ?? ''}
                                onChange={(event) =>
                                    updateAccount(index, {
                                        cci: event.target.value,
                                    })
                                }
                                className="numeric sm:col-span-2"
                            />
                            <Input
                                aria-label="Titular"
                                placeholder="Titular (opcional)"
                                value={account.holder ?? ''}
                                onChange={(event) =>
                                    updateAccount(index, {
                                        holder: event.target.value,
                                    })
                                }
                                className="sm:col-span-2"
                            />
                        </div>
                    ))}
                    {data.bank_accounts.length < 6 && (
                        <Button
                            variant="secondary"
                            className="self-start"
                            onClick={() => form.setData('bank_accounts', [...data.bank_accounts, { ...EMPTY_ACCOUNT }])}
                        >
                            <Plus /> Agregar cuenta
                        </Button>
                    )}
                    <div className="grid gap-4 sm:grid-cols-3">
                        {text('yape_phone', 'Yape', { numeric: true })}
                        {text('plin_phone', 'Plin', { numeric: true })}
                        {text('wallet_holder', 'Titular Yape/Plin')}
                    </div>
                </FormSection>

                <FormSection title="Cotizaciones" description="Valores por defecto de cada cotización nueva.">
                    <Field label="Moneda" error={fieldErrors.default_currency}>
                        {({ id }) => (
                            <Select
                                id={id}
                                value={data.default_currency}
                                onChange={(event) => form.setData('default_currency', event.target.value as CompanyValues['default_currency'])}
                            >
                                <option value="PEN">Soles (S/)</option>
                                <option value="USD">Dólares ($)</option>
                            </Select>
                        )}
                    </Field>
                    <div className="grid grid-cols-2 gap-4">
                        {text('default_tax_rate', 'IGV %', {
                            numeric: true,
                            hint: 'También se usa en cobros.',
                        })}
                        {text('quote_validity_days', 'Validez (días)', {
                            numeric: true,
                        })}
                    </div>
                    <Field label="Texto introductorio" error={fieldErrors.quote_intro} className="sm:col-span-2">
                        {({ id }) => (
                            <Textarea
                                id={id}
                                rows={2}
                                value={data.quote_intro}
                                onChange={(event) => form.setData('quote_intro', event.target.value)}
                                placeholder="Estimado cliente, le hacemos llegar nuestra propuesta…"
                            />
                        )}
                    </Field>
                    <Field label="Condiciones" error={fieldErrors.quote_terms} className="sm:col-span-2">
                        {({ id }) => (
                            <Textarea
                                id={id}
                                rows={4}
                                value={data.quote_terms}
                                onChange={(event) => form.setData('quote_terms', event.target.value)}
                                placeholder="Precios incluyen IGV. Forma de pago: 50% de adelanto…"
                            />
                        )}
                    </Field>
                </FormSection>

                <div className="sticky bottom-14 z-10 -mx-4 flex justify-end gap-2 border-t border-line bg-canvas/95 px-4 py-3 backdrop-blur-sm sm:static sm:mx-0 sm:mt-2 sm:border-0 sm:bg-transparent sm:px-0 sm:backdrop-blur-none">
                    <Button type="submit" loading={form.processing}>
                        Guardar
                    </Button>
                </div>
            </form>
        </>
    );
}
