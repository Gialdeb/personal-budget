import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const dashboardSource = readFileSync(
    new URL('../../resources/js/pages/Dashboard.vue', import.meta.url),
    'utf8',
);

const dashboardMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/dashboard.ts', import.meta.url),
    'utf8',
);

test('dashboard shows a base-currency hint for global aggregated totals', () => {
    assert.match(dashboardSource, /dashboard\.metrics\.baseCurrencyHint/);
    assert.match(dashboardSource, /props\.dashboard\.settings\.base_currency/);
});

test('dashboard translations include the base-currency aggregation hint in both locales', () => {
    assert.match(dashboardMessagesSource, /baseCurrencyHint/);
    assert.match(dashboardMessagesSource, /valuta base/);
    assert.match(dashboardMessagesSource, /base currency/);
});

test('dashboard includes a dismissible quick-start card for users without operational accounts', () => {
    assert.match(dashboardSource, /quick_start\.show/);
    assert.match(dashboardSource, /dashboard-quick-start/);
    assert.match(dashboardSource, /dashboard\.quickStart\.cta/);
    assert.match(dashboardSource, /editBanks\(\)/);
    assert.match(dashboardSource, /persistDashboardQuickStartDismissed/);
    assert.match(dashboardSource, /readDashboardQuickStartDismissed/);
    assert.match(dashboardMessagesSource, /quickStart:/);
    assert.match(dashboardMessagesSource, /Configura il primo conto per iniziare davvero/);
    assert.match(dashboardMessagesSource, /Set up your first account to really get started/);
    assert.match(dashboardMessagesSource, /Apri impostazioni banche/);
    assert.match(dashboardMessagesSource, /Open bank settings/);
});
