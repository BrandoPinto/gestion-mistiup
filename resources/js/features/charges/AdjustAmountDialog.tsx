import { MoneyInput } from '@/components/data/MoneyInput';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Field, Textarea } from '@/components/ui/Field';
import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import type { ChargeDetail } from './types';

type AdjustAmountDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    charge: ChargeDetail;
};

const FORM_ID = 'adjust-amount';

/** Cambiar el importe de un cobro sin pagos (descuento puntual, corrección). El motivo queda en el historial. */
export function AdjustAmountDialog({ open, onOpenChange, charge }: AdjustAmountDialogProps) {
    const form = useForm({ amount: charge.amount.amount, reason: '' });

    useEffect(() => {
        if (open) {
            form.setData({ amount: charge.amount.amount, reason: '' });
            form.clearErrors();
        }
    }, [open, charge.amount.amount]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(route('charges.amount', charge.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !form.processing && onOpenChange(next)}
            title="Ajustar importe"
            description="Solo es posible mientras el cobro no tenga pagos."
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={form.processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={FORM_ID} loading={form.processing}>
                        Guardar importe
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} noValidate className="flex flex-col gap-4">
                <Field label="Nuevo importe" error={form.errors.amount} required>
                    {({ id, invalid, describedBy }) => (
                        <MoneyInput
                            id={id}
                            amount={form.data.amount}
                            currency={charge.amount.currency}
                            onAmountChange={(amount) => form.setData('amount', amount)}
                            invalid={invalid}
                            describedBy={describedBy}
                        />
                    )}
                </Field>
                <Field label="Motivo" error={form.errors.reason} required hint="Por ejemplo: descuento por pronto pago.">
                    {({ id, invalid, describedBy }) => (
                        <Textarea
                            id={id}
                            rows={2}
                            value={form.data.reason}
                            onChange={(event) => form.setData('reason', event.target.value)}
                            invalid={invalid}
                            aria-describedby={describedBy}
                        />
                    )}
                </Field>
            </form>
        </Dialog>
    );
}
