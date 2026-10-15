import assert from 'node:assert/strict';
import test from 'node:test';
import {
    TRANSACTION_FILTER_STORAGE_KEY as key,
    clearTransactionFilterUrl,
    readTransactionFilterPreference,
    resolveTransactionFilters,
    storeTransactionFilterPreference,
    transactionFilterDefaults as defaults,
    transactionFilterUrl,
    transactionFiltersAreCustomized,
} from '../../resources/js/lib/transaction-filter-preferences.js';
const storage = () => {
    const values = new Map();

    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: (key) => values.delete(key),
    };
};
test('without opt in a new transactions visit uses defaults and ignores legacy auto preferences', () => {
    const local = storage();
    local.setItem('transactions.visibilityFilter', 'deleted');
    local.setItem('transactions.showOpeningBalances', 'false');
    assert.equal(readTransactionFilterPreference(local), null);
    assert.deepEqual(
        resolveTransactionFilters(
            '/transactions',
            readTransactionFilterPreference(local),
        ),
        defaults,
    );
    assert.equal(defaults.refunded, false);
    assert.equal(defaults.deleted, false);
});
test('opt in remembers the current filters and subsequent changes', () => {
    const local = storage();
    storeTransactionFilterPreference(
        local,
        { ...defaults, refunded: true },
        true,
    );
    assert.equal(
        resolveTransactionFilters(
            '/transactions',
            readTransactionFilterPreference(local),
        ).refunded,
        true,
    );
    storeTransactionFilterPreference(
        local,
        { ...defaults, refunded: true, expense: false, search: 'Mario' },
        true,
    );
    assert.deepEqual(readTransactionFilterPreference(local), {
        ...defaults,
        refunded: true,
        expense: false,
        search: 'Mario',
    });
});
test('explicit URL filters take precedence as a complete configuration over saved preferences', () => {
    const saved = {
        ...defaults,
        refunded: true,
        expense: false,
        account: 'saved-account',
        search: 'saved-search',
    };
    assert.deepEqual(
        resolveTransactionFilters('/transactions?tx_expense=1', saved),
        defaults,
    );
    assert.deepEqual(
        resolveTransactionFilters('/transactions?tx_filters=1', saved),
        defaults,
    );
    assert.deepEqual(
        resolveTransactionFilters(
            '/transactions?search=Mario&tx_refunded=1',
            saved,
        ),
        { ...defaults, search: 'Mario', refunded: true },
    );
});
test('period and focus links do not override remembered filters unless they specify a filter', () => {
    const saved = { ...defaults, refunded: true };
    assert.deepEqual(
        resolveTransactionFilters(
            '/transactions/2026/10?highlight=uuid',
            saved,
        ),
        saved,
    );
});
test('URL synchronization preserves unrelated query and uses compact explicit filter keys', () => {
    const url = transactionFilterUrl(
        '/transactions/2026/10?highlight=uuid&year=2026',
        { ...defaults, expense: false, refunded: true },
    );
    const params = new URL(url, 'https://local').searchParams;
    assert.equal(params.get('highlight'), 'uuid');
    assert.equal(params.get('year'), '2026');
    assert.equal(params.get('tx_expense'), '0');
    assert.equal(params.get('tx_refunded'), '1');
    assert.deepEqual(resolveTransactionFilters(url, null), {
        ...defaults,
        expense: false,
        refunded: true,
    });
});
test('restoring defaults clears remembered configuration and filter query only', () => {
    const local = storage();
    local.setItem('other-section:filters', 'untouched');
    storeTransactionFilterPreference(
        local,
        { ...defaults, refunded: true },
        true,
    );
    storeTransactionFilterPreference(local, defaults, false);
    assert.equal(readTransactionFilterPreference(local), null);
    assert.equal(local.getItem('other-section:filters'), 'untouched');
    assert.equal(
        clearTransactionFilterUrl(
            '/transactions/2026/10?tx_refunded=1&tx_filters=1&search=x&highlight=uuid',
        ),
        '/transactions/2026/10?highlight=uuid',
    );
    assert.equal(transactionFiltersAreCustomized(defaults), false);
});
test('saved state is namespaced, versioned and isolated per user', () => {
    const local = storage();
    assert.match(key, /^soamco:transactions:filters:v1$/);
    storeTransactionFilterPreference(
        local,
        { ...defaults, refunded: true },
        true,
        key + ':a',
    );
    assert.equal(readTransactionFilterPreference(local, key + ':b'), null);
    assert.equal(
        readTransactionFilterPreference(local, key + ':a').refunded,
        true,
    );
});
test('invalid or inaccessible storage fails without changing defaults', () => {
    const local = storage();
    local.setItem(key, 'broken json');
    assert.equal(readTransactionFilterPreference(local), null);
    const unavailable = {
        getItem() {
            throw new Error('blocked');
        },
        setItem() {
            throw new Error('blocked');
        },
        removeItem() {
            throw new Error('blocked');
        },
    };
    assert.equal(readTransactionFilterPreference(unavailable), null);
    assert.equal(
        storeTransactionFilterPreference(unavailable, defaults, true),
        false,
    );
});

test('day and transfer filters round trip through URL and saved preferences', () => {
    const filters = { ...defaults, transfer: false, day: '2026-10-15' };
    const url = transactionFilterUrl('/transactions/2026/10', filters);
    assert.deepEqual(resolveTransactionFilters(url, null), filters);
    const local = storage();
    storeTransactionFilterPreference(local, filters, true);
    assert.deepEqual(readTransactionFilterPreference(local), filters);
    assert.equal(
        resolveTransactionFilters('/transactions?tx_day=bad', null).day,
        '',
    );
});
