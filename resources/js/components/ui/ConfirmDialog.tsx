import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import type { ReactNode } from 'react';

type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    /** Texto del botón para salir sin confirmar. */
    cancelLabel?: string;
    /** `danger` para operaciones destructivas o irreversibles. */
    tone?: 'primary' | 'danger';
    processing?: boolean;
    onConfirm: () => void;
    /** Contenido extra (p. ej. motivo de anulación u opciones). */
    children?: ReactNode;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel = 'Cancelar',
    tone = 'primary',
    processing = false,
    onConfirm,
    children,
}: ConfirmDialogProps) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !processing && onOpenChange(next)}
            title={title}
            description={description}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={processing}>
                        {cancelLabel}
                    </Button>
                    <Button variant={tone} onClick={onConfirm} loading={processing}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            {children}
        </Dialog>
    );
}
