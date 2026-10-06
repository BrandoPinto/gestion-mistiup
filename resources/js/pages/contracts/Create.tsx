import { PageHeader } from '@/components/layout/PageHeader';
import type { CatalogService } from '@/features/catalog/types';
import type { ClientOption } from '@/features/clients/ClientSelector';
import { ContractForm } from '@/features/contracts/ContractForm';
import type { ContractFormValues } from '@/features/contracts/types';
import { todayInBusinessZone } from '@/lib/dates';

type ContractsCreateProps = {
    client: ClientOption | null;
    catalog: CatalogService[];
};

export default function ContractsCreate({ client, catalog }: ContractsCreateProps) {
    const today = todayInBusinessZone();

    const initialValues: ContractFormValues = {
        client_id: client?.id ?? null,
        service_id: null,
        name: '',
        description: '',
        currency: 'PEN',
        price: '',
        billing_type: 'recurring',
        interval_unit: 'year',
        interval_count: '1',
        start_date: today,
        has_term: false,
        term_unit: 'year',
        term_count: '',
        first_cycle: '1',
        first_charge_date: today,
        notes: '',
    };

    return (
        <>
            <PageHeader title="Contratar servicio" eyebrow={client ? client.name : 'Servicios contratados'} />
            <ContractForm
                initialValues={initialValues}
                initialClient={client}
                catalog={catalog}
                method="post"
                action={route('contracts.store')}
                cancelHref={
                    client
                        ? route('clients.show', {
                              client: client.id,
                              tab: 'services',
                          })
                        : route('contracts.index')
                }
                submitLabel="Registrar servicio"
            />
        </>
    );
}
