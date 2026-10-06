import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Panel } from '@/components/ui/Panel';
import { Skeleton } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/dates';
import { isValidAmount, multiplyAmount } from '@/lib/money';
import { CircleAlert } from 'lucide-react';
import { ScheduleList } from './ScheduleList';
import type { ContractFormValues, SchedulePreview } from './types';

type SchedulePanelProps = {
    values: ContractFormValues;
    preview: SchedulePreview | null;
    error: string | null;
    loading: boolean;
};

const VISIBLE_CYCLES = 5;

/** Resumen en vivo del contrato: modalidad, vigencia y los próximos cobros que generará el sistema. */
export function SchedulePanel({ values, preview, error, loading }: SchedulePanelProps) {
    const price = isValidAmount(values.price) ? { amount: values.price, currency: values.currency } : null;
    const firstCycle = Number(values.first_cycle) || null;

    const upcoming = (preview?.cycles ?? [])
        .filter((item) => firstCycle !== null && item.cycle >= firstCycle)
        .slice(0, VISIBLE_CYCLES)
        .map((item, index) => (index === 0 && values.first_charge_date && values.first_charge_date !== item.due_date ? { ...item, due_date: values.first_charge_date, adjusted: true } : item));

    const remainingCycles = preview?.total_cycles && firstCycle ? preview.total_cycles - firstCycle + 1 : null;

    return (
        <Panel title="Calendario de cobros" eyebrow="Vista previa">
            {error ? (
                <p className="flex items-start gap-2 text-base text-danger-700">
                    <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden /> {error}
                </p>
            ) : !preview ? (
                loading ? (
                    <div className="flex flex-col gap-3">
                        <Skeleton className="h-4 w-1/2" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                ) : (
                    <p className="text-base text-ink-500">Completa la modalidad y la fecha de inicio para ver los cobros.</p>
                )
            ) : (
                <div className="flex flex-col gap-4">
                    <dl className="grid grid-cols-2 gap-3 border-b border-line pb-4">
                        <div>
                            <dt className="eyebrow mb-0.5">Modalidad</dt>
                            <dd className="text-base text-ink-900">{preview.label}</dd>
                        </div>
                        <div>
                            <dt className="eyebrow mb-0.5">Precio</dt>
                            <dd>{price ? <MoneyDisplay value={price} /> : <span className="text-ink-400">—</span>}</dd>
                        </div>
                        <div>
                            <dt className="eyebrow mb-0.5">Vigencia</dt>
                            <dd className="text-base text-ink-900">{preview.end_date ? `Hasta ${formatDate(preview.end_date)}` : values.billing_type === 'recurring' ? 'Sin fecha de fin' : 'Pago único'}</dd>
                        </div>
                        {remainingCycles !== null && remainingCycles > 0 && price && (
                            <div>
                                <dt className="eyebrow mb-0.5">Por cobrar ({remainingCycles})</dt>
                                <dd>
                                    <MoneyDisplay value={{ amount: multiplyAmount(price.amount, remainingCycles), currency: price.currency }} />
                                </dd>
                            </div>
                        )}
                    </dl>

                    {preview.suggested_first_cycle === null ? (
                        <p className="text-base text-ink-500">Según estas fechas, el contrato ya terminó: no quedan cobros por generar.</p>
                    ) : (
                        <>
                            <ScheduleList items={upcoming} price={price} totalCycles={preview.total_cycles} />
                            <p className="text-xs text-ink-500">
                                El sistema generará cada cobro con anticipación a su vencimiento.
                            </p>
                        </>
                    )}
                </div>
            )}
        </Panel>
    );
}
