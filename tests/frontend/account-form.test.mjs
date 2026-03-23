import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/components/accounts/AccountFormSheet.vue', import.meta.url),
    'utf8',
);

test('account form includes the opening balance date field', () => {
    assert.match(source, /id="opening_balance_date"/);
    assert.match(source, /form\.opening_balance_date/);
    assert.match(source, /accounts\.form\.fields\.openingBalanceDate/);
});

test('account form wires backend validation errors for the opening balance date', () => {
    assert.match(source, /form\.errors\.opening_balance_date/);
    assert.match(source, /isOpeningBalanceDateRequired/);
});

test('account form does not expose the manual management toggle', () => {
    assert.doesNotMatch(source, /accounts\.form\.management\.manual/);
    assert.doesNotMatch(source, /form\.is_manual/);
});
