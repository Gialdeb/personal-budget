import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);

test('opening balance rows keep the opening badge visible', () => {
    assert.match(source, /transactions\.sheet\.grid\.openingBadge/);
});

test('opening balance rows do not render the income or expense type badge', () => {
    assert.match(source, /v-if="\s*!transaction\.is_opening_balance\s*"/);
});

test('transactions page exposes a toggle to show opening balances', () => {
    assert.match(source, /transactions\.sheet\.filters\.showOpeningBalances/);
    assert.match(source, /showOpeningBalances/);
});
