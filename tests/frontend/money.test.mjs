import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatMoneyDisplay,
    formatMoneyDraft,
    formatMoneyValue,
    normalizeMoneyValue,
    parseMoneyInput,
    resolveCurrencyIndicator,
    resolveCurrencyPosition,
    shouldAllowMoneyKey,
} from '../../resources/js/lib/money.js';

globalThis.window = {
    __soamcoBudgetCurrencyCatalog: {
        EUR: {
            code: 'EUR',
            name: 'Euro',
            symbol: '€',
            minor_unit: 2,
            symbol_position: 'suffix',
        },
        USD: {
            code: 'USD',
            name: 'US Dollar',
            symbol: '$',
            minor_unit: 2,
            symbol_position: 'prefix',
        },
        CAD: {
            code: 'CAD',
            name: 'Canadian Dollar',
            symbol: 'C$',
            minor_unit: 2,
            symbol_position: 'prefix',
        },
        JPY: {
            code: 'JPY',
            name: 'Japanese Yen',
            symbol: '¥',
            minor_unit: 0,
            symbol_position: 'prefix',
        },
        CNY: {
            code: 'CNY',
            name: 'Chinese Yuan',
            symbol: '¥',
            minor_unit: 2,
            symbol_position: 'prefix',
        },
    },
};

test('formats display for it-IT', () => {
    assert.equal(formatMoneyDisplay('1234.56', 'it-IT'), '1.234,56');
});

test('formats display for en-GB', () => {
    assert.equal(formatMoneyDisplay('1234.56', 'en-GB'), '1,234.56');
});

test('parses localized it-IT input to standardized value', () => {
    assert.equal(parseMoneyInput('1.234,56', 'it-IT'), '1234.56');
});

test('parses localized en-GB input to standardized value', () => {
    assert.equal(parseMoneyInput('1,234.56', 'en-GB'), '1234.56');
});

test('removes negative signs from input', () => {
    assert.equal(parseMoneyInput('-12,34', 'it-IT'), '12.34');
    assert.equal(parseMoneyInput('-12.34', 'en-GB'), '12.34');
});

test('parsing strips arbitrary text from pasted values', () => {
    assert.equal(parseMoneyInput('EUR 1.234,56 abc', 'it-IT'), '1234.56');
});

test('normalizes model values to standardized backend-safe payloads', () => {
    assert.equal(normalizeMoneyValue('001234,50', 'it-IT'), '1234.5');
    assert.equal(normalizeMoneyValue('1,234.50', 'en-US'), '1234.5');
});

test('formats draft values using the current locale separator', () => {
    assert.equal(formatMoneyDraft('1234,56', 'it-IT'), '1234,56');
    assert.equal(formatMoneyDraft('1234.56', 'en-GB'), '1234.56');
});

test('read-only money formatting uses the requested locale and currency', () => {
    assert.equal(formatMoneyValue('1234.56', 'GBP', 'en-GB'), '£1,234.56');
    assert.match(
        formatMoneyValue('1234.56', 'EUR', 'it-IT').replace(/\s/g, ' '),
        /^1\.234,56 .*€$/,
    );
});

test('ambiguous currency symbols automatically switch to the currency code', () => {
    assert.match(
        formatMoneyValue('1234.56', 'USD', 'en-US'),
        /^USD\s?1,234\.56$/,
    );
    assert.match(
        formatMoneyValue('1234.56', 'CNY', 'en-US'),
        /^CNY\s?1,234\.56$/,
    );
    assert.equal(formatMoneyValue('1234.56', 'CAD', 'en-US'), 'C$1,234.56');
});

test('currency indicators and positions follow the resolved display mode', () => {
    assert.equal(
        resolveCurrencyIndicator('USD', 'en-US', {
            preferCodeWhenAmbiguous: true,
        }),
        'USD',
    );
    assert.equal(
        resolveCurrencyIndicator('EUR', 'it-IT', {
            preferCodeWhenAmbiguous: true,
        }),
        '€',
    );
    assert.equal(resolveCurrencyPosition('GBP', 'en-GB'), 'prefix');
    assert.equal(resolveCurrencyPosition('EUR', 'it-IT'), 'suffix');
});

test('minor units are taken from the currency metadata when precision is not forced', () => {
    assert.equal(
        formatMoneyDisplay('1234', 'ja-JP', undefined, 'JPY'),
        '1,234',
    );
    assert.equal(parseMoneyInput('1234.56', 'ja-JP', undefined, 'JPY'), '1234');
    assert.equal(
        formatMoneyDraft('1234.56', 'ja-JP', undefined, 'JPY'),
        '1234',
    );
});

test('read-only money formatting preserves negative values from the domain', () => {
    assert.equal(formatMoneyValue(-250, 'EUR', 'en-GB'), '-€250.00');
});

test('money key filtering rejects letters and minus signs', () => {
    assert.equal(
        shouldAllowMoneyKey('a', {
            formatLocale: 'it-IT',
            currentValue: '',
        }),
        false,
    );
    assert.equal(
        shouldAllowMoneyKey('-', {
            formatLocale: 'en-GB',
            currentValue: '',
        }),
        false,
    );
    assert.equal(
        shouldAllowMoneyKey('5', {
            formatLocale: 'en-GB',
            currentValue: '',
        }),
        true,
    );
});

test('money key filtering allows only the active locale decimal separator', () => {
    assert.equal(
        shouldAllowMoneyKey(',', {
            formatLocale: 'it-IT',
            currentValue: '12',
        }),
        true,
    );
    assert.equal(
        shouldAllowMoneyKey('.', {
            formatLocale: 'it-IT',
            currentValue: '12',
        }),
        false,
    );
});

test('changing format locale changes the displayed value', () => {
    const raw = '1234.56';

    assert.equal(formatMoneyDisplay(raw, 'it-IT'), '1.234,56');
    assert.equal(formatMoneyDisplay(raw, 'en-US'), '1,234.56');
});

test('custom money separators override locale display formatting', () => {
    globalThis.document = {
        documentElement: {
            dataset: {
                numberThousandsSeparator: ' ',
                numberDecimalSeparator: ',',
            },
        },
    };

    assert.equal(formatMoneyDisplay('1234.56', 'en-US'), '1\u00A0234,56');
    assert.equal(
        formatMoneyValue('1234.56', 'EUR', 'en-US'),
        '1\u00A0234,56 €',
    );

    delete globalThis.document;
});
