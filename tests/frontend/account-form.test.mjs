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
const selectContentSource = readFileSync(
    new URL('../../resources/js/components/ui/select/SelectContent.vue', import.meta.url),
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
    assert.match(
        source,
        /:disabled="\s*isCashAccount \|\| isBankSelectionLocked\s*"/,
    );
    assert.match(source, /include-empty-option/);
    assert.match(source, /search-placeholder="Cerca banca, slug o paese"/);
});

test('account form keeps bank selection editable during create and locks it only in edit mode', () => {
    assert.match(source, /const isBankSelectionLocked = computed/);
    assert.match(source, /isEditing\.value/);
    assert.match(source, /form\.user_bank_uuid !== NONE_OPTION/);
    assert.match(
        source,
        /:disabled="\s*isCashAccount \|\| isBankSelectionLocked\s*"/,
    );
    assert.match(source, /La banca selezionata è in sola lettura/);
});

test('account form keeps account type and balance nature driven only by the selected account type', () => {
    assert.match(
        source,
        /option\) => option\.uuid === form\.account_type_uuid/,
    );
    assert.match(
        source,
        /selectedAccountType\?\.balance_nature_label \?\?/,
    );
    assert.match(
        source,
        /accounts\.form\.fields\.selectAccountTypeFirst/,
    );
});

test('select dropdown content is layered above sheets and dialogs', () => {
    assert.match(selectContentSource, /z-\[200\]/);
    assert.doesNotMatch(selectContentSource, /\bz-50\b/);
});

test('account form uses the shared currency catalog and surfaces currency locks', () => {
    assert.match(source, /currencies: CurrencyOption\[]/);
    assert.match(source, /v-for="currency in props\.currencies"/);
    assert.match(source, /accounts\.form\.fields\.currencyPlaceholder/);
    assert.match(source, /const isCurrencyLocked = computed/);
    assert.match(source, /currencyLockMessage/);
    assert.match(source, /form\.errors\.currency/);
    assert.match(source, /currency: form\.currency/);
    assert.match(source, /currency: userBaseCurrencyCode\.value/);
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
    assert.match(source, /creditCardLinkedPaymentAccountDisabled/);
    assert.match(source, /linkedPaymentAccountSelectBankFirst/);
    assert.match(source, /linkedPaymentAccountEmpty/);
});

test('credit card previews use next cycle start and billing date offset dynamically', () => {
    assert.match(source, /resolveCreditCardCycle/);
    assert.match(source, /creditCardCycle\.value\.current_period_start/);
    assert.match(source, /creditCardCycle\.value\.next_payment_date/);
});

test('account form reuses the shared mobile amount input for editable monetary fields', () => {
    assert.match(source, /import MobileAmountInput from '@\/components\/MobileAmountInput\.vue';/);
    assert.match(source, /<MobileAmountInput[\s\S]*id="opening_balance"/);
    assert.match(source, /<MobileAmountInput[\s\S]*id="current_balance"/);
    assert.match(source, /<MobileAmountInput[\s\S]*id="credit_limit"/);
    assert.doesNotMatch(source, /import MoneyInput from '@\/components\/MoneyInput\.vue';/);
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
