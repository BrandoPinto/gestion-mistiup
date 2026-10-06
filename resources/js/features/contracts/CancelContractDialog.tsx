import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Field, Textarea } from '@/components/ui/Field';
import { cn } from '@/lib/cn';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

type CancelContractDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    contract: { id: number; name: string };
};

const OPTIONS = [
    { value: true, label: 'Cancelar también cobros pendientes', hint: 'Los cobros con pagos parciales se mantienen para que los revises.' },
    { value: false, label: 'Mantener cobros pendientes existentes', hint: 'Solo deja de generar cobros nuevos.' },
] as const;

export function CancelContractDialog({ open, onOpenChange, contract }: CancelContractDialogProps) {
    const form = useForm({ reason: '', cancel_pending_charges: true });

    function confirm() {
        form.post(route('contracts.cancel', contract.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
            onError: (errors) => {
                if (errors.contract) toast.error(errors.contract);
            },
        });
    }

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="¿Cancelar este contrato?"
            description={`«${contract.name}» dejará de generar cobros. El contrato y su historial se conservan.`}
            confirmLabel="Cancelar contrato"
            cancelLabel="Volver"
            tone="danger"
            processing={form.processing}
            onConfirm={confirm}
        >
            <div className="flex flex-col gap-4">
                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1.5 text-sm font-medium text-ink-700">Cobros ya generados</legend>
                    {OPTIONS.map((option) => (
                        <label
                            key={String(option.value)}
                            className={cn(
                                'flex cursor-pointer gap-3 rounded-md border px-3 py-2.5 transition-colors',
                                form.data.cancel_pending_charges === option.value ? 'border-brand-600 bg-brand-50' : 'border-line hover:border-line-strong',
                            )}
                        >
                            <input
                                type="radio"
                                name="cancel_pending_charges"
                                checked={form.data.cancel_pending_charges === option.value}
                                onChange={() => form.setData('cancel_pending_charges', option.value)}
                                className="mt-0.5 accent-brand-600"
                            />
                            <span>
                                <span className="block text-base text-ink-900">{option.label}</span>
                                <span className="block text-xs text-ink-500">{option.hint}</span>
                            </span>
                        </label>
                    ))}
                </fieldset>

                <Field label="Motivo" error={form.errors.reason} hint="Opcional. Queda registrado en el historial.">
                    {({ id, invalid, describedBy }) => (
                        <Textarea id={id} rows={2} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} invalid={invalid} aria-describedby={describedBy} />
                    )}
                </Field>
            </div>
        </ConfirmDialog>
    );
}
