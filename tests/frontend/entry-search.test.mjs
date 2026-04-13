import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    buildEntrySearchQuery,
    countActiveEntrySearchFilters,
    hasEntrySearchCriteria,
    parseEntrySearchState,
    sanitizeEntrySearchStateForScope,
} from '../../resources/js/lib/entry-search.js';

const useEntrySearchSource = readFileSync(
    new URL('../../resources/js/composables/useEntrySearch.ts', import.meta.url),
    'utf8',
);
const universalEntrySearchBarSource = readFileSync(
    new URL(
        '../../resources/js/components/entry-search/UniversalEntrySearchBar.vue',
        import.meta.url,
    ),
    'utf8',
);
const appShellEntrySearchSource = readFileSync(
    new URL(
        '../../resources/js/components/entry-search/AppShellEntrySearch.vue',
        import.meta.url,
    ),
    'utf8',
);
const appSidebarLayoutSource = readFileSync(
    new URL(
        '../../resources/js/layouts/app/AppSidebarLayout.vue',
        import.meta.url,
    ),
    'utf8',
);
const appSidebarHeaderSource = readFileSync(
    new URL(
        '../../resources/js/components/AppSidebarHeader.vue',
        import.meta.url,
    ),
    'utf8',
);
const entrySearchResultsSource = readFileSync(
    new URL(
        '../../resources/js/components/entry-search/EntrySearchResults.vue',
        import.meta.url,
    ),
    'utf8',
);
const entrySearchFiltersSheetSource = readFileSync(
    new URL(
        '../../resources/js/components/entry-search/EntrySearchFiltersSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const entrySearchMonthGroupSource = readFileSync(
    new URL(
        '../../resources/js/components/entry-search/EntrySearchResultMonthGroup.vue',
        import.meta.url,
    ),
    'utf8',
);
const entrySearchMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/entry-search.ts', import.meta.url),
    'utf8',
);
const transactionsPageSource = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);
const recurringPageSource = readFileSync(
    new URL(
        '../../resources/js/pages/transactions/recurring/Index.vue',
        import.meta.url,
    ),
    'utf8',
);

test('entry search query helpers round-trip the shared state', () => {
    const state = parseEntrySearchState(
        '?q=rent&scope=recurring&across_months=1&account_uuid=acc-1&with_notes=1&recurring_status=paused',
        'all',
    );

    assert.equal(state.q, 'rent');
    assert.equal(state.scope, 'recurring');
    assert.equal(state.acrossMonths, true);
    assert.equal(state.accountUuid, 'acc-1');
    assert.equal(state.withNotes, true);
    assert.equal(state.recurringStatus, 'paused');
    assert.deepEqual(buildEntrySearchQuery(state, 'all'), {
        q: 'rent',
        scope: 'recurring',
        across_months: '1',
        account_uuid: 'acc-1',
        with_notes: '1',
        recurring_status: 'paused',
    });
});

test('entry search helpers sanitize filters that are not compatible with the current scope', () => {
    const state = sanitizeEntrySearchStateForScope({
        q: 'rent',
        scope: 'transactions',
        acrossMonths: false,
        accountUuid: null,
        categoryUuid: null,
        direction: null,
        amountMin: '',
        amountMax: '',
        withNotes: false,
        withReference: false,
        recurringStatus: 'paused',
    });

    assert.equal(state.recurringStatus, null);
    assert.deepEqual(buildEntrySearchQuery(state, 'all'), {
        q: 'rent',
        scope: 'transactions',
    });
});

test('entry search helpers count active filters and detect active searches', () => {
    const activeState = {
        q: '',
        scope: 'transactions',
        acrossMonths: false,
        accountUuid: 'acc-1',
        categoryUuid: null,
        direction: 'expense',
        amountMin: '100',
        amountMax: '',
        withNotes: true,
        withReference: false,
        recurringStatus: null,
    };

    assert.equal(countActiveEntrySearchFilters(activeState), 4);
    assert.equal(hasEntrySearchCriteria(activeState), true);
    assert.equal(
        hasEntrySearchCriteria({
            ...activeState,
            accountUuid: null,
            direction: null,
            amountMin: '',
            withNotes: false,
        }),
        false,
    );
});

