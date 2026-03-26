import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const dashboardSource = readFileSync(
    new URL('../../resources/js/pages/Dashboard.vue', import.meta.url),
    'utf8',
);

const messagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/dashboard.ts', import.meta.url),
    'utf8',
);

test('dashboard page exposes accessible account scope and single-account filters', () => {
    assert.match(dashboardSource, /currentAccountScope = computed/);
    assert.match(dashboardSource, /currentAccountUuid = computed/);
    assert.match(dashboardSource, /handleAccountScopeSelection/);
    assert.match(dashboardSource, /handleAccountSelection/);
    assert.match(dashboardSource, /account_scope: currentAccountScope\.value/);
    assert.match(dashboardSource, /query\.account_uuid = accountUuid/);
    assert.match(dashboardSource, /dashboard\.filters\.account_scope_options/);
    assert.match(dashboardSource, /dashboard\.filters\.account_options/);
});

test('dashboard i18n includes labels for accessible account filters', () => {
    assert.match(messagesSource, /accountScopePlaceholder: 'Ambito conti'/);
    assert.match(messagesSource, /accountPlaceholder: 'Conto specifico'/);
    assert.match(messagesSource, /accountAll: 'Tutti i conti nel filtro'/);
    assert.match(messagesSource, /sharedBadge: 'Condiviso'/);
});

test('dashboard layout uses softer breakpoint transitions for laptop viewports', () => {
    assert.match(
        dashboardSource,
        /class="flex flex-col gap-6 2xl:flex-row 2xl:items-start 2xl:justify-between"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 xl:grid-cols-\[minmax\(0,1fr\)_auto\] 2xl:w-\[38rem\] 2xl:grid-cols-1"/,
    );
    assert.match(
        dashboardSource,
        /class="grid min-w-0 gap-3 xl:grid-cols-2 2xl:grid-cols-1 2xl:gap-4"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-\[1\.35fr_1fr_1fr_\.95fr_1\.15fr\]"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 2xl:grid-cols-\[1\.6fr_1fr\]"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-\[0\.88fr_1\.46fr_0\.96fr\]"/,
    );
    assert.match(
        dashboardSource,
        /class="xl:col-span-2 overflow-hidden border-white\/70/,
    );
});
