import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button, IconButton } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { DropdownItem, DropdownMenu } from '@/components/ui/DropdownMenu';
import { Table, TBody, Td, Th, THead, Tr } from '@/components/ui/Table';
import { ResetPasswordDialog } from '@/features/users/ResetPasswordDialog';
import { UserFormDialog } from '@/features/users/UserFormDialog';
import type { ManagedUser, RoleOption } from '@/features/users/types';
import { formatTimestamp } from '@/lib/dates';
import { router } from '@inertiajs/react';
import { KeyRound, MoreHorizontal, Pencil, Plus, UserCheck, UserX } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type UsersIndexProps = {
    users: ManagedUser[];
    roles: RoleOption[];
};

export default function UsersIndex({ users, roles }: UsersIndexProps) {
    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [resetting, setResetting] = useState<ManagedUser | null>(null);
    const [toggling, setToggling] = useState<ManagedUser | null>(null);
    const [processing, setProcessing] = useState(false);

    function openForm(user: ManagedUser | null) {
        setEditing(user);
        setFormOpen(true);
    }

    function confirmToggle() {
        if (!toggling) return;

        router.patch(
            route('users.status', toggling.id),
            { is_active: !toggling.is_active },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (errors) => toast.error(Object.values(errors)[0] ?? 'No se pudo cambiar el estado.'),
                onFinish: () => {
                    setProcessing(false);
                    setToggling(null);
                },
            },
        );
    }

    return (
        <>
            <PageHeader
                title="Usuarios"
                eyebrow="Sistema"
                description="Personas con acceso al sistema. Los usuarios no se eliminan: se desactivan y conservan su historial."
                actions={
                    <Button onClick={() => openForm(null)}>
                        <Plus /> Nuevo usuario
                    </Button>
                }
            />

            <div className="overflow-hidden rounded-lg border border-line bg-surface">
                <Table>
                    <THead>
                        <tr>
                            <Th>Nombre</Th>
                            <Th className="max-md:hidden">Correo</Th>
                            <Th className="max-lg:hidden">Rol</Th>
                            <Th>Estado</Th>
                            <Th className="max-md:hidden">Último ingreso</Th>
                            <Th align="right">
                                <span className="sr-only">Acciones</span>
                            </Th>
                        </tr>
                    </THead>
                    <TBody>
                        {users.map((user) => (
                            <Tr key={user.id}>
                                <Td>
                                    <div className="max-w-[52vw] sm:max-w-sm">
                                        <p className="truncate font-medium text-ink-900">
                                            {user.name}
                                            {user.is_current && <span className="ml-2 text-sm font-normal text-ink-500">(tú)</span>}
                                        </p>
                                        <p className="truncate text-xs text-ink-500 md:hidden">{user.email}</p>
                                    </div>
                                </Td>
                                <Td className="max-md:hidden">{user.email}</Td>
                                <Td className="max-lg:hidden">{user.role_label}</Td>
                                <Td>{user.is_active ? <Badge tone="success">Activo</Badge> : <Badge tone="neutral">Inactivo</Badge>}</Td>
                                <Td numeric align="left" className="max-md:hidden">
                                    {user.last_login_at ? formatTimestamp(user.last_login_at) : <span className="text-ink-400">Nunca</span>}
                                </Td>
                                <Td align="right">
                                    <DropdownMenu
                                        align="end"
                                        trigger={
                                            <IconButton label={`Acciones de ${user.name}`}>
                                                <MoreHorizontal />
                                            </IconButton>
                                        }
                                    >
                                        <DropdownItem icon={Pencil} onSelect={() => openForm(user)}>
                                            Editar datos
                                        </DropdownItem>
                                        <DropdownItem icon={KeyRound} onSelect={() => setResetting(user)}>
                                            Restablecer contraseña
                                        </DropdownItem>
                                        {!user.is_current && (
                                            <DropdownItem icon={user.is_active ? UserX : UserCheck} onSelect={() => setToggling(user)}>
                                                {user.is_active ? 'Desactivar' : 'Activar'}
                                            </DropdownItem>
                                        )}
                                    </DropdownMenu>
                                </Td>
                            </Tr>
                        ))}
                    </TBody>
                </Table>
            </div>

            <UserFormDialog open={formOpen} onOpenChange={setFormOpen} user={editing} roles={roles} />
            <ResetPasswordDialog user={resetting} onClose={() => setResetting(null)} />
            <ConfirmDialog
                open={toggling !== null}
                onOpenChange={(open) => !open && !processing && setToggling(null)}
                title={toggling?.is_active ? `¿Desactivar a ${toggling.name}?` : `¿Activar a ${toggling?.name ?? ''}?`}
                description={
                    toggling?.is_active
                        ? 'No podrá ingresar y se cerrarán sus sesiones abiertas. Su historial se conserva y puedes reactivarlo cuando quieras.'
                        : 'Podrá volver a ingresar con su contraseña actual.'
                }
                confirmLabel={toggling?.is_active ? 'Desactivar' : 'Activar'}
                tone={toggling?.is_active ? 'danger' : 'primary'}
                processing={processing}
                onConfirm={confirmToggle}
            />
        </>
    );
}