test('composable debounces requests, syncs query string and focuses the mobile input', () => {
    assert.match(useEntrySearchSource, /window\.setTimeout\(\(\) => {\s*void performSearch\(\);\s*}, 240\)/s);
    assert.match(useEntrySearchSource, /window\.history\.replaceState/);
    assert.match(useEntrySearchSource, /window\.addEventListener\('popstate', handlePopState\)/);
    assert.match(useEntrySearchSource, /inputRef\.value\?\.focus\(\)/);
    assert.match(useEntrySearchSource, /router\.visit\(targetUrl/);
});

test('shared search bar exposes one unified desktop and mobile experience', () => {
    assert.match(universalEntrySearchBarSource, /SheetContent/);
    assert.match(universalEntrySearchBarSource, /surfaceTitle/);
    assert.match(universalEntrySearchBarSource, /surfaceDescription/);
    assert.match(universalEntrySearchBarSource, /\[&>button]:hidden/);
    assert.match(universalEntrySearchBarSource, /compactTrigger/);
    assert.match(universalEntrySearchBarSource, /EntrySearchResults/);
    assert.match(universalEntrySearchBarSource, /EntrySearchFiltersSheet/);
    assert.match(universalEntrySearchBarSource, /toggleFilters/);
    assert.match(universalEntrySearchBarSource, /applyFilters/);
    assert.match(universalEntrySearchBarSource, /scopeOptions/);
    assert.match(universalEntrySearchBarSource, /periodOptions/);
    assert.match(universalEntrySearchBarSource, /state\.scope === 'recurring'/);
});

test('authenticated app shell mounts the shared search once and keeps visibility rules centralized', () => {
    assert.doesNotMatch(appSidebarLayoutSource, /AppShellEntrySearch/);
    assert.match(appSidebarHeaderSource, /<AppShellEntrySearch compact \/>/);
    assert.match(appSidebarHeaderSource, /<AppShellEntrySearch \/>/);
    assert.match(appShellEntrySearchSource, /!component\.startsWith\('admin\/'\)/);
    assert.doesNotMatch(appShellEntrySearchSource, /!component\.startsWith\('settings\/'\)/);
    assert.match(appShellEntrySearchSource, /currentPath\.value\.startsWith\('\/transactions'\)/);
    assert.match(appShellEntrySearchSource, /currentPath\.value\.startsWith\('\/recurring-entries'\)/);
    assert.match(appShellEntrySearchSource, /return 'all';/);
});

test('results render month-grouped output with dedicated empty, loading and error states', () => {
    assert.match(entrySearchResultsSource, /EntrySearchResultMonthGroup/);
    assert.match(entrySearchResultsSource, /idleTitle/);
    assert.match(entrySearchResultsSource, /emptyTitle/);
    assert.match(entrySearchResultsSource, /errorTitle/);
    assert.match(entrySearchMonthGroupSource, /entrySearch\.resultKinds\.transaction/);
    assert.match(entrySearchMonthGroupSource, /entrySearch\.resultKinds\.recurring/);
    assert.doesNotMatch(entrySearchMonthGroupSource, /entrySearch\.scopeOptions\.\$\{kind}/);
});

test('advanced filters use the shared category picker and hide recurring-only filters outside recurring scope', () => {
    assert.match(entrySearchFiltersSheetSource, /MobileSearchableSelect/);
    assert.match(entrySearchFiltersSheetSource, /hierarchical/);
    assert.match(entrySearchFiltersSheetSource, /searchCategories/);
    assert.match(entrySearchFiltersSheetSource, /noCategoriesFound/);
    assert.match(entrySearchFiltersSheetSource, /v-if="showRecurringStatus"/);
});

test('entry search messages do not rely on missing singular scope keys and include category filter copy', () => {
    assert.doesNotMatch(entrySearchMessagesSource, /scopeOptions\.transaction/);
    assert.match(entrySearchMessagesSource, /searchCategories/);
    assert.match(entrySearchMessagesSource, /noCategoriesFound/);
    assert.match(entrySearchMessagesSource, /categoryDescription/);
});

test('transactions and recurring pages no longer mount local search bars', () => {
    assert.doesNotMatch(transactionsPageSource, /UniversalEntrySearchBar/);
    assert.doesNotMatch(recurringPageSource, /UniversalEntrySearchBar/);
});

test('recurring page preserves real-context navigation through highlight targets', () => {
    assert.match(recurringPageSource, /readHighlightedRecurringEntryUuid/);
    assert.match(recurringPageSource, /data-recurring-entry-row/);
    assert.match(recurringPageSource, /scrollIntoView/);
});
