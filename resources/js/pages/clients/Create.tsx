import { PageHeader } from '@/components/layout/PageHeader';
import { ClientForm } from '@/features/clients/ClientForm';
import { EMPTY_CLIENT } from '@/features/clients/types';

export default function ClientsCreate() {
    return (
        <>
            <PageHeader title="Nuevo cliente" eyebrow="Clientes" />
            <ClientForm
                initialValues={EMPTY_CLIENT}
                method="post"
                action={route('clients.store')}
                cancelHref={route('clients.index')}
                submitLabel="Registrar cliente"
            />
        </>
    );
}
