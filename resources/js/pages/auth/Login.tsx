import { LogoMark } from '@/components/brand/LogoMark';
import { Button } from '@/components/ui/Button';
import { Checkbox, Field, Input } from '@/components/ui/Field';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import type { FormEvent } from 'react';

export default function Login() {
    const { app, flash } = usePage().props;
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(route('login.store'), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title="Iniciar sesión" />
            <div className="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                {/* Panel institucional: solo en escritorio. */}
                <aside className="relative hidden flex-col justify-between overflow-hidden bg-navy-900 p-12 text-cream-100 lg:flex">
                    <div className="flex items-center gap-3">
                        <LogoMark className="h-8 w-auto" />
                        <span className="text-md font-semibold tracking-[-0.01em] text-cream-50">{app.name}</span>
                    </div>

                    <div className="max-w-md">
                        <p className="mb-4 text-2xs font-semibold tracking-[0.12em] text-brand-500 uppercase">Administración</p>
                        <p className="text-2xl leading-tight font-semibold tracking-[-0.02em] text-cream-50">
                            Clientes, servicios, cobros y cotizaciones en un solo lugar.
                        </p>
                        <div className="mt-8 h-px w-16 bg-cream-100/30" />
                    </div>

                    <LogoMark className="pointer-events-none absolute -right-24 -bottom-16 h-80 w-auto opacity-[0.06]" />
                </aside>

                <main className="flex items-center justify-center bg-canvas px-5 py-12 sm:px-10">
                    <div className="w-full max-w-sm">
                        <div className="mb-10 flex items-center gap-3 lg:hidden">
                            <span className="flex size-10 items-center justify-center rounded-md bg-navy-900">
                                <LogoMark className="h-5 w-auto" />
                            </span>
                            <span className="text-md font-semibold text-ink-900">{app.name}</span>
                        </div>

                        <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink-900">Iniciar sesión</h1>
                        <p className="mt-1 text-base text-ink-500">Ingresa con tu correo y contraseña.</p>

                        {flash.error && (
                            <div
                                role="alert"
                                className="mt-6 flex items-start gap-2 rounded-md border border-danger-600/20 bg-danger-50 px-3 py-2.5 text-base text-danger-700"
                            >
                                <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                                {flash.error}
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-8 flex flex-col gap-5" noValidate>
                            <Field label="Correo" error={form.errors.email}>
                                {({ id, invalid, describedBy }) => (
                                    <Input
                                        id={id}
                                        type="email"
                                        autoComplete="username"
                                        autoFocus
                                        value={form.data.email}
                                        onChange={(event) => form.setData('email', event.target.value)}
                                        invalid={invalid}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </Field>

                            <Field label="Contraseña" error={form.errors.password}>
                                {({ id, invalid, describedBy }) => (
                                    <Input
                                        id={id}
                                        type="password"
                                        autoComplete="current-password"
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        invalid={invalid}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </Field>

                            <Checkbox
                                label="Mantener sesión iniciada"
                                checked={form.data.remember}
                                onChange={(event) => form.setData('remember', event.target.checked)}
                            />

                            <Button type="submit" loading={form.processing} className="mt-2 w-full">
                                Ingresar
                            </Button>
                        </form>
                    </div>
                </main>
            </div>
        </>
    );
}
