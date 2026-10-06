import { DateDisplay } from '@/components/data/DateDisplay';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { formatDate } from '@/lib/dates';
import type { Money } from '@/types/money';
import type { ScheduleItem } from './types';

type ScheduleListProps = {
    items: ScheduleItem[];
    price: Money | null;
    totalCycles: number | null;
};

/** Lista de próximos cobros: ciclo, vencimiento, periodo cubierto e importe. */
export function ScheduleList({ items, price, totalCycles }: ScheduleListProps) {
    if (items.length === 0) {
        return <p className="text-base text-ink-500">No hay cobros por generar.</p>;
    }

    return (
        <ol className="divide-y divide-line">
            {items.map((item) => (
                <li key={item.cycle} className="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                    <div className="min-w-0">
                        <p className="flex items-center gap-2 text-base text-ink-900">
                            <DateDisplay value={item.due_date} format="short" />
                            {item.adjusted && <span className="rounded-sm bg-warning-50 px-1.5 text-2xs font-medium text-warning-700">Fecha ajustada</span>}
                        </p>
                        <p className="numeric text-xs text-ink-500">
                            Ciclo {item.cycle}
                            {totalCycles ? ` de ${totalCycles}` : ''}
                            {item.period_start && item.period_end && ` · ${formatDate(item.period_start)} – ${formatDate(item.period_end)}`}
                        </p>
                    </div>
                    {price && <MoneyDisplay value={price} size="sm" />}
                </li>
            ))}
        </ol>
    );
}
