import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pageSource = readFileSync(
    new URL('../../resources/js/pages/settings/ExchangeRates.vue', import.meta.url),
    'utf8',
);

const layoutSource = readFileSync(
    new URL('../../resources/js/layouts/settings/Layout.vue', import.meta.url),
    'utf8',
);

const settingsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/settings.ts', import.meta.url),
    'utf8',
);

test('settings navigation exposes the exchange-rates entry', () => {
    assert.match(layoutSource, /settings\.sections\.exchangeRates/);
    assert.match(layoutSource, /editExchangeRates/);
});

test('exchange-rates settings page renders persisted rates and opens provider links in a new tab', () => {
    assert.match(pageSource, /settings\.exchangeRatesPage\.title/);
    assert.match(pageSource, /exchange_rates\.data/);
    assert.match(pageSource, /target="_blank"/);
    assert.match(pageSource, /settings\.exchangeRatesPage\.filters\.apply/);
    assert.match(pageSource, /localizedPaginationLabel/);
    assert.match(pageSource, /settings\.exchangeRatesPage\.pagination\.navigation/);
    assert.match(pageSource, /settings\.exchangeRatesPage\.pagination\.previous/);
    assert.match(pageSource, /settings\.exchangeRatesPage\.pagination\.next/);
    assert.match(pageSource, /normalizedLabel === 'pagination\.previous'/);
    assert.match(pageSource, /normalizedLabel === 'pagination\.next'/);
});

test('settings translations include exchange-rates copy in both locales', () => {
    assert.match(settingsMessagesSource, /exchangeRates: 'Tassi di cambio'/);
    assert.match(settingsMessagesSource, /exchangeRates: 'Exchange rates'/);
    assert.match(settingsMessagesSource, /snapshot FX già salvati/i);
    assert.match(settingsMessagesSource, /FX snapshots already fixed/i);
    assert.match(settingsMessagesSource, /navigation: 'Navigazione paginazione tassi di cambio'/);
    assert.match(settingsMessagesSource, /navigation: 'Exchange-rate pagination'/);
});
