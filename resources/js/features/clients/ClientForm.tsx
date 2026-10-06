import { Button, buttonClasses } from '@/components/ui/Button';
import { Field, Input, Select, Textarea } from '@/components/ui/Field';
import { FormSection } from '@/components/ui/FormSection';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { useForm, Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DOCUMENT_TYPE_LABEL, type ClientFormValues, type ClientType, type DocumentType } from './types';

type ClientFormProps = {
    initialValues: ClientFormValues;
    /** POST para crear, PUT para editar. */
    method: 'post' | 'put';
    action: string;
    cancelHref: string;
    submitLabel: string;
};

const DOCUMENT_HINT: Record<DocumentType, string | undefined> = {
    RUC: '11 dígitos. Se valida el dígito verificador.',
    DNI: '8 dígitos.',
    CE: 'Entre 6 y 12 caracteres.',
    NONE: undefined,
};

export function ClientForm({ initialValues, method, action, cancelHref, submitLabel }: ClientFormProps) {
    const form = useForm<ClientFormValues>(initialValues);
    const { data, errors } = form;
    const isCompany = data.type === 'company';

    function changeType(type: ClientType) {
        // Sugerir el documento habitual solo si aún no se escribió uno.
        const suggestedDocument: DocumentType = type === 'company' ? 'RUC' : 'DNI';
        form.setData((current) => ({
            ...current,
            type,
            document_type: current.document_number === '' ? suggestedDocument : current.document_type,
        }));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        // Los campos ocultos para personas no deben guardarse aunque se hayan escrito antes de cambiar el tipo.
        form.transform((values) => (values.type === 'person' ? { ...values, trade_name: '', contact_name: '' } : values));
        form.submit(method, action, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} noValidate className="flex flex-col">
            <FormSection title="Identificación" description="Cómo aparece el cliente en cotizaciones y cobros.">
                <div className="sm:col-span-2">
                    <SegmentedControl
                        label="Tipo de cliente"
                        value={data.type}
                        onChange={changeType}
                        options={[
                            { value: 'company', label: 'Empresa' },
                            { value: 'person', label: 'Persona' },
                        ]}
                    />
                </div>

                <Field label={isCompany ? 'Razón social' : 'Nombre completo'} error={errors.name} required className="sm:col-span-2">
                    {({ id, invalid, describedBy }) => (
                        <Input
                            id={id}
                            value={data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            invalid={invalid}
                            aria-describedby={describedBy}
                            placeholder={isCompany ? 'Empresa ABC S.A.C.' : 'María Torres Ríos'}
                            autoFocus
                        />
                    )}
                </Field>

                {isCompany && (
                    <Field label="Nombre comercial" error={errors.trade_name} hint="Opcional. Si es distinto de la razón social." className="sm:col-span-2">
                        {({ id, invalid, describedBy }) => (
                            <Input id={id} value={data.trade_name} onChange={(event) => form.setData('trade_name', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                        )}
                    </Field>
                )}

                <Field label="Tipo de documento" error={errors.document_type}>
                    {({ id, invalid }) => (
                        <Select id={id} value={data.document_type} onChange={(event) => form.setData('document_type', event.target.value as DocumentType)} invalid={invalid}>
                            {(Object.keys(DOCUMENT_TYPE_LABEL) as DocumentType[]).map((type) => (
                                <option key={type} value={type}>
                                    {DOCUMENT_TYPE_LABEL[type]}
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>

                {data.document_type !== 'NONE' && (
                    <Field label={`Número de ${data.document_type}`} error={errors.document_number} hint={DOCUMENT_HINT[data.document_type]}>
                        {({ id, invalid, describedBy }) => (
                            <Input
                                id={id}
                                value={data.document_number}
                                onChange={(event) => form.setData('document_number', event.target.value)}
                                invalid={invalid}
                                aria-describedby={describedBy}
                                inputMode={data.document_type === 'CE' ? 'text' : 'numeric'}
                                maxLength={data.document_type === 'RUC' ? 11 : data.document_type === 'DNI' ? 8 : 12}
                                className="numeric"
                            />
                        )}
                    </Field>
                )}
            </FormSection>

            <FormSection title="Contacto" description="Todos los campos son opcionales.">
                <Field label="WhatsApp" error={errors.whatsapp} hint="Celular de 9 dígitos o con código de país (+…).">
                    {({ id, invalid, describedBy }) => (
                        <Input id={id} type="tel" inputMode="tel" value={data.whatsapp} onChange={(event) => form.setData('whatsapp', event.target.value)} invalid={invalid} aria-describedby={describedBy} className="numeric" placeholder="987 654 321" />
                    )}
                </Field>

                <Field label="Teléfono" error={errors.phone}>
                    {({ id, invalid, describedBy }) => (
                        <Input id={id} type="tel" inputMode="tel" value={data.phone} onChange={(event) => form.setData('phone', event.target.value)} invalid={invalid} aria-describedby={describedBy} className="numeric" />
                    )}
                </Field>

                <Field label="Correo" error={errors.email}>
                    {({ id, invalid, describedBy }) => (
                        <Input id={id} type="email" inputMode="email" value={data.email} onChange={(event) => form.setData('email', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                    )}
                </Field>

                {isCompany && (
                    <Field label="Persona de contacto" error={errors.contact_name}>
                        {({ id, invalid, describedBy }) => (
                            <Input id={id} value={data.contact_name} onChange={(event) => form.setData('contact_name', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                        )}
                    </Field>
                )}

                <Field label="Dirección" error={errors.address} className="sm:col-span-2">
                    {({ id, invalid, describedBy }) => (
                        <Input id={id} value={data.address} onChange={(event) => form.setData('address', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                    )}
                </Field>
            </FormSection>

            <FormSection title="Otros" description="Notas internas: no aparecen en documentos del cliente.">
                <Field label="Estado" error={errors.status}>
                    {({ id, invalid }) => (
                        <Select id={id} value={data.status} onChange={(event) => form.setData('status', event.target.value as ClientFormValues['status'])} invalid={invalid}>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </Select>
                    )}
                </Field>

                <Field label="Notas" error={errors.notes} className="sm:col-span-2">
                    {({ id, invalid, describedBy }) => (
                        <Textarea id={id} rows={4} value={data.notes} onChange={(event) => form.setData('notes', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                    )}
                </Field>
            </FormSection>

            {/* En móvil la barra de acciones queda fija sobre la navegación inferior. */}
            <div className="sticky bottom-14 z-10 -mx-4 flex justify-end gap-2 border-t border-line bg-canvas/95 px-4 py-3 backdrop-blur-sm sm:static sm:mx-0 sm:mt-2 sm:border-0 sm:bg-transparent sm:px-0 sm:backdrop-blur-none">
                <Link href={cancelHref} className={buttonClasses('secondary')}>
                    Cancelar
                </Link>
                <Button type="submit" loading={form.processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
