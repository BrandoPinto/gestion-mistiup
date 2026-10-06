import { DateDisplay } from '@/components/data/DateDisplay';
import { LedgerStrip } from '@/components/data/LedgerStrip';
import { MoneyByCurrency } from '@/components/data/MoneyByCurrency';
import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { PageHeader } from '@/components/layout/PageHeader';
import { buttonClasses } from '@/components/ui/Button';
import { Panel } from '@/components/ui/Panel';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { Skeleton } from '@/components/ui/Skeleton';
import type { ChargeListItem } from '@/features/charges/types';
import type { ContractListItem } from '@/features/contracts/types';
import { CompactList } from '@/features/dashboard/CompactList';
import { IncomeChart, IncomeTable, type MonthlyIncome } from '@/features/dashboard/IncomeChart';
import { QuoteStatusBadge } from '@/features/quotes/QuoteStatusBadge';
import type { QuoteListItem } from '@/features/quotes/types';
import { useLocalPreference } from '@/hooks/useLocalPreference';
import { formatDateLong } from '@/lib/dates';
import type { Currency } from '@/types/money';
import { Deferred, Link, usePage } from '@inertiajs/react';
import { BarChart3, FileText, Plus, Table2 } from 'lucide-react';

type CurrencyAmount = { currency: Currency; amount: string };

type DashboardProps = {
    today: string;
    collected: {
        this_month: (CurrencyAmount & { count: number })[];
        previous_period: (CurrencyAmount & { count: number })[];
    };
    receivables: {
        currency: Currency;
        balance: string;
        overdue: string;
        overdue_count: number;
        upcoming: string;
    }[];
    quotes: { count: number; viewed: number; totals: CurrencyAmount[] };
    counts: { active_clients: number; active_contracts: number };
    upcoming: ChargeListItem[];
    overdue: ChargeListItem[];
    ending_contracts: ContractListItem[];
    recent_quotes: QuoteListItem[];
    income?: MonthlyIncome[];
};

/** Comparación con el mismo tramo del mes anterior (del 1 al mismo día), cada moneda por separado. */
function ComparisonHint({ previous }: { previous: DashboardProps['collected']['previous_period'] }) {
    if (previous.length === 0) {
        return <>Sin cobros en el mismo tramo del mes anterior</>;
    }

    return (
        <span className="inline-flex flex-wrap items-baseline gap-1">
            Mismo tramo del mes anterior: <MoneyByCurrency items={previous} size="base" className="inline-flex flex-row gap-2" />
        </span>
    );
}

