import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button, IconButton } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/Field';
import { Panel } from '@/components/ui/Panel';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp, Check, Pause, Pencil, Play, Plus, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type Method = {
    id: number;
    name: string;
    is_active: boolean;
    payments_count: number;
};

type PaymentMethodsProps = {
    methods: Method[];
};

export default function SettingsPaymentMethods({ methods }: PaymentMethodsProps) {
    const form = useForm({ name: '' });
    const [renaming, setRenaming] = useState<{
        id: number;
        name: string;
    } | null>(null);
    const [renameError, setRenameError] = useState<string | undefined>();

    function add(event: FormEvent) {
        event.preventDefault();
        form.post(route('settings.payment-methods.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function update(method: Method, changes: Partial<Pick<Method, 'name' | 'is_active'>>, onSuccess?: () => void) {
        router.patch(
            route('settings.payment-methods.update', method.id),
            { name: method.name, is_active: method.is_active, ...changes },
            {
                preserveScroll: true,
                onSuccess,
                onError: (errors) => {
                    if (errors.name) {
                        setRenameError(errors.name);
                    } else {
                        toast.error(Object.values(errors)[0] ?? 'No se pudo actualizar.');
                    }
                },
            },
        );
    }

    function saveRename(event: FormEvent, method: Method) {
        event.preventDefault();
        if (!renaming) return;
        update(method, { name: renaming.name }, () => setRenaming(null));
    }

    function move(method: Method, direction: 'up' | 'down') {
        router.post(route('settings.payment-methods.move', method.id), { direction }, { preserveScroll: true });
    }

    return (
        <>
            <Link href={route('settings.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900">
                <ArrowLeft className="size-4" aria-hidden /> Configuración
            </Link>
            <PageHeader
                title="Métodos de pago"
                eyebrow="Configuración"
                description="Aparecen en este orden al registrar un pago. Los métodos usados no se eliminan: se pausan y siguen visibles en los pagos antiguos."
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <Panel title="Métodos" flush>
                    <ul className="divide-y divide-line">
                        {methods.map((method, index) => (
                            <li key={method.id} className="flex items-center gap-3 px-4 py-3">
                                {renaming?.id === method.id ? (
                                    <form onSubmit={(event) => saveRename(event, method)} className="flex flex-1 items-start gap-2">
                                        <div className="flex-1">
                                            <Input
                                                value={renaming.name}
                                                onChange={(event) =>
                                                    setRenaming({
                                                        id: method.id,
                                                        name: event.target.value,
                                                    })
                                                }
                                                invalid={renameError !== undefined}
                                                aria-label="Nuevo nombre"
                                                autoFocus
                                            />
                                            {renameError && <p className="mt-1 text-xs text-danger-600">{renameError}</p>}
                                        </div>
                                        <IconButton type="submit" label="Guardar nombre">
                                            <Check />
                                        </IconButton>
                                        <IconButton label="Cancelar" onClick={() => setRenaming(null)}>
                                            <X />
                                        </IconButton>
                                    </form>
                                ) : (
                                    <>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-base font-medium text-ink-900">{method.name}</span>
                                            <span className="numeric block text-sm text-ink-500">
                                                {method.payments_count === 0 ? 'Sin pagos' : `${method.payments_count} pago(s)`}
                                                {!method.is_active && <span className="sm:hidden"> · Pausado</span>}
                                            </span>
                                        </span>
                                        <span className="max-sm:hidden">
                                            {method.is_active ? <Badge tone="success">Activo</Badge> : <Badge tone="neutral">Pausado</Badge>}
                                        </span>
                                        <IconButton label="Subir" onClick={() => move(method, 'up')} disabled={index === 0}>
                                            <ArrowUp />
                                        </IconButton>
                                        <IconButton label="Bajar" onClick={() => move(method, 'down')} disabled={index === methods.length - 1}>
                                            <ArrowDown />
                                        </IconButton>
                                        <IconButton
                                            label="Renombrar"
                                            onClick={() => {
                                                setRenameError(undefined);
                                                setRenaming({
                                                    id: method.id,
                                                    name: method.name,
                                                });
                                            }}
                                        >
                                            <Pencil />
                                        </IconButton>
                                        <IconButton
                                            label={method.is_active ? 'Pausar' : 'Activar'}
                                            onClick={() =>
                                                update(method, {
                                                    is_active: !method.is_active,
                                                })
                                            }
                                        >
                                            {method.is_active ? <Pause /> : <Play />}
                                        </IconButton>
                                    </>
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Agregar método" className="self-start">
                    <form onSubmit={add} noValidate className="flex flex-col gap-4">
                        <Field label="Nombre" error={form.errors.name}>
                            {({ id, invalid, describedBy }) => (
                                <Input
                                    id={id}
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    invalid={invalid}
                                    aria-describedby={describedBy}
                                    placeholder="Tarjeta (POS)"
                                />
                            )}
                        </Field>
                        <Button type="submit" loading={form.processing} className="self-start">
                            <Plus /> Agregar
                        </Button>
                    </form>
                </Panel>
            </div>
        </>
    );
}
