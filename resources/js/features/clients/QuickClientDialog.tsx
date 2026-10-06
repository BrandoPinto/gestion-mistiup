import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Field, Input, Select } from '@/components/ui/Field';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { HttpError, postJson } from '@/lib/http';
import { useEffect, useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import type { ClientOption } from './ClientSelector';
import { DOCUMENT_TYPE_LABEL, type ClientType, type DocumentType } from './types';

type QuickClientDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (client: ClientOption) => void;
};

type QuickClientForm = {
    type: ClientType;
    name: string;
    document_type: DocumentType;
    document_number: string;
    whatsapp: string;
    email: string;
};

const EMPTY: QuickClientForm = { type: 'company', name: '', document_type: 'RUC', document_number: '', whatsapp: '', email: '' };
const FORM_ID = 'quick-client';

/**
 * Alta rápida de cliente sin salir de otro formulario. Usa las mismas validaciones del alta completa (backend).
 */
export function QuickClientDialog({ open, onOpenChange, onCreated }: QuickClientDialogProps) {
    const [data, setData] = useState<QuickClientForm>(EMPTY);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setData(EMPTY);
            setErrors({});
        }
    }, [open]);

    function set<K extends keyof QuickClientForm>(key: K, value: QuickClientForm[K]) {
        setData((current) => ({ ...current, [key]: value }));
    }

    async function submit(event: FormEvent) {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await postJson<{ data: ClientOption }>(route('clients.quick-store'), { ...data, status: 'active' });
            toast.success(`Cliente «${response.data.name}» registrado.`);
            onCreated(response.data);
            onOpenChange(false);
        } catch (error) {
            if (error instanceof HttpError && error.status === 422 && error.errors) {
                setErrors(Object.fromEntries(Object.entries(error.errors).map(([key, messages]) => [key, messages[0] ?? ''])));
            } else {
                toast.error('No se pudo registrar el cliente.');
            }
        } finally {
            setProcessing(false);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !processing && onOpenChange(next)}
            title="Nuevo cliente"
            description="Datos básicos. Puedes completar el resto luego desde su ficha."
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={FORM_ID} loading={processing}>
                        Registrar cliente
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} noValidate className="flex flex-col gap-4">
                <SegmentedControl
                    label="Tipo de cliente"
                    value={data.type}
                    onChange={(type) => setData((current) => ({ ...current, type, document_type: current.document_number ? current.document_type : type === 'company' ? 'RUC' : 'DNI' }))}
                    options={[
                        { value: 'company', label: 'Empresa' },
                        { value: 'person', label: 'Persona' },
                    ]}
                    className="self-start"
                />
                <Field label={data.type === 'company' ? 'Razón social' : 'Nombre completo'} error={errors.name} required>
                    {({ id, invalid, describedBy }) => <Input id={id} value={data.name} onChange={(event) => set('name', event.target.value)} invalid={invalid} aria-describedby={describedBy} autoFocus />}
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Documento" error={errors.document_type}>
                        {({ id }) => (
                            <Select id={id} value={data.document_type} onChange={(event) => set('document_type', event.target.value as DocumentType)}>
                                {(Object.keys(DOCUMENT_TYPE_LABEL) as DocumentType[]).map((type) => (
                                    <option key={type} value={type}>
                                        {DOCUMENT_TYPE_LABEL[type]}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>
                    {data.document_type !== 'NONE' && (
                        <Field label="Número" error={errors.document_number}>
                            {({ id, invalid, describedBy }) => (
                                <Input id={id} inputMode="numeric" value={data.document_number} onChange={(event) => set('document_number', event.target.value)} invalid={invalid} aria-describedby={describedBy} className="numeric" />
                            )}
                        </Field>
                    )}
                    <Field label="WhatsApp" error={errors.whatsapp}>
                        {({ id, invalid, describedBy }) => <Input id={id} type="tel" value={data.whatsapp} onChange={(event) => set('whatsapp', event.target.value)} invalid={invalid} aria-describedby={describedBy} className="numeric" />}
                    </Field>
                    <Field label="Correo" error={errors.email}>
                        {({ id, invalid, describedBy }) => <Input id={id} type="email" value={data.email} onChange={(event) => set('email', event.target.value)} invalid={invalid} aria-describedby={describedBy} />}
                    </Field>
                </div>
            </form>
        </Dialog>
    );
}