export default function DashboardIndex(props: DashboardProps) {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const [currency, setCurrency] = useLocalPreference<Currency>('dashboard.currency', 'PEN');
    const [view, setView] = useLocalPreference<'chart' | 'table'>('dashboard.incomeView', 'chart');

    const overdueTotals = props.receivables.filter((row) => row.overdue !== '0.00').map((row) => ({ currency: row.currency, amount: row.overdue }));
    const overdueCount = props.receivables.reduce((sum, row) => sum + row.overdue_count, 0);
    const upcomingTotals = props.receivables.filter((row) => row.upcoming !== '0.00').map((row) => ({ currency: row.currency, amount: row.upcoming }));

    return (
        <>
            <PageHeader
                title={`Hola, ${firstName}`}
                eyebrow={formatDateLong(props.today)}
                description={`${props.counts.active_clients} clientes activos · ${props.counts.active_contracts} servicios activos`}
                actions={
                    <>
                        <Link href={route('quotes.create')} className={buttonClasses('secondary')}>
                            <FileText /> Nueva cotización
                        </Link>
                        <Link href={route('contracts.create')} className={buttonClasses('primary')}>
                            <Plus /> Contratar servicio
                        </Link>
                    </>
                }
            />

            {/* ¿Cuánto he cobrado? ¿Qué debo cobrar? ¿Quién me debe? ¿Qué cotizaciones tengo pendientes? */}
            <LedgerStrip
                entries={[
                    {
                        label: 'Cobrado este mes',
                        value: <MoneyByCurrency items={props.collected.this_month} tone="success" empty="—" />,
                        hint: <ComparisonHint previous={props.collected.previous_period} />,
                    },
                    {
                        label: 'Por cobrar',
                        value: (
                            <MoneyByCurrency
                                items={props.receivables.map((row) => ({
                                    currency: row.currency,
                                    amount: row.balance,
                                }))}
                            />
                        ),
                        hint:
                            upcomingTotals.length > 0 ? (
                                <span className="inline-flex flex-wrap items-baseline gap-1">
                                    Próximos 30 días: <MoneyByCurrency items={upcomingTotals} size="base" className="inline-flex flex-row gap-2" />
                                </span>
                            ) : undefined,
                    },
                    {
                        label: 'Vencido',
                        value: <MoneyByCurrency items={overdueTotals} tone="danger" empty="Al día" />,
                        hint:
                            overdueCount > 0 ? (
                                <Link
                                    href={route('charges.index', {
                                        status: 'overdue',
                                    })}
                                    className="text-danger-700 hover:underline"
                                >
                                    {overdueCount === 1 ? '1 cobro vencido' : `${overdueCount} cobros vencidos`}
                                </Link>
                            ) : undefined,
                    },
                    {
                        label: 'Cotizaciones abiertas',
                        value: (
                            <Link href={route('quotes.index')} className="numeric text-2xl font-semibold text-ink-900 hover:text-brand-700">
                                {props.quotes.count}
                            </Link>
                        ),
                        hint: props.quotes.count > 0 ? `${props.quotes.viewed} vista${props.quotes.viewed === 1 ? '' : 's'} por el cliente` : undefined,
                    },
                ]}
            />

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div className="flex min-w-0 flex-col gap-6">
                    <Panel
                        title="Ingresos por mes"
                        eyebrow="Últimos 12 meses · pagos recibidos"
                        actions={
                            <div className="flex items-center gap-2">
                                <SegmentedControl
                                    label="Moneda"
                                    value={currency}
                                    onChange={setCurrency}
                                    options={[
                                        { value: 'PEN', label: 'S/' },
                                        { value: 'USD', label: '$' },
                                    ]}
                                />
                                <SegmentedControl
                                    label="Vista"
                                    value={view}
                                    onChange={setView}
                                    options={[
                                        { value: 'chart', label: 'Gráfico' },
                                        { value: 'table', label: 'Tabla' },
                                    ]}
                                    className="max-sm:hidden"
                                />
                            </div>
                        }
                    >
                        <Deferred data="income" fallback={<Skeleton className="h-[220px] w-full" />}>
                            {props.income && props.income.every((item) => item.totals[currency] === '0.00') ? (
                                <div className="flex h-[220px] flex-col items-center justify-center gap-2 text-center">
                                    <BarChart3 className="size-5 text-ink-400" aria-hidden />
                                    <p className="text-base text-ink-500">
                                        Aún no hay pagos en {currency === 'PEN' ? 'soles' : 'dólares'} en los últimos 12 meses.
                                    </p>
                                </div>
                            ) : props.income && view === 'table' ? (
                                <IncomeTable data={props.income} currency={currency} />
                            ) : props.income ? (
                                <IncomeChart data={props.income} currency={currency} />
                            ) : null}
                        </Deferred>
                        <p className="mt-2 flex items-center gap-1.5 text-xs text-ink-500 sm:hidden">
                            <Table2 className="size-3.5" aria-hidden /> Toca una barra para ver el monto.
                        </p>
                    </Panel>

                    {/* ¿Qué debo cobrar? */}
                    <Panel
                        title="Próximos cobros"
                        eyebrow="Próximos 30 días"
                        flush
                        actions={
                            <Link href={route('due.index')} className="text-sm font-medium text-brand-700 hover:underline">
                                Ver vencimientos
                            </Link>
                        }
                    >
                        <CompactList
                            empty="No hay cobros por vencer en los próximos 30 días."
                            rows={props.upcoming.map((charge) => ({
                                key: charge.id,
                                href: route('charges.show', charge.id),
                                title: charge.client.name,
                                subtitle: charge.description,
                                value: <MoneyDisplay value={charge.balance} />,
                                meta: <DateDisplay value={charge.due_date} format="short" />,
                            }))}
                        />
                    </Panel>
                </div>

                <div className="flex min-w-0 flex-col gap-6">
                    {/* ¿Quién me debe? */}
                    <Panel
                        title="Vencidos"
                        eyebrow="Quién me debe"
                        flush
                        className={props.overdue.length > 0 ? 'border-danger-600/30' : undefined}
                        actions={
                            props.overdue.length > 0 && (
                                <Link
                                    href={route('charges.index', {
                                        status: 'overdue',
                                    })}
                                    className="text-sm font-medium text-brand-700 hover:underline"
                                >
                                    Ver todos
                                </Link>
                            )
                        }
                    >
                        <CompactList
                            empty="Ningún cobro vencido."
                            rows={props.overdue.map((charge) => ({
                                key: charge.id,
                                href: route('charges.show', charge.id),
                                title: charge.client.name,
                                subtitle: charge.description,
                                value: <MoneyDisplay value={charge.balance} tone="danger" />,
                                meta: `hace ${charge.days_overdue} d`,
                            }))}
                        />
                    </Panel>

                    {/* ¿Qué cotizaciones tengo pendientes? */}
                    <Panel
                        title="Cotizaciones pendientes"
                        eyebrow={props.quotes.totals.length > 0 ? 'Abiertas' : undefined}
                        flush
                        actions={
                            <Link href={route('quotes.index')} className="text-sm font-medium text-brand-700 hover:underline">
                                Ver todas
                            </Link>
                        }
                    >
                        <CompactList
                            empty="No hay cotizaciones abiertas."
                            rows={props.recent_quotes.map((quote) => ({
                                key: quote.id,
                                href: route('quotes.show', quote.id),
                                title: quote.client.name,
                                subtitle: quote.number,
                                value: <MoneyDisplay value={quote.total} />,
                                meta: <QuoteStatusBadge status={quote.display_status} />,
                            }))}
                        />
                    </Panel>

                    {/* ¿Qué vence pronto? (contratos) */}
                    {props.ending_contracts.length > 0 && (
                        <Panel title="Contratos por terminar" eyebrow="Próximos 60 días" flush>
                            <CompactList
                                empty=""
                                rows={props.ending_contracts.map((contract) => ({
                                    key: contract.id,
                                    href: route('contracts.show', contract.id),
                                    title: contract.name,
                                    subtitle: contract.client.name,
                                    meta: contract.end_date ? <DateDisplay value={contract.end_date} format="short" relative /> : undefined,
                                }))}
                            />
                        </Panel>
                    )}
                </div>
            </div>
        </>
    );
}
