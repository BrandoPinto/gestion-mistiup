import { formatTimestamp } from '@/lib/dates';

export type ActivityItem = {
    id: number;
    action: string;
    label: string;
    /** Dato relevante de la acción: importe, motivo, cantidad. */
    detail: string | null;
    user: string | null;
    fields: string[];
    created_at: string;
};

type ActivityListProps = {
    items: ActivityItem[];
    /** Traduce nombres de campo técnicos a etiquetas legibles. */
    fieldLabels?: Record<string, string>;
};

/** Historial compacto de acciones (log de auditoría) en forma de línea de tiempo. */
export function ActivityList({ items, fieldLabels = {} }: ActivityListProps) {
    if (items.length === 0) {
        return <p className="text-base text-ink-500">Sin actividad registrada.</p>;
    }

    return (
        <ol className="relative flex flex-col gap-4 before:absolute before:top-2 before:bottom-2 before:left-[3px] before:w-px before:bg-line">
            {items.map((item) => (
                <li key={item.id} className="relative pl-5">
                    <span aria-hidden className="absolute top-[7px] left-0 size-[7px] rounded-full border border-ink-300 bg-surface" />
                    <p className="text-base text-ink-900">{item.label}</p>
                    {item.detail && <p className="numeric text-sm text-ink-700">{item.detail}</p>}
                    {item.fields.length > 0 && (
                        <p className="text-sm text-ink-500">{item.fields.map((field) => fieldLabels[field] ?? field).join(', ')}</p>
                    )}
                    <p className="numeric text-xs text-ink-400">
                        {formatTimestamp(item.created_at)}
                        {item.user && ` · ${item.user}`}
                    </p>
                </li>
            ))}
        </ol>
    );
}
