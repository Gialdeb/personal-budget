import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);

test('ending balance uses carried period balances instead of the visible month rows', () => {
    assert.match(source, /sheet\.value\.meta\.period_ending_balances/);
    assert.match(source, /selectedAccount\.value !== 'all'/);
    assert.match(source, /balance\.account_uuid === selectedAccount\.value/);
    assert.doesNotMatch(source, /filteredTransactions\.value\.find\(\s*\(transaction\) => transaction\.balance_after_raw !== null/s);
});
