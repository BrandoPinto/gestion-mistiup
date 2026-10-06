import { PageHeader } from '@/components/layout/PageHeader';
import type { CatalogService } from '@/features/catalog/types';
import type { ClientOption } from '@/features/clients/ClientSelector';
import { ContractForm } from '@/features/contracts/ContractForm';
import type { ContractFormValues } from '@/features/contracts/types';

type ContractsEditProps = {
    contract: { id: number; name: string; schedule_locked: boolean };
    client: ClientOption;
    values: ContractFormValues;
    catalog: CatalogService[];
};

export default function ContractsEdit({ contract, client, values, catalog }: ContractsEditProps) {
    return (
        <>
            <PageHeader
                title="Editar servicio contratado"
                eyebrow={`${client.name} · ${contract.name}`}
                description="Un cambio de precio solo afecta a los cobros que todavía no se generaron."
            />
            <ContractForm
                initialValues={values}
                initialClient={client}
                catalog={catalog}
                method="put"
                action={route('contracts.update', contract.id)}
                cancelHref={route('contracts.show', contract.id)}
                submitLabel="Guardar cambios"
                lockClient
                scheduleLocked={contract.schedule_locked}
            />
        </>
    );
}
