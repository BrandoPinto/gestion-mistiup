import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { MoneyInput } from '@/components/data/MoneyInput';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Field, Input, Select, Textarea } from '@/components/ui/Field';
import { cn } from '@/lib/cn';
import { useForm } from '@inertiajs/react';
import { Paperclip, X } from 'lucide-react';
import { useEffect, useRef, type FormEvent } from 'react';
import type { ChargeDetail, PaymentMethodOption } from './types';

type RegisterPaymentDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    charge: ChargeDetail;
    paymentMethods: PaymentMethodOption[];
    today: string;
};

type PaymentForm = {
    amount: string;
    paid_on: string;
    payment_method_id: string;
    reference: string;
    notes: string;
    receipt: File | null;
};

const FORM_ID = 'register-payment';
const MAX_BYTES = 5 * 1024 * 1024;

export function RegisterPaymentDialog({ open, onOpenChange, charge, paymentMethods, today }: RegisterPaymentDialogProps) {
    const fileRef = useRef<HTMLInputElement>(null);
    const form = useForm<PaymentForm>({
        amount: charge.balance.amount,
        paid_on: today,
        payment_method_id: String(paymentMethods[0]?.id ?? ''),
        reference: '',
        notes: '',
        receipt: null,
    });
    const { data, errors } = form;

    // Al abrir, proponer el saldo completo y la fecha de hoy.
    useEffect(() => {
        if (open) {
            form.setData((current) => ({
                ...current,
                amount: charge.balance.amount,
                paid_on: today,
                reference: '',
                notes: '',
                receipt: null,
            }));
            form.clearErrors();
        }
    }, [open, charge.balance.amount]);

    function chooseFile(file: File | undefined) {
        if (!file) return;
        if (file.size > MAX_BYTES) {
            form.setError('receipt', 'El comprobante no puede pesar más de 5 MB.');
            return;
        }
        form.clearErrors('receipt');
        form.setData('receipt', file);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(route('payments.store', charge.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !form.processing && onOpenChange(next)}
            title="Registrar pago"
            description={`${charge.description} · ${charge.client.name}`}
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={form.processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={FORM_ID} loading={form.processing}>
                        Registrar pago
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} noValidate className="flex flex-col gap-5">
                <div className="flex items-center justify-between rounded-md bg-cream-50 px-4 py-3">
                    <span className="eyebrow">Saldo pendiente</span>
                    <MoneyDisplay value={charge.balance} size="lg" />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Monto recibido" error={errors.amount} required>
                        {({ id, invalid, describedBy }) => (
                            <MoneyInput
                                id={id}
                                amount={data.amount}
                                currency={charge.balance.currency}
                                onAmountChange={(amount) => form.setData('amount', amount)}
                                invalid={invalid}
                                describedBy={describedBy}
                            />
                        )}
                    </Field>
                    <Field label="Fecha del pago" error={errors.paid_on} required>
                        {({ id, invalid, describedBy }) => (
                            <Input
                                id={id}
                                type="date"
                                max={today}
                                value={data.paid_on}
                                onChange={(event) => form.setData('paid_on', event.target.value)}
                                invalid={invalid}
                                aria-describedby={describedBy}
                            />
                        )}
                    </Field>
                    <Field label="Método" error={errors.payment_method_id} required>
                        {({ id, invalid }) => (
                            <Select
                                id={id}
                                value={data.payment_method_id}
                                onChange={(event) => form.setData('payment_method_id', event.target.value)}
                                invalid={invalid}
                            >
                                {paymentMethods.map((method) => (
                                    <option key={method.id} value={method.id}>
                                        {method.name}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </Field>
                    <Field label="N.º de operación" error={errors.reference}>
                        {({ id, invalid, describedBy }) => (
                            <Input
                                id={id}
                                value={data.reference}
                                onChange={(event) => form.setData('reference', event.target.value)}
                                invalid={invalid}
                                aria-describedby={describedBy}
                                className="numeric"
                            />
                        )}
                    </Field>
                </div>

                <Field label="Comprobante" error={errors.receipt} hint={data.receipt ? undefined : 'Opcional. Imagen o PDF de hasta 5 MB.'}>
                    {({ id, describedBy }) => (
                        <div>
                            <input
                                ref={fileRef}
                                id={id}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                className="sr-only"
                                aria-describedby={describedBy}
                                onChange={(event) => chooseFile(event.target.files?.[0])}
                            />
                            {data.receipt ? (
                                <div className="flex h-10 items-center gap-2 rounded-sm border border-line bg-cream-50 px-3 text-base">
                                    <Paperclip className="size-4 shrink-0 text-ink-500" aria-hidden />
                                    <span className="min-w-0 flex-1 truncate">{data.receipt.name}</span>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            form.setData('receipt', null);
                                            if (fileRef.current) fileRef.current.value = '';
                                        }}
                                        aria-label="Quitar comprobante"
                                        className="flex size-7 items-center justify-center rounded-sm text-ink-500 hover:bg-cream-100"
                                    >
                                        <X className="size-4" />
                                    </button>
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => fileRef.current?.click()}
                                    className={cn(
                                        'flex h-10 w-full items-center justify-center gap-2 rounded-sm border border-dashed text-base text-ink-500 transition-colors hover:border-brand-600 hover:text-brand-700',
                                        errors.receipt ? 'border-danger-600' : 'border-line-strong',
                                    )}
                                >
                                    <Paperclip className="size-4" aria-hidden /> Adjuntar archivo
                                </button>
                            )}
                            {form.progress && <progress value={form.progress.percentage} max={100} className="mt-2 h-1 w-full accent-brand-600" />}
                        </div>
                    )}
                </Field>

                <Field label="Nota" error={errors.notes}>
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
