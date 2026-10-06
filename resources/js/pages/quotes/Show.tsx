import { ActivityList, type ActivityItem } from '@/components/data/ActivityList';
import { DateDisplay } from '@/components/data/DateDisplay';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { Button, buttonClasses } from '@/components/ui/Button';
import { Panel } from '@/components/ui/Panel';
import { Badge } from '@/components/ui/Badge';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { ReasonDialog } from '@/features/charges/ReasonDialog';
import { ContractStatusBadge } from '@/features/contracts/ContractStatusBadge';
import type { ContractStatus } from '@/features/contracts/types';
import { QuotePreview } from '@/features/quotes/QuotePreview';
import { QuoteStatusBadge } from '@/features/quotes/QuoteStatusBadge';
import type { CompanyBlock, QuoteDocument, QuoteListItem } from '@/features/quotes/types';
import { useCopyQuoteLink } from '@/features/quotes/useCopyQuoteLink';
import { formatTimestamp } from '@/lib/dates';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, Ban, ExternalLink, FileDown, Link2, Pencil, RefreshCw, Send, Undo2 } from 'lucide-react';
import { useState } from 'react';

type QuotesShowProps = {
    quote: QuoteListItem & {
        internal_notes: string | null;
        sent_at: string | null;
        cancelled_at: string | null;
        cancel_reason: string | null;
        editable: boolean;
        public_url: string;
        public_enabled: boolean;
        first_viewed_at: string | null;
        last_viewed_at: string | null;
        view_count: number;
        pdf_url: string;
        convertible: boolean;
    };
    conversions: {
        item: string;
        contract: { id: number; name: string; status: ContractStatus };
    }[];
    document: QuoteDocument;
    company: CompanyBlock;
    activity: ActivityItem[];
};

