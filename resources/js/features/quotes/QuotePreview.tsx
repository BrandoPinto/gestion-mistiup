import { formatDateLong } from '@/lib/dates';
import { CURRENCY_SYMBOL, formatAmount, isValidAmount } from '@/lib/money';
import type { ReactNode } from 'react';
import type { CompanyBlock, QuoteDocument } from './types';

type QuotePreviewProps = {
    document: QuoteDocument;
    company: CompanyBlock;
};

function amount(value: string, symbol: string): string {
    return isValidAmount(value) ? `${symbol} ${formatAmount(value)}` : '—';
}

/** "1.50" → "1.5", "2.00" → "2". */
function quantity(value: string): string {
    return value.includes('.') ? value.replace(/\.?0+$/, '') : value;
}

function discountLabel(type: string | null, value: string | null, symbol: string): string | null {
    if (!type || !value) return null;
    return type === 'percent' ? `-${quantity(value)}%` : `-${symbol} ${formatAmount(value)}`;
}

/**
 * Documento de cotización con apariencia de documento comercial (no una tabla HTML impresa).
 * Lo usan el editor (vista previa en vivo), la ficha de la cotización y el enlace público (Fase 8).
 * El PDF (Fase 8) replica este diseño en Blade con el mismo contrato de datos.
 */
