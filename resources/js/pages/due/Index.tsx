import { MoneyByCurrency } from '@/components/data/MoneyByCurrency';
import { PageHeader } from '@/components/layout/PageHeader';
import { Panel } from '@/components/ui/Panel';
import { ChargesTable } from '@/features/charges/ChargesTable';
import type { ChargeListItem } from '@/features/charges/types';
import { ContractsTable } from '@/features/contracts/ContractsTable';
import type { ContractListItem } from '@/features/contracts/types';
import { cn } from '@/lib/cn';
import type { Currency } from '@/types/money';

type DueGroup = {
    key: 'overdue' | 'week' | 'month' | 'later';
    charges: ChargeListItem[];
    totals: { amount: string; currency: Currency }[];
};

type DueIndexProps = {
    groups: DueGroup[];
    contracts: ContractListItem[];
    horizon_days: number;
};

const GROUP_META: Record<DueGroup['key'], { title: string; eyebrow: string; tone: 'danger' | 'default' }> = {
    overdue: {
        title: 'Vencidos',
        eyebrow: 'Requiere atención',
        tone: 'danger',
    },
    week: { title: 'Próximos 7 días', eyebrow: 'Esta semana', tone: 'default' },
    month: { title: 'De 8 a 30 días', eyebrow: 'Este mes', tone: 'default' },
    later: {
        title: 'De 31 a 90 días',
        eyebrow: 'Más adelante',
        tone: 'default',
    },
};

/** Vista de control: qué hay que cobrar, ordenado por urgencia, y qué contratos terminan pronto. */
export default function DueIndex({ groups, contracts, horizon_days }: DueIndexProps) {
    return (
        <>
            <PageHeader title="Vencimientos" eyebrow="Control" description={`Cobros abiertos y contratos que terminan en los próximos ${horizon_days} días.`} />

            {/* Resumen por tramo: saldo por moneda. */}
            <div className="mb-6 grid grid-cols-2 overflow-hidden rounded-lg border border-line bg-surface md:grid-cols-4">
                {groups.map((group) => (
                    <a
                        key={group.key}
                        href={`#${group.key}`}
                        className="-mr-px -mb-px flex flex-col gap-2 border-r border-b border-line px-4 py-4 transition-colors hover:bg-cream-50 sm:px-5"
                    >
                        <span className="eyebrow">{GROUP_META[group.key].title}</span>
                        <MoneyByCurrency
                            items={group.totals}
                            size="lg"
                            tone={group.key === 'overdue' && group.charges.length > 0 ? 'danger' : 'default'}
                            empty="—"
                        />
                        <span className="numeric text-xs text-ink-500">{group.charges.length === 1 ? '1 cobro' : `${group.charges.length} cobros`}</span>
                    </a>
                ))}
            </div>

            <div className="flex flex-col gap-6">
                {groups
                    .filter((group) => group.charges.length > 0)
                    .map((group) => (
                        <section key={group.key} id={group.key} className="scroll-mt-20">
                            <Panel
                                flush
                                title={GROUP_META[group.key].title}
                                eyebrow={GROUP_META[group.key].eyebrow}
                                className={cn(group.key === 'overdue' && 'border-danger-600/30')}
                            >
                                <ChargesTable charges={group.charges} empty={{ title: '' }} />
                            </Panel>
                        </section>
                    ))}

                {groups.every((group) => group.charges.length === 0) && (
                    <Panel>
                        <p className="py-6 text-center text-base text-ink-500">No hay cobros abiertos en los próximos {horizon_days} días.</p>
                    </Panel>
                )}

                <Panel flush title="Contratos que terminan" eyebrow={`Próximos ${horizon_days} días`}>
                    <ContractsTable
                        contracts={contracts}
                        empty={{
                            title: 'Ningún contrato termina pronto',
                            description: 'Los contratos con duración aparecen aquí antes de su fecha de fin.',
                        }}
                    />
                </Panel>
            </div>
        </>
    );
}
