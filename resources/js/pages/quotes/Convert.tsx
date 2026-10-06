import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { MoneyInput } from '@/components/data/MoneyInput';
import { PageHeader } from '@/components/layout/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button, buttonClasses } from '@/components/ui/Button';
import { Checkbox, Field, Input, Select } from '@/components/ui/Field';
import { RecurrenceFields } from '@/features/billing/RecurrenceFields';
import { cn } from '@/lib/cn';
import { recurrenceLabel, type BillingType, type IntervalUnit } from '@/lib/recurrence';
import type { Currency } from '@/types/money';
import { Link, useForm } from '@inertiajs/react';
import { ArrowRightLeft, Info } from 'lucide-react';
import type { FormEvent } from 'react';

type ConvertItem = {
    id: number;
    name: string;
    description: string | null;
    quantity: string;
    line_total: string;
    allocated_discount: string;
    net_price: string;
    suggested_billing_type: BillingType;
    suggested_interval_unit: IntervalUnit | null;
    suggested_interval_count: number | null;
    contract: { id: number; name: string } | null;
};

type ConvertProps = {
    quote: {
        id: number;
        number: string;
        client: { id: number; name: string };
        currency: Currency;
        has_global_discount: boolean;
    };
    items: ConvertItem[];
    today: string;
};

type Row = {
    quote_item_id: number;
    selected: boolean;
    name: string;
    price: string;
    billing_type: BillingType;
    interval_unit: IntervalUnit | '';
    interval_count: string;
    start_date: string;
    has_term: boolean;
    term_unit: IntervalUnit;
    term_count: string;
    first_charge_date: string;
};

