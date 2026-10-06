import { PageHeader } from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { PasswordFields } from '@/features/users/PasswordFields';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type AccountEditProps = {
    account: { name: string; email: string };
};

export default function AccountEdit({ account }: AccountEditProps) {
    const profile = useForm({ name: account.name, email: account.email });
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function saveProfile(event: FormEvent) {
        event.preventDefault();
        profile.put(route('account.update'), { preserveScroll: true });
    }

    function savePassword(event: FormEvent) {
        event.preventDefault();
        password.put(route('account.password'), {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    }

    return (
        <>
            <PageHeader title="Mi cuenta" eyebrow="Sistema" />

            <div className="grid max-w-4xl gap-6 lg:grid-cols-2">
                <Panel title="Datos personales" className="self-start">
                    <form onSubmit={saveProfile} noValidate className="flex flex-col gap-5">
                        <Field label="Nombre" error={profile.errors.name} required>
                            {({ id, invalid, describedBy }) => (
                                <Input
                                    id={id}
                                    value={profile.data.name}
                                    onChange={(event) => profile.setData('name', event.target.value)}
                                    invalid={invalid}
                                    aria-describedby={describedBy}
                                    autoComplete="name"
                                />
                            )}
                        </Field>
                        <Field label="Correo" error={profile.errors.email} required hint="Con este correo inicias sesión.">
                            {({ id, invalid, describedBy }) => (
                                <Input
                                    id={id}
                                    type="email"
                                    value={profile.data.email}
                                    onChange={(event) => profile.setData('email', event.target.value)}
                                    invalid={invalid}
                                    aria-describedby={describedBy}
                                    autoComplete="email"
                                />
                            )}
                        </Field>
                        <Button type="submit" loading={profile.processing} className="self-start">
                            Guardar datos
                        </Button>
                    </form>
                </Panel>

                <Panel title="Cambiar contraseña" className="self-start">
                    <form onSubmit={savePassword} noValidate className="flex flex-col gap-5">
                        <Field label="Contraseña actual" error={password.errors.current_password} required>
                            {({ id, invalid, describedBy }) => (
                                <Input
                                    id={id}
                                    type="password"
                                    value={password.data.current_password}
                                    onChange={(event) => password.setData('current_password', event.target.value)}
                                    invalid={invalid}
                                    aria-describedby={describedBy}
                                    autoComplete="current-password"
                                />
                            )}
                        </Field>
                        <PasswordFields
                            password={password.data.password}
                            confirmation={password.data.password_confirmation}
                            onPasswordChange={(value) => password.setData('password', value)}
                            onConfirmationChange={(value) => password.setData('password_confirmation', value)}
                            error={password.errors.password}
                        />
                        <p className="text-sm text-ink-500">Al cambiarla se cierran tus sesiones en otros equipos; esta sigue abierta.</p>
                        <Button type="submit" loading={password.processing} className="self-start">
                            Cambiar contraseña
                        </Button>
                    </form>
                </Panel>
            </div>
        </>
    );
}
