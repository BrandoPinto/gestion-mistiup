import { PageHeader } from '@/components/layout/PageHeader';
import { ClientForm } from '@/features/clients/ClientForm';
import type { ClientFormValues } from '@/features/clients/types';

type ClientsEditProps = {
    client: { id: number; name: string };
    values: ClientFormValues;
};

export default function ClientsEdit({ client, values }: ClientsEditProps) {
    return (
        <>
            <PageHeader title="Editar cliente" eyebrow={client.name} />
            <ClientForm
                initialValues={values}
                method="put"
                action={route('clients.update', client.id)}
                cancelHref={route('clients.show', client.id)}
                submitLabel="Guardar cambios"
            />
        </>
    );
}
