import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Field, Textarea } from '@/components/ui/Field';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

type ReasonDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    /** Ruta POST que recibe { reason }. */
    action: string;
    reasonRequired?: boolean;
};

/**
 * Confirmación destructiva con motivo (anular pago, cancelar cobro). El motivo queda en el historial.
 * Los errores de reglas de negocio que no son del campo (p. ej. "charge") se muestran como aviso.
 */
export function ReasonDialog({ open, onOpenChange, title, description, confirmLabel, action, reasonRequired = false }: ReasonDialogProps) {
    const form = useForm({ reason: '' });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    }, [open]);

    function confirm() {
        form.post(action, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (errors) => {
                const general = Object.entries(errors).find(([key]) => key !== 'reason');
                if (general) {
                    toast.error(general[1]);
                    onOpenChange(false);
                }
            },
        });
    }

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            confirmLabel={confirmLabel}
            cancelLabel="Volver"
            tone="danger"
            processing={form.processing}
            onConfirm={confirm}
        >
            <Field
                label="Motivo"
                error={form.errors.reason}
                required={reasonRequired}
                hint={reasonRequired ? 'Queda registrado en el historial.' : 'Opcional. Queda registrado en el historial.'}
            >
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
        </ConfirmDialog>
    );
}
