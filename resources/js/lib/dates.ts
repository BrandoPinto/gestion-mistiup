/**
 * Fechas de negocio ("YYYY-MM-DD") se tratan como texto de calendario, nunca con `new Date(isoDate)`,
 * porque JS las interpreta como medianoche UTC y en Lima (UTC−5) mostraría el día anterior.
 * Los timestamps (ISO con zona) sí se formatean con Intl en la zona del negocio.
 */

const BUSINESS_TIMEZONE = 'America/Lima';

const MONTHS_SHORT = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'] as const;
const MONTHS_LONG = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
] as const;

export type CalendarDate = { year: number; month: number; day: number };

const DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

export function parseDate(value: string): CalendarDate {
    const match = DATE_PATTERN.exec(value);

    if (!match) {
        throw new Error(`Fecha inválida: "${value}"`);
    }

    return { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]) };
}

/** "2026-11-10" → "10/11/2026" */
export function formatDate(value: string): string {
    const { year, month, day } = parseDate(value);

    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;
}

/** "2026-11-10" → "10 nov 2026" */
export function formatDateShort(value: string): string {
    const { year, month, day } = parseDate(value);

    return `${day} ${MONTHS_SHORT[month - 1]} ${year}`;
}

/** "2026-11-10" → "10 de noviembre de 2026" */
export function formatDateLong(value: string): string {
    const { year, month, day } = parseDate(value);

    return `${day} de ${MONTHS_LONG[month - 1]} de ${year}`;
}

const timestampFormatter = new Intl.DateTimeFormat('es-PE', {
    timeZone: BUSINESS_TIMEZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/** Timestamp ISO con zona (UTC desde el backend) → "10/11/2026, 14:30" en hora de Lima. */
export function formatTimestamp(iso: string): string {
    return timestampFormatter.format(new Date(iso));
}

const businessDateFormatter = new Intl.DateTimeFormat('en-CA', { timeZone: BUSINESS_TIMEZONE });

/** Hoy en Lima como "YYYY-MM-DD", independiente de la zona del navegador. */
export function todayInBusinessZone(): string {
    return businessDateFormatter.format(new Date());
}

/** Fecha de calendario en Lima de un timestamp ISO: "2026-10-05T03:00:00Z" → "2026-10-04". */
export function businessDateOf(iso: string): string {
    return businessDateFormatter.format(new Date(iso));
}

/** Suma días a una fecha de calendario "YYYY-MM-DD" (aritmética en UTC: sin efectos de zona horaria). */
export function addDays(value: string, days: number): string {
    const { year, month, day } = parseDate(value);
    return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
}

/** Días de calendario entre dos fechas "YYYY-MM-DD" (b − a). */
export function daysBetween(a: string, b: string): number {
    const toUtcDays = (value: string) => {
        const { year, month, day } = parseDate(value);
        return Date.UTC(year, month - 1, day) / 86_400_000;
    };

    return toUtcDays(b) - toUtcDays(a);
}
