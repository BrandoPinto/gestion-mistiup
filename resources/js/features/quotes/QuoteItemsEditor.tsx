import { MoneyDisplay } from '@/components/data/MoneyDisplay';
import { MoneyInput } from '@/components/data/MoneyInput';
import { Button, IconButton } from '@/components/ui/Button';
import { Input, Select, Textarea } from '@/components/ui/Field';
import type { CatalogService } from '@/features/catalog/types';
import { cn } from '@/lib/cn';
import { CURRENCY_SYMBOL } from '@/lib/money';
import type { QuoteMathResult } from '@/lib/quote-math';
import type { Currency } from '@/types/money';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import type { QuoteItemForm } from './types';

type QuoteItemsEditorProps = {
    items: QuoteItemForm[];
    currency: Currency;
    catalog: CatalogService[];
    lines: QuoteMathResult['lines'] | null;
    errors: Record<string, string>;
    onChange: (items: QuoteItemForm[]) => void;
};

let sequence = 0;
function newKey(): string {
    sequence += 1;
    return `new-${Date.now()}-${sequence}`;
}

export function emptyItem(): QuoteItemForm {
    return {
        key: newKey(),
        service_id: null,
        name: '',
        description: '',
        quantity: '1',
        unit_price: '',
        discount_type: '',
        discount_value: '',
    };
}

