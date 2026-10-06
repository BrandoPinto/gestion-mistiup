/**
 * Espejo exacto de App\Domain\Quotes\Services\QuoteCalculator para la vista previa en vivo del editor.
 * El backend recalcula y guarda; ambos se verifican contra tests/fixtures/quote-calculations.json.
 *
 * Todo se opera en enteros (bigint): importes en céntimos, cantidades y porcentajes en centésimas.
 * Sin imports en tiempo de ejecución para poder probarlo con `node --test`.
 */

export type DiscountType = 'percent' | 'amount';

export type QuoteMathItem = {
    quantity: string;
    unit_price: string;
    discount_type: DiscountType | null;
    discount_value: string | null;
};

export type QuoteMathInput = {
    items: QuoteMathItem[];
    global_discount_type: DiscountType | null;
    global_discount_value: string | null;
    tax_rate: string;
};

export type QuoteMathResult = {
    lines: { gross: string; discount: string; total: string }[];
    subtotal: string;
    global_discount: string;
    discount_total: string;
    total: string;
    tax_base: string;
    tax_amount: string;
};

/** Error atribuible a un campo, con la misma clave que usa el backend ("items.2.discount_value"). */
export class QuoteMathError extends Error {
    readonly field: string;

    constructor(field: string, message: string) {
        super(message);
        this.field = field;
    }
}

const DECIMAL = /^(\d+)(?:\.(\d{1,2}))?$/;

/** "12.5" → 1250n (centésimas). Vacío o inválido → null. */
export function toHundredths(value: string | null | undefined): bigint | null {
    const match = DECIMAL.exec((value ?? '').trim());
    if (!match) return null;
    return BigInt(match[1] ?? '0') * 100n + BigInt((match[2] ?? '').padEnd(2, '0'));
}

function format(cents: bigint): string {
    const whole = cents / 100n;
    const fraction = (cents % 100n).toString().padStart(2, '0');
    return `${whole.toString()}.${fraction}`;
}

/** División entera con redondeo HALF_UP (operandos no negativos). */
function divHalfUp(numerator: bigint, denominator: bigint): bigint {
    return (numerator * 2n + denominator) / (denominator * 2n);
}

function discount(base: bigint, type: DiscountType | null, value: string | null, field: string): bigint {
    const amount = toHundredths(value);
    if (type === null || amount === null) return 0n;

    if (type === 'percent') {
        if (amount > 10000n) throw new QuoteMathError(field, 'El descuento no puede superar el 100%.');
        return divHalfUp(base * amount, 10000n);
    }

    if (amount > base) throw new QuoteMathError(field, 'El descuento no puede ser mayor que el importe.');
    return amount;
}

export function calculateQuote(input: QuoteMathInput): QuoteMathResult {
    let subtotal = 0n;
    let lineDiscounts = 0n;

    const lines = input.items.map((item, index) => {
        const quantity = toHundredths(item.quantity) ?? 0n;
        const unitPrice = toHundredths(item.unit_price) ?? 0n;
        const gross = divHalfUp(quantity * unitPrice, 100n);
        const lineDiscount = discount(gross, item.discount_type, item.discount_value, `items.${index}.discount_value`);
        const total = gross - lineDiscount;

        subtotal += total;
        lineDiscounts += lineDiscount;

        return { gross: format(gross), discount: format(lineDiscount), total: format(total) };
    });

    const globalDiscount = discount(subtotal, input.global_discount_type, input.global_discount_value, 'global_discount_value');
    const total = subtotal - globalDiscount;
    const rate = toHundredths(input.tax_rate) ?? 0n;
    const base = rate === 0n ? total : divHalfUp(total * 10000n, 10000n + rate);

    return {
        lines,
        subtotal: format(subtotal),
        global_discount: format(globalDiscount),
        discount_total: format(lineDiscounts + globalDiscount),
        total: format(total),
        tax_base: format(base),
        tax_amount: format(total - base),
    };
}