export default function QuotesConvert({ quote, items, today }: ConvertProps) {
    const form = useForm<{ rows: Row[] }>({
        rows: items
            .filter((item) => item.contract === null)
            .map((item) => ({
                quote_item_id: item.id,
                selected: true,
                name: item.name,
                price: item.net_price,
                // No se asume que todo es recurrente: se propone la modalidad del catálogo o pago único.
                billing_type: item.suggested_billing_type,
                interval_unit: item.suggested_interval_unit ?? '',
                interval_count: item.suggested_interval_count ? String(item.suggested_interval_count) : '',
                start_date: today,
                has_term: false,
                term_unit: 'year',
                term_count: '',
                first_charge_date: today,
            })),
    });

    const rows = form.data.rows;
    const selected = rows.filter((row) => row.selected);
    const errors = form.errors as Record<string, string>;

    // El servidor recibe solo las filas marcadas: sus errores vienen indexados por esa posición.
    function errorFor(row: Row, field: string): string | undefined {
        const index = selected.indexOf(row);
        return index === -1 ? undefined : errors[`items.${index}.${field}`];
    }

    function update(id: number, changes: Partial<Row>) {
        form.setData(
            'rows',
            rows.map((row) => (row.quote_item_id === id ? { ...row, ...changes } : row)),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            items: data.rows.filter((row) => row.selected).map(({ selected: _selected, ...row }) => row),
        }));
        form.post(route('quotes.convert.store', quote.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <PageHeader
                title="Convertir en servicios contratados"
                eyebrow={`${quote.number} · ${quote.client.name}`}
                description="Elige qué conceptos se contratan y cómo se cobra cada uno. Se crearán sus contratos y los cobros próximos."
            />

            {quote.has_global_discount && (
                <div className="mb-5 flex items-start gap-2 rounded-md border border-brand-600/20 bg-brand-50 px-4 py-3 text-base text-brand-800">
                    <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
                    El descuento global se repartió en proporción a cada concepto: el precio propuesto ya lo incluye. Puedes ajustarlo antes de convertir.
                </div>
            )}

            <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                {items.map((item) => {
                    const row = rows.find((candidate) => candidate.quote_item_id === item.id);

                    if (!row) {
                        return (
                            <div key={item.id} className="flex items-center justify-between gap-3 rounded-lg border border-line bg-cream-50 px-4 py-3">
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-ink-700">{item.name}</p>
                                    <p className="text-sm text-ink-500">Ya convertido</p>
                                </div>
                                {item.contract && (
                                    <Link href={route('contracts.show', item.contract.id)} className="text-sm font-medium text-brand-700 hover:underline">
                                        Ver servicio
                                    </Link>
                                )}
                            </div>
                        );
                    }

                    const recurring = row.billing_type === 'recurring';

                    return (
                        <section
                            key={item.id}
                            className={cn('rounded-lg border bg-surface transition-colors', row.selected ? 'border-brand-600/40' : 'border-line opacity-70')}
                        >
                            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3">
                                <Checkbox
                                    label={item.name}
                                    checked={row.selected}
                                    onChange={(event) =>
                                        update(item.id, {
                                            selected: event.target.checked,
                                        })
                                    }
                                    className="font-medium text-ink-900"
                                />
                                <div className="text-right text-sm text-ink-500">
                                    <p>
                                        En la cotización:{' '}
                                        <MoneyDisplay
                                            value={{
                                                amount: item.line_total,
                                                currency: quote.currency,
                                            }}
                                            size="sm"
                                        />
                                        {item.quantity !== '1.00' && <span className="numeric"> ({item.quantity.replace(/\.?0+$/, '')} u.)</span>}
                                    </p>
                                    {item.allocated_discount !== '0.00' && (
                                        <p>
                                            Desc. global: −
                                            <MoneyDisplay
                                                value={{
                                                    amount: item.allocated_discount,
                                                    currency: quote.currency,
                                                }}
                                                size="sm"
                                            />
                                        </p>
                                    )}
                                </div>
                            </header>

                            {row.selected && (
                                <div className="grid gap-4 p-4 lg:grid-cols-2">
                                    <Field label="Nombre del servicio" error={errorFor(row, 'name')} required>
                                        {({ id, invalid }) => (
                                            <Input
                                                id={id}
                                                value={row.name}
                                                onChange={(event) =>
                                                    update(item.id, {
                                                        name: event.target.value,
                                                    })
                                                }
                                                invalid={invalid}
                                            />
                                        )}
                                    </Field>
                                    <Field label={recurring ? 'Precio por ciclo' : 'Precio'} error={errorFor(row, 'price')} required hint="Con IGV incluido.">
                                        {({ id, invalid }) => (
                                            <MoneyInput
                                                id={id}
                                                amount={row.price}
                                                currency={quote.currency}
                                                onAmountChange={(price) => update(item.id, { price })}
                                                invalid={invalid}
                                            />
                                        )}
                                    </Field>

                                    <div className="flex flex-col gap-1.5 lg:col-span-2">
                                        <span className="text-sm font-medium text-ink-700">Modalidad</span>
                                        <RecurrenceFields
                                            value={{
                                                billing_type: row.billing_type,
                                                interval_unit: row.interval_unit,
                                                interval_count: row.interval_count,
                                            }}
                                            onChange={(recurrence) =>
                                                update(item.id, {
                                                    ...recurrence,
                                                    has_term: recurrence.billing_type === 'recurring' ? row.has_term : false,
                                                })
                                            }
                                            error={errorFor(row, 'interval_count') ?? errorFor(row, 'interval_unit')}
                                        />
                                    </div>

                                    <Field
                                        label="Inicio"
                                        error={errorFor(row, 'start_date')}
                                        required
                                        hint={recurring ? 'Ancla del calendario: cada ciclo vence en esta fecha.' : undefined}
                                    >
                                        {({ id, invalid, describedBy }) => (
                                            <Input
                                                id={id}
                                                type="date"
                                                value={row.start_date}
                                                onChange={(event) =>
                                                    update(item.id, {
                                                        start_date: event.target.value,
                                                        // El primer cobro acompaña al inicio mientras no se haya cambiado a mano.
                                                        first_charge_date:
                                                            row.first_charge_date === row.start_date ? event.target.value : row.first_charge_date,
                                                    })
                                                }
                                                invalid={invalid}
                                                aria-describedby={describedBy}
                                            />
                                        )}
                                    </Field>
                                    <Field label="Primer cobro vence" error={errorFor(row, 'first_charge_date')} required>
                                        {({ id, invalid }) => (
                                            <Input
                                                id={id}
                                                type="date"
                                                value={row.first_charge_date}
                                                onChange={(event) =>
                                                    update(item.id, {
                                                        first_charge_date: event.target.value,
                                                    })
                                                }
                                                invalid={invalid}
                                            />
                                        )}
                                    </Field>

                                    {recurring && (
                                        <div className="flex flex-col gap-2 lg:col-span-2">
                                            <Checkbox
                                                label="Tiene duración determinada"
                                                checked={row.has_term}
                                                onChange={(event) =>
                                                    update(item.id, {
                                                        has_term: event.target.checked,
                                                    })
                                                }
                                            />
                                            {row.has_term && (
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        aria-label="Duración"
                                                        inputMode="numeric"
                                                        value={row.term_count}
                                                        onChange={(event) =>
                                                            update(item.id, {
                                                                term_count: event.target.value.replace(/\D/g, '').slice(0, 3),
                                                            })
                                                        }
                                                        invalid={Boolean(errorFor(row, 'term_count'))}
                                                        className="numeric w-20 text-center"
                                                    />
                                                    <Select
                                                        aria-label="Unidad"
                                                        value={row.term_unit}
                                                        onChange={(event) =>
                                                            update(item.id, {
                                                                term_unit: event.target.value as IntervalUnit,
                                                            })
                                                        }
                                                        className="w-28"
                                                    >
                                                        <option value="month">meses</option>
                                                        <option value="year">años</option>
                                                    </Select>
                                                </div>
                                            )}
                                            {errorFor(row, 'term_count') && <p className="text-xs text-danger-600">{errorFor(row, 'term_count')}</p>}
                                        </div>
                                    )}

                                    <p className="text-sm text-ink-500 lg:col-span-2">
                                        {recurring && row.interval_unit && Number(row.interval_count) > 0
                                            ? `Se cobrará ${recurrenceLabel({ unit: row.interval_unit, count: Number(row.interval_count) }).toLowerCase()}${row.has_term && row.term_count ? ` durante ${row.term_count} ${row.term_unit === 'year' ? 'año(s)' : 'mes(es)'}` : ', sin fecha de fin'}.`
                                            : 'Se generará un único cobro.'}
                                    </p>
                                </div>
                            )}
                        </section>
                    );
                })}

                {(errors.items || errors.quote) && <p className="text-base text-danger-600">{errors.items ?? errors.quote}</p>}

                <div className="sticky bottom-14 z-10 -mx-4 flex items-center justify-end gap-2 border-t border-line bg-canvas/95 px-4 py-3 backdrop-blur-sm sm:bottom-0 sm:mx-0 sm:rounded-md sm:border">
                    <Link href={route('quotes.show', quote.id)} className={cn(buttonClasses('ghost'), 'mr-auto')}>
                        Cancelar
                    </Link>
                    {selected.length > 0 && (
                        <Badge tone="brand" dot={false}>
                            {selected.length === 1 ? '1 concepto' : `${selected.length} conceptos`}
                        </Badge>
                    )}
                    <Button type="submit" loading={form.processing} disabled={selected.length === 0}>
                        <ArrowRightLeft /> Convertir
                    </Button>
                </div>
            </form>
        </>
    );
}
