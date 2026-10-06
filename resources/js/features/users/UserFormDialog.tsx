import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Field, Input, Select } from '@/components/ui/Field';
import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import { PasswordFields } from './PasswordFields';
import type { ManagedUser, RoleOption } from './types';

type UserFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** null = nuevo usuario. */
    user: ManagedUser | null;
    roles: RoleOption[];
};

const FORM_ID = 'user-form';

export function UserFormDialog({ open, onOpenChange, user, roles }: UserFormDialogProps) {
    const form = useForm({ name: '', email: '', role: roles[0]?.value ?? 'admin', password: '', password_confirmation: '' });
    const { data, errors } = form;

    useEffect(() => {
        if (open) {
            form.setData({ name: user?.name ?? '', email: user?.email ?? '', role: user?.role ?? roles[0]?.value ?? 'admin', password: '', password_confirmation: '' });
            form.clearErrors();
        }
    }, [open, user?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (user) {
            form.transform(({ name, email, role }) => ({ name, email, role }));
            form.put(route('users.update', user.id), options);
        } else {
            form.transform((current) => current);
            form.post(route('users.store'), options);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !form.processing && onOpenChange(next)}
            title={user ? 'Editar usuario' : 'Nuevo usuario'}
            description={user ? 'La contraseña se cambia desde «Restablecer contraseña».' : 'Comparte la contraseña inicial por un canal privado; el usuario puede cambiarla desde «Mi cuenta».'}
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={form.processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={FORM_ID} loading={form.processing}>
                        {user ? 'Guardar cambios' : 'Crear usuario'}
                    </Button>
                </>
            }
        >
            <form id={FORM_ID} onSubmit={submit} noValidate className="flex flex-col gap-5">
                <Field label="Nombre" error={errors.name} required>
                    {({ id, invalid, describedBy }) => (
                        <Input id={id} value={data.name} onChange={(event) => form.setData('name', event.target.value)} invalid={invalid} aria-describedby={describedBy} autoComplete="off" />
                    )}
                </Field>
                <Field label="Correo" error={errors.email} required hint="Con este correo inicia sesión.">
                    {({ id, invalid, describedBy }) => (
                        <Input id={id} type="email" value={data.email} onChange={(event) => form.setData('email', event.target.value)} invalid={invalid} aria-describedby={describedBy} autoComplete="off" />
                    )}
                </Field>
                <Field label="Rol" error={errors.role}>
                    {({ id, invalid }) => (
                        <Select id={id} value={data.role} onChange={(event) => form.setData('role', event.target.value)} invalid={invalid} disabled={user?.is_current}>
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>
                {!user && (
                    <PasswordFields
                        password={data.password}
                        confirmation={data.password_confirmation}
                        onPasswordChange={(value) => form.setData('password', value)}
                        onConfirmationChange={(value) => form.setData('password_confirmation', value)}
                        error={errors.password}
                        label="Contraseña inicial"
                    />
                )}
            </form>
        </Dialog>
    );
}
