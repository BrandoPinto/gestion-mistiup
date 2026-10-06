// Ejecutar con: npm run test:js (node --test, sin dependencias).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { calculateQuote, QuoteMathError, type QuoteMathInput, type QuoteMathResult } from './quote-math.ts';

type Fixture = QuoteMathInput & { name: string; expected: QuoteMathResult };

const fixtures: Fixture[] = JSON.parse(readFileSync(new URL('../../../tests/fixtures/quote-calculations.json', import.meta.url), 'utf8'));

for (const fixture of fixtures) {
    test(`coincide con PHP: ${fixture.name}`, () => {
        assert.deepEqual(calculateQuote(fixture), fixture.expected);
    });
}

test('rechaza un descuento mayor al importe con el mismo campo que el backend', () => {
    assert.throws(
        () => calculateQuote({ items: [{ quantity: '1', unit_price: '100', discount_type: 'amount', discount_value: '150' }], global_discount_type: null, global_discount_value: null, tax_rate: '18' }),
        (error: unknown) => error instanceof QuoteMathError && error.field === 'items.0.discount_value',
    );
});
