import { formatAmount, formatMoney } from '@/lib/money';
import type { Currency } from '@/types/money';
import { useLayoutEffect, useRef, useState } from 'react';

export type MonthlyIncome = {
    /** "2026-10" */
    month: string;
    totals: Record<Currency, string>;
};

type IncomeChartProps = {
    data: MonthlyIncome[];
    currency: Currency;
};

const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const HEIGHT = 220;
const PAD = { top: 12, right: 8, bottom: 28, left: 56 };
const BAR_MAX = 24;
const RADIUS = 4;

function monthLabel(month: string): string {
    return MONTHS[Number(month.slice(5, 7)) - 1] ?? month;
}

/** Tope "limpio" del eje: 1, 2, 2.5 o 5 × 10^n por encima del máximo. */
function niceMax(value: number): number {
    if (value <= 0) return 100;
    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 2, 2.5, 5, 10].find((candidate) => candidate * magnitude >= value) ?? 10;
    return step * magnitude;
}

/** Barra con extremo de datos redondeado (4px) y base recta sobre el eje. */
function barPath(x: number, y: number, width: number, height: number): string {
    const r = Math.min(RADIUS, width / 2, height);
    return `M${x},${y + height} V${y + r} Q${x},${y} ${x + r},${y} H${x + width - r} Q${x + width},${y} ${x + width},${y + r} V${y + height} Z`;
}

/**
 * Ingresos por mes de UNA moneda (la elige el usuario: PEN y USD nunca se suman).
 * Una sola serie: sin leyenda; el título del panel la nombra. Tooltip por barra y vista de tabla aparte.
 * Los importes se convierten a número solo para la geometría; los textos se formatean desde el string exacto.
 */
export function IncomeChart({ data, currency }: IncomeChartProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(640);
    const [active, setActive] = useState<number | null>(null);

    useLayoutEffect(() => {
        const element = containerRef.current;
        if (!element) return;
        const observer = new ResizeObserver(([entry]) => entry && setWidth(entry.contentRect.width));
        observer.observe(element);
        return () => observer.disconnect();
    }, []);

    const values = data.map((item) => Number(item.totals[currency] ?? '0'));
    const max = niceMax(Math.max(...values, 0));
    const plotWidth = Math.max(width - PAD.left - PAD.right, 1);
    const plotHeight = HEIGHT - PAD.top - PAD.bottom;
    const band = plotWidth / Math.max(data.length, 1);
    const barWidth = Math.min(BAR_MAX, band * 0.62);
    const ticks = [0, 0.25, 0.5, 0.75, 1].map((fraction) => max * fraction);
    const y = (value: number) => PAD.top + plotHeight - (value / max) * plotHeight;
    const activeItem = active !== null ? data[active] : null;

    return (
        <div ref={containerRef} className="relative" onMouseLeave={() => setActive(null)}>
            <svg width={width} height={HEIGHT} role="img" aria-label={`Ingresos por mes en ${currency === 'PEN' ? 'soles' : 'dólares'}`}>
                {ticks.map((tick) => (
                    <g key={tick}>
                        <line x1={PAD.left} x2={width - PAD.right} y1={y(tick)} y2={y(tick)} stroke="var(--color-line)" strokeWidth={1} />
                        <text x={PAD.left - 8} y={y(tick)} textAnchor="end" dominantBaseline="middle" className="numeric fill-ink-400 text-[11px]">
                            {Math.round(tick).toLocaleString('en-US')}
                        </text>
                    </g>
                ))}

                {data.map((item, index) => {
                    const value = values[index] ?? 0;
                    const x = PAD.left + band * index + (band - barWidth) / 2;
                    const barHeight = (value / max) * plotHeight;
                    const isCurrent = index === data.length - 1;
                    const dimmed = active !== null && active !== index;

                    return (
                        <g key={item.month}>
                            {value > 0 && (
                                <path
                                    d={barPath(x, y(value), barWidth, barHeight)}
                                    fill="var(--color-brand-600)"
                                    opacity={dimmed ? 0.35 : isCurrent ? 1 : 0.85}
                                    className="transition-opacity duration-150"
                                />
                            )}
                            <text
                                x={PAD.left + band * index + band / 2}
                                y={HEIGHT - 8}
                                textAnchor="middle"
                                className={isCurrent ? 'fill-ink-900 text-[11px] font-medium' : 'fill-ink-400 text-[11px]'}
                            >
                                {monthLabel(item.month)}
                            </text>
                            {/* Zona de hover: toda la columna del mes, más grande que la barra. */}
                            <rect
                                x={PAD.left + band * index}
                                y={PAD.top}
                                width={band}
                                height={plotHeight}
                                fill="transparent"
                                onMouseEnter={() => setActive(index)}
                                onFocus={() => setActive(index)}
                                tabIndex={0}
                                aria-label={`${monthLabel(item.month)} ${item.month.slice(0, 4)}: ${formatMoney({ amount: item.totals[currency] ?? '0.00', currency })}`}
                                className="cursor-default outline-none"
                            />
                        </g>
                    );
                })}
            </svg>

            {activeItem && active !== null && (
                <div
                    role="status"
                    className="pointer-events-none absolute z-10 rounded-md border border-line bg-surface px-3 py-2 text-sm shadow-overlay"
                    style={{
                        left: Math.min(Math.max(PAD.left + band * active + band / 2 - 70, 0), width - 140),
                        top: Math.max(y(values[active] ?? 0) - 64, 0),
                        width: 140,
                    }}
                >
                    <p className="text-xs text-ink-500 capitalize">
                        {monthLabel(activeItem.month)} {activeItem.month.slice(0, 4)}
                    </p>
                    <p className="numeric font-semibold text-ink-900">{formatMoney({ amount: activeItem.totals[currency] ?? '0.00', currency })}</p>
                </div>
            )}
        </div>
    );
}

/** Vista de tabla del mismo dato (accesible y exacta). */
export function IncomeTable({ data, currency }: IncomeChartProps) {
    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="text-left">
                    <th className="eyebrow py-1.5">Mes</th>
                    <th className="eyebrow py-1.5 text-right">Cobrado</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-line">
                {[...data].reverse().map((item) => (
                    <tr key={item.month}>
                        <td className="py-1.5 capitalize">
                            {monthLabel(item.month)} {item.month.slice(0, 4)}
                        </td>
                        <td className="numeric py-1.5 text-right">{currency === 'PEN' ? 'S/' : '$'} {formatAmount(item.totals[currency] ?? '0.00')}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