/** Lista de conceptos: del catálogo o personalizados, con cantidad, precio y descuento por línea. */
export function QuoteItemsEditor({ items, currency, catalog, lines, errors, onChange }: QuoteItemsEditorProps) {
    function update(index: number, changes: Partial<QuoteItemForm>) {
        onChange(items.map((item, position) => (position === index ? { ...item, ...changes } : item)));
    }

    function move(index: number, offset: -1 | 1) {
        const target = index + offset;
        if (target < 0 || target >= items.length) return;
        const next = [...items];
        [next[index], next[target]] = [next[target]!, next[index]!];
        onChange(next);
    }

    function addFromCatalog(serviceId: string) {
        const service = catalog.find((item) => String(item.id) === serviceId);
        if (!service) return;

        const sameCurrency = service.default_currency === currency;
        if (service.default_price && !sameCurrency) {
            toast.info(`El precio sugerido de «${service.name}» está en ${service.default_currency}: ingresa el precio en ${currency}.`);
        }

        onChange([
            ...items.filter((item) => item.name !== '' || item.unit_price !== ''), // Reemplaza la fila vacía inicial.
            {
                ...emptyItem(),
                service_id: service.id,
                name: service.name,
                description: service.description ?? '',
                unit_price: sameCurrency ? (service.default_price?.amount ?? '') : '',
            },
        ]);
    }

    return (
        <div className="flex flex-col gap-3">
            {items.map((item, index) => {
                const lineError = errors[`items.${index}.discount_value`];

                return (
                    <div key={item.key} className="rounded-md border border-line bg-surface p-3">
                        <div className="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                            <span className="numeric mt-2 w-5 shrink-0 text-sm text-ink-400">{index + 1}</span>
                            <div className="flex min-w-0 flex-1 flex-col gap-2">
                                <Input
                                    aria-label={`Concepto ${index + 1}`}
                                    placeholder="Concepto"
                                    value={item.name}
                                    onChange={(event) =>
                                        update(index, {
                                            name: event.target.value,
                                        })
                                    }
                                    invalid={Boolean(errors[`items.${index}.name`])}
                                    className="font-medium"
                                />
                                <Textarea
                                    aria-label={`Descripción del concepto ${index + 1}`}
                                    placeholder="Descripción (opcional)"
                                    rows={1}
                                    value={item.description}
                                    onChange={(event) =>
                                        update(index, {
                                            description: event.target.value,
                                        })
                                    }
                                    className="min-h-9 text-sm"
                                />
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-[80px_minmax(0,1fr)_minmax(0,1.2fr)]">
                                    <Input
                                        aria-label="Cantidad"
                                        inputMode="decimal"
                                        value={item.quantity}
                                        onChange={(event) =>
                                            update(index, {
                                                quantity: event.target.value.replace(',', '.').replace(/[^\d.]/g, ''),
                                            })
                                        }
                                        invalid={Boolean(errors[`items.${index}.quantity`])}
                                        className="numeric text-right"
                                    />
                                    <MoneyInput
                                        amount={item.unit_price}
                                        currency={currency}
                                        onAmountChange={(unit_price) => update(index, { unit_price })}
                                        invalid={Boolean(errors[`items.${index}.unit_price`])}
                                        placeholder="Precio unit."
                                    />
                                    <div className="col-span-2 flex gap-1 sm:col-span-1">
                                        <Select
                                            aria-label="Tipo de descuento"
                                            value={item.discount_type}
                                            onChange={(event) =>
                                                update(index, {
                                                    discount_type: event.target.value as QuoteItemForm['discount_type'],
                                                    discount_value: '',
                                                })
                                            }
                                            className="w-[92px] shrink-0"
                                        >
                                            <option value="">Sin desc.</option>
                                            <option value="percent">%</option>
                                            <option value="amount">{CURRENCY_SYMBOL[currency]}</option>
                                        </Select>
                                        {item.discount_type && (
                                            <Input
                                                aria-label="Descuento"
                                                inputMode="decimal"
                                                value={item.discount_value}
                                                onChange={(event) =>
                                                    update(index, {
                                                        discount_value: event.target.value.replace(',', '.').replace(/[^\d.]/g, ''),
                                                    })
                                                }
                                                invalid={Boolean(lineError)}
                                                className="numeric min-w-0 text-right"
                                            />
                                        )}
                                    </div>
                                </div>
                                {(lineError || errors[`items.${index}.quantity`] || errors[`items.${index}.unit_price`] || errors[`items.${index}.name`]) && (
                                    <p className="text-xs text-danger-600">
                                        {lineError ?? errors[`items.${index}.name`] ?? errors[`items.${index}.quantity`] ?? errors[`items.${index}.unit_price`]}
                                    </p>
                                )}
                            </div>
                            <div className="flex shrink-0 items-end gap-1 max-sm:w-full max-sm:items-center max-sm:justify-between max-sm:border-t max-sm:border-line max-sm:pt-2 max-sm:pl-7 sm:flex-col">
                                <span className={cn('numeric min-w-20 text-right sm:mt-2 text-base font-medium', !lines && 'text-ink-300')}>
                                    {lines?.[index] ? (
                                        <MoneyDisplay
                                            value={{
                                                amount: lines[index].total,
                                                currency,
                                            }}
                                        />
                                    ) : (
                                        '—'
                                    )}
                                </span>
                                <div className="flex sm:mt-auto">
                                    <IconButton label="Subir" onClick={() => move(index, -1)} disabled={index === 0} className="size-8">
                                        <ArrowUp />
                                    </IconButton>
                                    <IconButton label="Bajar" onClick={() => move(index, 1)} disabled={index === items.length - 1} className="size-8">
                                        <ArrowDown />
                                    </IconButton>
                                    <IconButton
                                        label="Quitar concepto"
                                        onClick={() => onChange(items.filter((_, position) => position !== index))}
                                        className="size-8 hover:text-danger-600"
                                    >
                                        <Trash2 />
                                    </IconButton>
                                </div>
                            </div>
                        </div>
                    </div>
                );
            })}

            <div className="flex flex-wrap gap-2">
                <Select
                    aria-label="Agregar desde el catálogo"
                    value=""
                    onChange={(event) => addFromCatalog(event.target.value)}
                    className="w-auto min-w-56 flex-1 sm:flex-none"
                >
                    <option value="">+ Agregar del catálogo…</option>
                    {catalog.map((service) => (
                        <option key={service.id} value={service.id}>
                            {service.name}
                        </option>
                    ))}
                </Select>
                <Button variant="secondary" onClick={() => onChange([...items, emptyItem()])}>
                    <Plus /> Concepto personalizado
                </Button>
            </div>
        </div>
    );
}
