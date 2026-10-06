import type { Currency, Money } from '@/types/money';

/**
 * Utilidades de dinero en el frontend. Operan en céntimos enteros (bigint) para no usar float.
 * El backend sigue siendo la fuente de verdad: esto solo sirve para mostrar y previsualizar.
 */

export const CURRENCY_SYMBOL: Record<Currency, string> = {
    PEN: 'S/',
    USD: '$',
};

const DECIMAL_PATTERN = /^(-)?(\d+)(?:\.(\d{1,2}))?$/;

/** "1234.5" → 123450n. Lanza error si el string no es un decimal válido de hasta 2 decimales. */
export function toCents(amount: string): bigint {
    const match = DECIMAL_PATTERN.exec(amount.trim());

    if (!match) {
        throw new Error(`Importe inválido: "${amount}"`);
    }

    const [, sign, whole = '0', fraction = ''] = match;
    const cents = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));

    return sign ? -cents : cents;
}

/** 123450n → "1234.50" */
export function fromCents(cents: bigint): string {
    const negative = cents < 0n;
    const abs = negative ? -cents : cents;
    const whole = abs / 100n;
    const fraction = (abs % 100n).toString().padStart(2, '0');

    return `${negative ? '-' : ''}${whole.toString()}.${fraction}`;
}

/** Importe × cantidad entera, exacto: "350.00" × 5 → "1750.00". */
export function multiplyAmount(amount: string, times: number): string {
    return fromCents(toCents(amount) * BigInt(times));
}

export function isValidAmount(amount: string): boolean {
    return DECIMAL_PATTERN.test(amount.trim());
}

/** Agrupa miles con coma y usa punto decimal, como se acostumbra en Perú: 12,345.60 */
function groupThousands(whole: string): string {
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

export function formatAmount(amount: string): string {
    const normalized = fromCents(toCents(amount));
    const negative = normalized.startsWith('-');
    const [whole = '0', fraction = '00'] = normalized.replace('-', '').split('.');

    return `${negative ? '-' : ''}${groupThousands(whole)}.${fraction}`;
}

export function formatMoney({ amount, currency }: Money): string {
    const formatted = formatAmount(amount);
    const negative = formatted.startsWith('-');

    return `${negative ? '-' : ''}${CURRENCY_SYMBOL[currency]} ${formatted.replace('-', '')}`;
}
