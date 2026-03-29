import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const dashboardSource = readFileSync(
    new URL('../../resources/js/pages/Dashboard.vue', import.meta.url),
    'utf8',
);

const dashboardPreviewChartSource = readFileSync(
    new URL('../../resources/js/components/DashboardPreviewChart.vue', import.meta.url),
    'utf8',
);

const messagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/dashboard.ts', import.meta.url),
    'utf8',
);

test('dashboard page exposes accessible account scope and single-account filters', () => {
    assert.match(dashboardSource, /currentAccountScope = computed/);
    assert.match(dashboardSource, /currentAccountUuid = computed/);
    assert.match(dashboardSource, /selectedAccountOption = computed/);
    assert.match(dashboardSource, /shouldShowAccountScopeFilter = computed/);
    assert.match(dashboardSource, /handleAccountScopeSelection/);
    assert.match(dashboardSource, /handleAccountSelection/);
    assert.match(dashboardSource, /account_scope: currentAccountScope\.value/);
    assert.match(dashboardSource, /query\.account_uuid = accountUuid/);
    assert.match(dashboardSource, /dashboard\.filters\.account_scope_options/);
    assert.match(dashboardSource, /dashboard\.filters\.account_options/);
    assert.match(dashboardSource, /v-if="shouldShowAccountScopeFilter"/);
    assert.match(dashboardSource, /accountOptionBadgeClass/);
    assert.match(dashboardSource, /accountOptionOwnershipLabel/);
    assert.match(dashboardSource, /groupedAccountOptions = computed/);
    assert.match(dashboardSource, /option\.account_type_code === 'credit_card'/);
    assert.match(dashboardSource, /SelectGroup/);
    assert.match(dashboardSource, /SelectLabel/);
    assert.match(dashboardSource, /<Badge/);
});

test('dashboard i18n includes labels for accessible account filters', () => {
    assert.match(messagesSource, /accountScopePlaceholder: 'Ambito conti'/);
    assert.match(messagesSource, /accountPlaceholder: 'Conto specifico'/);
    assert.match(messagesSource, /accountAll: 'Tutti i conti nel filtro'/);
    assert.match(messagesSource, /paymentAccountsGroup: 'Conti di pagamento'/);
    assert.match(messagesSource, /creditCardsGroup: 'Carte di credito'/);
    assert.match(messagesSource, /ownedBadge: 'Personale'/);
    assert.match(messagesSource, /sharedBadge: 'Condiviso'/);
    assert.match(messagesSource, /paymentAccountsGroup: 'Payment accounts'/);
    assert.match(messagesSource, /creditCardsGroup: 'Credit cards'/);
    assert.match(messagesSource, /ownedBadge: 'Personal'/);
    assert.match(messagesSource, /sharedBadge: 'Shared'/);
});

test('dashboard i18n includes the transfer section label in both languages', () => {
    assert.match(messagesSource, /sections:\s*\{\s*transfer: 'Addebito mensile della carta di credito'/);
    assert.match(messagesSource, /sections:\s*\{\s*transfer: 'Monthly credit card charge'/);
});

test('dashboard agenda uses payee wording and localized fallback labels instead of merchant wording', () => {
    assert.match(messagesSource, /topPayees: 'Beneficiari principali'/);
    assert.match(messagesSource, /payeesEmpty:/);
    assert.match(messagesSource, /unspecified: 'Non specificato'/);
    assert.match(messagesSource, /topPayees: 'Top payees'/);
    assert.match(messagesSource, /unspecified: 'Unspecified'/);
    assert.doesNotMatch(messagesSource, /topMerchants: 'Merchant principali'/);
    assert.match(dashboardSource, /dashboard\.agenda\.topPayees/);
    assert.match(dashboardSource, /dashboard\.agenda\.entryKinds\./);
});

test('dashboard pending actions box uses operational entries and keeps the same card structure', () => {
    assert.match(dashboardSource, /CalendarClock class="size-5"/);
    assert.match(dashboardSource, /dashboard\.metrics\.pendingActions/);
    assert.match(dashboardSource, /const activePendingActionIndex = ref\(0\)/);
    assert.match(dashboardSource, /const activePendingAction = computed/);
    assert.match(dashboardSource, /window\.setInterval/);
    assert.match(dashboardSource, /@mouseenter="setPendingActionsPaused\(true\)"/);
    assert.match(dashboardSource, /@mouseleave="setPendingActionsPaused\(false\)"/);
    assert.match(dashboardSource, /v-if="activePendingAction"/);
    assert.match(dashboardSource, /<Link/);
    assert.match(
        dashboardSource,
        /ChevronRight\s+class="mt-0\.5 size-4 shrink-0 text-muted-foreground"/,
    );
    assert.match(dashboardSource, /v-if="pendingActionItems\.length > 1"/);
    assert.match(dashboardSource, /dashboard\.metrics\.actionStatuses\./);
    assert.match(
        dashboardSource,
        /class="rounded-\[28px] border border-white\/70 bg-\[linear-gradient\(180deg,rgba\(255,255,255,0\.98\),rgba\(248,250,255,0\.94\)\)] p-5 shadow-sm/,
    );
    assert.doesNotMatch(dashboardSource, /v-for="item in pendingActionItems"/);
    assert.doesNotMatch(dashboardSource, /dashboard\.metrics\.notifications/);
});

test('dashboard layout uses softer breakpoint transitions for laptop viewports', () => {
    assert.match(
        dashboardSource,
        /class="flex flex-col gap-6 2xl:flex-row 2xl:items-start 2xl:justify-between"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 xl:grid-cols-\[minmax\(0,1fr\)_auto] 2xl:w-\[38rem] 2xl:grid-cols-1"/,
    );
    assert.match(
        dashboardSource,
        /class="grid min-w-0 gap-3 xl:grid-cols-2 2xl:grid-cols-1 2xl:gap-4"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-\[1\.35fr_1fr_1fr_\.95fr_1\.15fr]"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 2xl:grid-cols-\[1\.6fr_1fr]"/,
    );
    assert.match(
        dashboardSource,
        /class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-\[0\.88fr_1\.46fr_0\.96fr]"/,
    );
    assert.match(
        dashboardSource,
        /class="xl:col-span-2 overflow-hidden border-white\/70/,
    );
});

test('dashboard preview chart does not use deprecated echarts containLabel', () => {
    assert.doesNotMatch(dashboardPreviewChartSource, /containLabel\s*:/);
});
