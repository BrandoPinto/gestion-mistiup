import { MoneyInput } from '@/components/data/MoneyInput';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Field, Input, Textarea } from '@/components/ui/Field';
import { ClientSelector, type ClientOption } from '@/features/clients/ClientSelector';
import { todayInBusinessZone } from '@/lib/dates';
import type { Currency } from '@/types/money';
import { useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

type ManualChargeDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Si se indica, el cliente queda fijo (desde su ficha). */
    client?: ClientOption | null;
};

type ManualChargeForm = {
    client_id: number | null;
    description: string;
    currency: Currency;
    amount: string;
    due_date: string;
    notes: string;
};

const FORM_ID = 'manual-charge';

/** Cobro suelto, sin contrato: un trabajo puntual, una migración, un reembolso. */
export function ManualChargeDialog({ open, onOpenChange, client = null }: ManualChargeDialogProps) {
    const [selected, setSelected] = useState<ClientOption | null>(client);
    const form = useForm<ManualChargeForm>({
        client_id: client?.id ?? null,
        description: '',
        currency: 'PEN',
        amount: '',
        due_date: todayInBusinessZone(),
        notes: '',
    });
    const { data, errors } = form;

    useEffect(() => {
        if (open) {
            form.setData({
                client_id: client?.id ?? null,
                description: '',
                currency: 'PEN',
                amount: '',
                due_date: todayInBusinessZone(),
                notes: '',
            });
            form.clearErrors();
            setSelected(client);
        }
    }, [open]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(route('charges.store'), {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !form.processing && onOpenChange(next)}
            title="Nuevo cobro"
            description="Para cobros que no vienen de un servicio contratado. Importe con IGV incluido."
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={form.processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={FORM_ID} loading={form.processing}>
                        Registrar cobro
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} noValidate className="flex flex-col gap-4">
                <Field label="Cliente" error={errors.client_id} required>
                    {({ id, invalid, describedBy }) => (
                        <ClientSelector
                            id={id}
                            value={selected}
                            disabled={client !== null}
                            invalid={invalid}
                            describedBy={describedBy}
                            onChange={(option) => {
                                setSelected(option);
                                form.setData('client_id', option?.id ?? null);
                            }}
                        />
                    )}
                </Field>
                <Field label="Concepto" error={errors.description} required>
                    {({ id, invalid, describedBy }) => (
                        <Input
                            id={id}
                            value={data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            invalid={invalid}
                            aria-describedby={describedBy}
                            placeholder="Migración de correos"
                        />
                    )}
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Importe" error={errors.amount ?? errors.currency} required>
                        {({ id, invalid, describedBy }) => (
                            <MoneyInput
                                id={id}
                                amount={data.amount}
                                currency={data.currency}
                                onAmountChange={(amount) => form.setData('amount', amount)}
                                onCurrencyChange={(currency) => form.setData('currency', currency)}
                                invalid={invalid}
                                describedBy={describedBy}
                            />
                        )}
                    </Field>
                    <Field label="Vence el" error={errors.due_date} required>
                        {({ id, invalid, describedBy }) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.due_date}
                                onChange={(event) => form.setData('due_date', event.target.value)}
                                invalid={invalid}
                                aria-describedby={describedBy}
                            />
                        )}
                    </Field>
                </div>
                <Field label="Notas" error={errors.notes}>
                    {({ id, invalid, describedBy }) => (
                        <Textarea
                            id={id}
                            rows={2}
                            value={data.notes}
                            onChange={(event) => form.setData('notes', event.target.value)}
                            invalid={invalid}
                            aria-describedby={describedBy}
                        />
                    )}
                </Field>
            </form>
        </Dialog>
    );
}