export default function QuotesShow({ quote, conversions, document, company, activity }: QuotesShowProps) {
    const [cancelling, setCancelling] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const post = (name: string) => router.post(route(name, quote.id), {}, { preserveScroll: true });
    const copyLink = useCopyQuoteLink({
        id: quote.id,
        status: quote.status,
        public_url: quote.public_enabled ? quote.public_url : null,
    });

    return (
        <>
            <Head title={`${quote.number} · ${quote.client.name}`} />

            <Link href={route('quotes.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-ink-900">
                <ArrowLeft className="size-4" aria-hidden /> Cotizaciones
            </Link>

            <header className="mb-6 flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="eyebrow mb-1">{quote.client.name}</p>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="numeric text-xl font-semibold tracking-[-0.01em] text-ink-900">{quote.number}</h1>
                        <QuoteStatusBadge status={quote.display_status} />
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {quote.editable && (
                        <Link href={route('quotes.edit', quote.id)} className={buttonClasses('secondary')}>
                            <Pencil /> Editar
                        </Link>
                    )}
                    {quote.status === 'draft' && (
                        <Button variant={quote.convertible ? 'secondary' : 'primary'} onClick={() => post('quotes.send')}>
                            <Send /> Marcar como enviada
                        </Button>
                    )}
                    {quote.convertible && (
                        <Link href={route('quotes.convert', quote.id)} className={buttonClasses('primary')}>
                            <ArrowRightLeft /> {quote.status === 'partially_converted' ? 'Convertir el resto' : 'Convertir en servicios'}
                        </Link>
                    )}
                </div>
            </header>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div className="overflow-hidden rounded-md border border-line shadow-subtle">
                    <QuotePreview document={document} company={company} />
                </div>

                <aside className="flex flex-col gap-6">
                    <Panel title="Resumen">
                        <dl className="flex flex-col gap-3">
                            <div className="flex items-center justify-between">
                                <dt className="eyebrow">Total</dt>
                                <dd>
                                    <MoneyDisplay value={quote.total} size="lg" />
                                </dd>
                            </div>
                            <div className="flex items-center justify-between text-base">
                                <dt className="text-ink-500">Válida hasta</dt>
                                <dd>
                                    <DateDisplay value={quote.valid_until} format="short" relative={quote.editable} />
                                </dd>
                            </div>
                            {quote.sent_at && (
                                <div className="flex items-center justify-between text-base">
                                    <dt className="text-ink-500">Enviada</dt>
                                    <dd className="numeric text-sm">{formatTimestamp(quote.sent_at)}</dd>
                                </div>
                            )}
                        </dl>

                        <div className="mt-4 flex flex-col gap-2 border-t border-line pt-4">
                            <a href={quote.pdf_url} target="_blank" rel="noopener" className={buttonClasses('secondary', 'md', 'w-full')}>
                                <FileDown /> Ver PDF
                            </a>
                            <Button variant="secondary" onClick={copyLink} disabled={!quote.public_enabled} className="w-full">
                                <Link2 /> Copiar enlace público
                            </Button>
                            {quote.status === 'sent' && (
                                <Button variant="ghost" onClick={() => post('quotes.draft')} className="w-full">
                                    <Undo2 /> Volver a borrador
                                </Button>
                            )}
                            {quote.editable && (
                                <Button
                                    variant="ghost"
                                    onClick={() => setCancelling(true)}
                                    className="w-full text-danger-600 hover:bg-danger-50 hover:text-danger-700"
                                >
                                    <Ban /> Cancelar cotización
                                </Button>
                            )}
                        </div>
                    </Panel>

                    {quote.status === 'cancelled' && (
                        <div className="rounded-md border border-danger-600/20 bg-danger-50 px-4 py-3 text-base text-danger-700">
                            Cancelada {quote.cancelled_at && `el ${formatTimestamp(quote.cancelled_at)}`}
                            {quote.cancel_reason && ` · ${quote.cancel_reason}`}
                        </div>
                    )}

                    {conversions.length > 0 && (
                        <Panel title="Servicios contratados" eyebrow="Conversión">
                            <ul className="flex flex-col gap-2">
                                {conversions.map((conversion) => (
                                    <li key={conversion.contract.id} className="flex items-center justify-between gap-2 text-base">
                                        <Link
                                            href={route('contracts.show', conversion.contract.id)}
                                            className="min-w-0 truncate text-brand-700 hover:underline"
                                        >
                                            {conversion.contract.name}
                                        </Link>
                                        <ContractStatusBadge status={conversion.contract.status} />
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    )}

                    <Panel title="Enlace público" eyebrow="Para el cliente">
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center justify-between gap-2">
                                {quote.public_enabled ? <Badge tone="success">Activo</Badge> : <Badge tone="neutral">Desactivado</Badge>}
                                {quote.public_enabled && (
                                    <a
                                        href={quote.public_url}
                                        target="_blank"
                                        rel="noopener"
                                        className="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline"
                                    >
                                        Abrir <ExternalLink className="size-3.5" aria-hidden />
                                    </a>
                                )}
                            </div>
                            <p className="text-sm text-ink-500">
                                {quote.view_count > 0 ? (
                                    <>
                                        Visto <span className="numeric font-medium text-ink-900">{quote.view_count}</span>{' '}
                                        {quote.view_count === 1 ? 'vez' : 'veces'} · primera vez el {formatTimestamp(quote.first_viewed_at!)}
                                        {quote.last_viewed_at && quote.view_count > 1 && <> · última el {formatTimestamp(quote.last_viewed_at)}</>}
                                    </>
                                ) : (
                                    'El cliente aún no abrió el enlace. Tus propias visitas no se cuentan.'
                                )}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        router.patch(route('quotes.link.toggle', quote.id), { enabled: !quote.public_enabled }, { preserveScroll: true })
                                    }
                                >
                                    {quote.public_enabled ? 'Desactivar' : 'Activar'}
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setRegenerating(true)}>
                                    <RefreshCw /> Regenerar
                                </Button>
                            </div>
                        </div>
                    </Panel>

                    {quote.internal_notes && (
                        <Panel title="Notas internas" eyebrow="Solo para ti">
                            <p className="text-base whitespace-pre-line text-ink-700">{quote.internal_notes}</p>
                        </Panel>
                    )}

                    <Panel title="Actividad" eyebrow="Historial">
                        <ActivityList items={activity} />
                    </Panel>
                </aside>
            </div>

            <ConfirmDialog
                open={regenerating}
                onOpenChange={setRegenerating}
                title="¿Generar un enlace nuevo?"
                description="El enlace actual dejará de funcionar de inmediato. Úsalo si lo compartiste por error o con quien no debías."
                confirmLabel="Generar enlace nuevo"
                tone="danger"
                onConfirm={() =>
                    router.post(
                        route('quotes.link.regenerate', quote.id),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setRegenerating(false),
                        },
                    )
                }
            />

            <ReasonDialog
                open={cancelling}
                onOpenChange={setCancelling}
                title="¿Cancelar esta cotización?"
                description="Quedará como cancelada y ya no se podrá editar. Su número no se reutiliza."
                confirmLabel="Cancelar cotización"
                action={route('quotes.cancel', quote.id)}
            />
        </>
    );
}