export function QuotePreview({ document: doc, company }: QuotePreviewProps) {
    const symbol = CURRENCY_SYMBOL[doc.currency];
    const hasDiscounts = isValidAmount(doc.discount_total) && doc.discount_total !== '0.00';
    const hasLineDiscounts = doc.items.some((item) => item.line_discount !== '0.00' && item.line_discount !== '');
    const taxRate = quantity(doc.tax_rate || '0');
    const accounts = company.bank_accounts.filter((account) => !account.currency || account.currency === doc.currency);

    return (
        <article className="bg-white text-[13px] leading-relaxed text-ink-900 [font-feature-settings:'tnum']" aria-label={`Cotización ${doc.number}`}>
            {/* Encabezado: marca a la izquierda, identificación del documento a la derecha. */}
            <header className="flex flex-wrap items-start justify-between gap-6 px-5 pt-8 pb-6 sm:px-10">
                <div className="min-w-0">
                    {company.logo_url ? (
                        <img src={company.logo_url} alt={company.name} className="mb-3 max-h-14 max-w-[200px] object-contain" />
                    ) : (
                        <p className="mb-2 text-lg font-semibold tracking-[-0.01em] text-navy-900">{company.name}</p>
                    )}
                    <div className="text-xs text-ink-500">
                        {company.legal_name && <p className="font-medium text-ink-700">{company.legal_name}</p>}
                        {company.ruc && <p>RUC {company.ruc}</p>}
                        {company.address && <p>{company.address}</p>}
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-[10px] font-semibold tracking-[0.16em] text-brand-600 uppercase">Cotización</p>
                    <p className="text-xl font-semibold tracking-[-0.01em] whitespace-nowrap text-navy-900">{doc.number}</p>
                </div>
            </header>

            <div className="mx-8 h-[3px] bg-navy-900 sm:mx-10" />

            {/* Partes y fechas. */}
            <section className="grid gap-6 px-5 py-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:px-10">
                <div className="min-w-0">
                    <Label>Para</Label>
                    {doc.client ? (
                        <div className="mt-1">
                            <p className="text-[15px] font-semibold text-ink-900">{doc.client.name}</p>
                            <div className="text-xs text-ink-500">
                                {doc.client.document && <p>{doc.client.document}</p>}
                                {doc.client.address && <p>{doc.client.address}</p>}
                                {doc.client.contact_name && <p>Atención: {doc.client.contact_name}</p>}
                                {(doc.client.email || doc.client.phone) && <p>{[doc.client.email, doc.client.phone].filter(Boolean).join(' · ')}</p>}
                            </div>
                        </div>
                    ) : (
                        <p className="mt-1 text-ink-400 italic">Selecciona un cliente</p>
                    )}
                </div>
                <dl className="grid grid-cols-[auto_auto] gap-x-6 gap-y-1 self-start text-xs">
                    <dt className="text-ink-500">Fecha</dt>
                    <dd className="text-right font-medium">{doc.issue_date ? formatDateLong(doc.issue_date) : '—'}</dd>
                    <dt className="text-ink-500">Válida hasta</dt>
                    <dd className="text-right font-medium">{doc.valid_until ? formatDateLong(doc.valid_until) : '—'}</dd>
                    {doc.delivery_date && (
                        <>
                            <dt className="text-ink-500">Entrega estimada</dt>
                            <dd className="text-right font-medium">{formatDateLong(doc.delivery_date)}</dd>
                        </>
                    )}
                    <dt className="text-ink-500">Moneda</dt>
                    <dd className="text-right font-medium">{doc.currency === 'PEN' ? 'Soles (S/)' : 'Dólares ($)'}</dd>
                </dl>
            </section>

            {doc.intro && <p className="px-5 pb-5 whitespace-pre-line text-ink-700 sm:px-10">{doc.intro}</p>}

            {/* Conceptos. */}
            <section className="px-5 sm:px-10">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-y border-navy-900/80 text-[10px] tracking-[0.1em] text-ink-500 uppercase">
                            <th className="w-7 py-2 text-left font-semibold">#</th>
                            <th className="py-2 text-left font-semibold">Descripción</th>
                            <th className="w-10 py-2 text-right sm:w-14 font-semibold">Cant.</th>
                            <th className="w-24 py-2 text-right font-semibold max-sm:hidden">P. unit.</th>
                            {hasLineDiscounts && <th className="w-16 py-2 text-right font-semibold max-sm:hidden">Desc.</th>}
                            <th className="w-24 py-2 text-right font-semibold sm:w-28">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        {doc.items.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-6 text-center text-ink-400 italic">
                                    Agrega conceptos
                                </td>
                            </tr>
                        )}
                        {doc.items.map((item, index) => (
                            <tr key={index} className="border-b border-line align-top">
                                <td className="py-3 text-ink-400">{index + 1}</td>
                                <td className="py-3 pr-3">
                                    <p className="font-medium text-ink-900">{item.name || <span className="text-ink-400 italic">Sin nombre</span>}</p>
                                    {item.description && <p className="text-xs whitespace-pre-line text-ink-500">{item.description}</p>}
                                </td>
                                <td className="py-3 text-right">{quantity(item.quantity || '0')}</td>
                                <td className="py-3 text-right max-sm:hidden">{amount(item.unit_price, symbol)}</td>
                                {hasLineDiscounts && (
                                    <td className="py-3 text-right text-ink-500 max-sm:hidden">
                                        {discountLabel(item.discount_type, item.discount_value, symbol)}
                                    </td>
                                )}
                                <td className="py-3 text-right font-medium">{amount(item.line_total, symbol)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            {/* Totales: precios con IGV incluido; el desglose es informativo. */}
            <section className="flex justify-end px-5 pt-4 pb-6 sm:px-10">
                <dl className="w-full max-w-[280px] text-xs">
                    <Row label="Subtotal" value={amount(doc.subtotal, symbol)} />
                    {doc.global_discount !== '0.00' && isValidAmount(doc.global_discount) && (
                        <Row
                            label={`Descuento${doc.global_discount_type === 'percent' && doc.global_discount_value ? ` (${quantity(doc.global_discount_value)}%)` : ''}`}
                            value={`-${amount(doc.global_discount, symbol)}`}
                        />
                    )}
                    <Row label="Op. gravada" value={amount(doc.tax_base, symbol)} muted />
                    <Row label={`IGV ${taxRate}%`} value={amount(doc.tax_amount, symbol)} muted />
                    <div className="mt-2 flex items-baseline justify-between bg-cream-100 px-3 py-2.5">
                        <dt className="text-[11px] font-semibold tracking-[0.1em] text-navy-900 uppercase">Total</dt>
                        <dd className="text-lg font-semibold text-navy-900">{amount(doc.total, symbol)}</dd>
                    </div>
                    {hasDiscounts && <p className="mt-1.5 text-right text-[11px] text-ink-500">Incluye descuentos por {amount(doc.discount_total, symbol)}</p>}
                    <p className="mt-0.5 text-right text-[11px] text-ink-500">Precios con IGV incluido</p>
                </dl>
            </section>

            {(doc.observations || doc.terms) && (
                <section className="grid gap-5 border-t border-line px-5 py-6 sm:grid-cols-2 sm:px-10">
                    {doc.observations && (
                        <div>
                            <Label>Observaciones</Label>
                            <p className="mt-1 text-xs whitespace-pre-line text-ink-700">{doc.observations}</p>
                        </div>
                    )}
                    {doc.terms && (
                        <div>
                            <Label>Condiciones</Label>
                            <p className="mt-1 text-xs whitespace-pre-line text-ink-700">{doc.terms}</p>
                        </div>
                    )}
                </section>
            )}

            {(accounts.length > 0 || company.yape_phone || company.plin_phone) && (
                <section className="border-t border-line bg-cream-50 px-5 py-5 sm:px-10">
                    <Label>Datos de pago</Label>
                    <div className="mt-2 grid gap-x-8 gap-y-2 text-xs sm:grid-cols-2">
                        {accounts.map((account, index) => (
                            <div key={index}>
                                <p className="font-medium text-ink-900">
                                    {account.bank}
                                    {account.currency && ` · ${account.currency === 'PEN' ? 'Soles' : 'Dólares'}`}
                                </p>
                                <p className="text-ink-700">Cuenta {account.number}</p>
                                {account.cci && <p className="text-ink-500">CCI {account.cci}</p>}
                                {account.holder && <p className="text-ink-500">{account.holder}</p>}
                            </div>
                        ))}
                        {(company.yape_phone || company.plin_phone) && (
                            <div>
                                {company.yape_phone && <p className="text-ink-700">Yape: {company.yape_phone}</p>}
                                {company.plin_phone && <p className="text-ink-700">Plin: {company.plin_phone}</p>}
                                {company.wallet_holder && <p className="text-ink-500">{company.wallet_holder}</p>}
                            </div>
                        )}
                    </div>
                </section>
            )}

            <footer className="flex flex-wrap justify-between gap-2 border-t-[3px] border-navy-900 px-5 py-3 text-[11px] text-ink-500 sm:px-10">
                <span className="font-medium text-navy-900">{company.name}</span>
                <span>{[company.website, company.email, company.phone].filter(Boolean).join(' · ')}</span>
            </footer>
        </article>
    );
}

function Label({ children }: { children: ReactNode }) {
    return <p className="text-[10px] font-semibold tracking-[0.14em] text-ink-500 uppercase">{children}</p>;
}

function Row({ label, value, muted = false }: { label: string; value: string; muted?: boolean }) {
    return (
        <div className={muted ? 'flex justify-between py-1 text-ink-500' : 'flex justify-between py-1 text-ink-900'}>
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}
