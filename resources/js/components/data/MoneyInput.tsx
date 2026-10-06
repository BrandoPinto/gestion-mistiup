import { cn } from '@/lib/cn';
import { CURRENCY_SYMBOL } from '@/lib/money';
import type { Currency } from '@/types/money';

type MoneyInputProps = {
    id?: string;
    amount: string;
    currency: Currency;
    onAmountChange: (amount: string) => void;
    /** Si se omite, la moneda se muestra fija (no editable). */
    onCurrencyChange?: (currency: Currency) => void;
    invalid?: boolean;
    placeholder?: string;
    describedBy?: string;
};

/**
 * Importe + moneda en un solo control. El valor es texto decimal (nunca number) y
 * solo admite dígitos y un punto con hasta 2 decimales mientras se escribe.
 */
export function MoneyInput({ id, amount, currency, onAmountChange, onCurrencyChange, invalid = false, placeholder = '0.00', describedBy }: MoneyInputProps) {
    function handleAmount(value: string) {
        const normalized = value.replace(',', '.').replace(/[^\d.]/g, '');

        if (/^\d{0,10}(\.\d{0,2})?$/.test(normalized)) {
            onAmountChange(normalized);
        }
    }

    return (
        <div
            className={cn(
                'flex h-9 overflow-hidden rounded-sm border bg-surface transition-colors focus-within:ring-2 max-sm:h-11',
                invalid
                    ? 'border-danger-600 focus-within:ring-danger-600/20'
                    : 'border-line-strong hover:border-ink-300 focus-within:border-brand-600 focus-within:ring-brand-600/25',
            )}
        >
            {onCurrencyChange ? (
                <select
                    aria-label="Moneda"
                    value={currency}
                    onChange={(event) => onCurrencyChange(event.target.value as Currency)}
                    className="border-r border-line bg-cream-50 px-2.5 text-sm font-medium text-ink-700 focus:outline-none"
                >
                    <option value="PEN">S/ PEN</option>
                    <option value="USD">$ USD</option>
                </select>
            ) : (
                <span className="flex items-center border-r border-line bg-cream-50 px-3 text-sm font-medium text-ink-500">{CURRENCY_SYMBOL[currency]}</span>
            )}
            <input
                id={id}
                inputMode="decimal"
                autoComplete="off"
                value={amount}
                onChange={(event) => handleAmount(event.target.value)}
                placeholder={placeholder}
                aria-invalid={invalid || undefined}
                aria-describedby={describedBy}
                className="numeric min-w-0 flex-1 bg-transparent px-3 text-right text-base text-ink-900 placeholder:text-ink-400 focus:outline-none"
            />
        </div>
    );
}
