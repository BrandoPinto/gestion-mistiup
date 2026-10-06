import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import { PasswordFields } from './PasswordFields';
import type { ManagedUser } from './types';

type ResetPasswordDialogProps = {
    user: ManagedUser | null;
    onClose: () => void;
};

const FORM_ID = 'reset-password-form';

export function ResetPasswordDialog({ user, onClose }: ResetPasswordDialogProps) {
    const form = useForm({ password: '', password_confirmation: '' });

    useEffect(() => {
        if (user) {
            form.reset();
            form.clearErrors();
        }
    }, [user?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (user) {
            form.post(route('users.password', user.id), { preserveScroll: true, onSuccess: onClose });
        }
    }

    return (
        <Dialog
            open={user !== null}
            onOpenChange={(open) => !open && !form.processing && onClose()}
            title="Restablecer contraseña"
            description={user ? `${user.name} deberá usar la nueva contraseña. Se cerrarán sus sesiones abiertas en otros equipos.` : undefined}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} disabled={form.processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={FORM_ID} loading={form.processing}>
                        Guardar contraseña
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} noValidate className="flex flex-col gap-5">
                <PasswordFields
                    password={form.data.password}
                    confirmation={form.data.password_confirmation}
                    onPasswordChange={(value) => form.setData('password', value)}
                    onConfirmationChange={(value) => form.setData('password_confirmation', value)}
                    error={form.errors.password}
                />
            </form>
        </Dialog>
    );
}
