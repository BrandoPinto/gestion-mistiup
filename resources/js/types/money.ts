export type Currency = 'PEN' | 'USD';

/** Forma en la que el backend envía importes (MoneyPresenter). `amount` es string decimal, nunca number. */
export type Money = {
    amount: string;
    currency: Currency;
};
