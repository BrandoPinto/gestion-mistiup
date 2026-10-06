import { DateDisplay } from '@/components/data/DateDisplay';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Badge } from '@/components/ui/Badge';
import { IconButton } from '@/components/ui/Button';
import { Tooltip } from '@/components/ui/Tooltip';
import { cn } from '@/lib/cn';
import { formatTimestamp } from '@/lib/dates';
import { Ban, FileText, ImageIcon } from 'lucide-react';
import type { PaymentItem } from './types';

type PaymentListProps = {
    payments: PaymentItem[];
    /** Si se indica, cada pago válido muestra la acción de anular. */
    onVoid?: (payment: PaymentItem) => void;
};

/** Historial de pagos de un cobro. Los anulados se muestran tachados con su motivo: nunca desaparecen. */
export function PaymentList({ payments, onVoid }: PaymentListProps) {
    if (payments.length === 0) {
        return <p className="text-base text-ink-500">Aún no se registraron pagos.</p>;
    }

    return (
        <ul className="divide-y divide-line">
            {payments.map((payment) => {
                const voided = payment.voided_at !== null;

                return (
                    <li key={payment.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <MoneyDisplay value={payment.amount} className={cn(voided && 'line-through opacity-60')} />
                                <span className="text-sm text-ink-500">
                                    {payment.method}
                                    {payment.reference && <span className="numeric"> · {payment.reference}</span>}
                                </span>
                                {voided && <Badge tone="danger">Anulado</Badge>}
                            </div>
                            <p className="text-xs text-ink-500">
                                <DateDisplay value={payment.paid_on} format="short" />
                                {payment.created_by && ` · registrado por ${payment.created_by}`}
                            </p>
                            {payment.notes && <p className="mt-1 text-sm text-ink-700">{payment.notes}</p>}
                            {voided && (
                                <p className="mt-1 text-sm text-danger-700">
                                    Anulado el {formatTimestamp(payment.voided_at!)}
                                    {payment.void_reason && ` · ${payment.void_reason}`}
                                </p>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-1">
                            {payment.receipt && (
                                <Tooltip content={`Ver comprobante: ${payment.receipt.name}`}>
                                    <a
                                        href={payment.receipt.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex size-9 items-center justify-center rounded-sm text-ink-500 transition-colors hover:bg-cream-100 hover:text-ink-900 max-sm:size-11"
                                        aria-label="Ver comprobante"
                                    >
                                        {payment.receipt.mime_type === 'application/pdf' ? <FileText className="size-[18px]" /> : <ImageIcon className="size-[18px]" />}
                                    </a>
                                </Tooltip>
                            )}
                            {onVoid && !voided && (
                                <Tooltip content="Anular pago">
                                    <IconButton label="Anular pago" onClick={() => onVoid(payment)}>
                                        <Ban />
                                    </IconButton>
                                </Tooltip>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
