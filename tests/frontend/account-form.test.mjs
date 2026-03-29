import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/components/accounts/AccountFormSheet.vue', import.meta.url),
    'utf8',
);
const listSource = readFileSync(
    new URL('../../resources/js/components/accounts/AccountsList.vue', import.meta.url),
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
    assert.match(source, /openingBalanceDateOptions/);
    assert.match(source, /openingBalanceDateMax/);
});

test('account form does not expose the manual management toggle', () => {
    assert.doesNotMatch(source, /accounts\.form\.management\.manual/);
    assert.doesNotMatch(source, /form\.is_manual/);
});

test('account form does not expose scope and normalizes cash accounts to no bank', () => {
    assert.doesNotMatch(source, /accounts\.form\.fields\.scope/);
    assert.doesNotMatch(source, /form\.scope_uuid/);
    assert.match(source, /form\.user_bank_uuid = NONE_OPTION/);
    assert.match(source, /:disabled="isCashAccount"/);
});

test('credit card form hides banking and opening fields and shows the cycle preview', () => {
    assert.match(source, /!isCashAccount && !isCreditCard/);
    assert.match(source, /v-if="!isCreditCard" class="grid gap-2"/);
    assert.match(source, /creditCardClosingRangePreview/);
    assert.match(source, /creditCardNextBillingPreview/);
    assert.match(source, /statement_closing_day: '15'/);
    assert.match(source, /payment_day: '16'/);
    assert.match(source, /v-for="day in 31"/);
    assert.doesNotMatch(source, /creditCard\.description/);
    assert.ok(
        source.indexOf("accounts.form.creditCard.title") <
            source.indexOf("accounts.form.management.title"),
    );
});

test('credit card form filters linked payment accounts by the selected bank and excludes cash accounts', () => {
    assert.match(source, /option\.account_type_code === 'cash_account'/);
    assert.match(source, /option\.user_bank_uuid === form\.user_bank_uuid/);
    assert.match(source, /:disabled="true"/);
    assert.doesNotMatch(source, /linkedPaymentAccountHelper/);
});

test('credit card previews use next cycle start and billing date offset dynamically', () => {
    assert.match(source, /resolveCreditCardCycle/);
    assert.match(source, /creditCardCycle\.value\.current_period_start/);
    assert.match(source, /creditCardCycle\.value\.next_payment_date/);
});

test('account form exposes the reported flag and locks protected cash account controls', () => {
    assert.match(source, /form\.is_reported/);
    assert.match(source, /form\.is_default/);
    assert.match(source, /accounts\.form\.management\.reported/);
    assert.match(source, /accounts\.form\.management\.defaultAccount/);
    assert.match(source, /isProtectedCashAccount/);
    assert.match(source, /openingBalanceDirectionLocked/);
});

test('accounts list hides deactivate and delete actions for protected cash accounts', () => {
    assert.match(listSource, /v-if="account\.can_toggle_active"/);
    assert.match(listSource, /v-if="account\.is_deletable"/);
});
