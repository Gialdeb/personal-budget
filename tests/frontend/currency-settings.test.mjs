import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const profileSource = readFileSync(
    new URL('../../resources/js/pages/settings/Profile.vue', import.meta.url),
    'utf8',
);
const settingsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/settings.ts', import.meta.url),
    'utf8',
);
const accountMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/accounts.ts', import.meta.url),
    'utf8',
);

test('profile base currency options expose the extended currency catalog shape', () => {
    assert.match(profileSource, /base_currencies: Array<\{/);
    assert.match(profileSource, /name: string/);
    assert.match(profileSource, /symbol: string/);
});

test('settings copy distinguishes profile base currency from account currency', () => {
    assert.match(
        settingsMessagesSource,
        /valuta base del profilo, dei riepiloghi e dei report/i,
    );
    assert.match(
        settingsMessagesSource,
        /does not control numeric separators or date format/i,
    );
    assert.match(
        accountMessagesSource,
        /La valuta del conto è indipendente dalla valuta base del profilo/i,
    );
    assert.match(
        accountMessagesSource,
        /The account currency is independent from the profile base currency/i,
    );
});

test('profile format locale copy explains numbers dates and currency separation', () => {
    assert.match(settingsMessagesSource, /Formato numeri e date/);
    assert.match(settingsMessagesSource, /Number and date format/);
    assert.match(
        settingsMessagesSource,
        /I simboli monetari seguono la valuta/i,
    );
    assert.match(settingsMessagesSource, /Money symbols follow the currency/i);
});

test('profile format settings use explicit controls instead of country presets', () => {
    assert.doesNotMatch(profileSource, /id="profile-format-locale"/);
    assert.doesNotMatch(profileSource, /formatLocaleLabel/);
    assert.doesNotMatch(settingsMessagesSource, /Italiano \(Italia\)/);
    assert.match(profileSource, /type="radio"/);
    assert.match(profileSource, /name="number_thousands_separator"/);
    assert.match(profileSource, /name="number_decimal_separator"/);
    assert.match(profileSource, /name="date_format"/);
    assert.match(profileSource, /formatLocaleForm\.number_thousands_separator/);
    assert.match(profileSource, /formatLocaleForm\.number_decimal_separator/);
    assert.match(profileSource, /formatLocaleForm\.date_format/);
});

test('profile format settings render live number amount and date preview', () => {
    assert.match(profileSource, /const formatPreview = computed/);
    assert.match(profileSource, /formatNumberWithSeparators/);
    assert.match(profileSource, /formatAmountPreview/);
    assert.match(profileSource, /formatDatePattern/);
    assert.match(profileSource, /formatPreview\.number/);
    assert.match(profileSource, /formatPreview\.amount/);
    assert.match(profileSource, /formatPreview\.date/);
});

test('profile format settings expose separator and date-format copy', () => {
    assert.match(settingsMessagesSource, /Separatore migliaia/);
    assert.match(settingsMessagesSource, /Separatore decimali/);
    assert.match(settingsMessagesSource, /Formato data/);
    assert.match(settingsMessagesSource, /Thousands separator/);
    assert.match(settingsMessagesSource, /Decimal separator/);
    assert.match(settingsMessagesSource, /Date format/);
    assert.match(settingsMessagesSource, /Dot/);
    assert.match(settingsMessagesSource, /Comma/);
    assert.match(settingsMessagesSource, /Space/);
});

test('profile format settings disable incoherent separator combinations', () => {
    assert.match(
        profileSource,
        /safeDecimalSeparator\(formatLocaleForm\.number_decimal_separator\)/,
    );
    assert.match(
        profileSource,
        /safeThousandsSeparator\(formatLocaleForm\.number_thousands_separator\)/,
    );
    assert.match(profileSource, /function safeDateFormat/);
});
