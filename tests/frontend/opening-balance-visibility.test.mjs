import assert from 'node:assert/strict';
import test from 'node:test';
import {
    filterOpeningBalanceTransactions,
    persistOpeningBalanceVisibility,
    readOpeningBalanceVisibility,
} from '../../resources/js/lib/opening-balance-visibility.js';

test('opening balance rows can be hidden from the visual list', () => {
    const transactions = [
        { uuid: 'opening', is_opening_balance: true },
        { uuid: 'manual', is_opening_balance: false },
    ];

    assert.deepEqual(
        filterOpeningBalanceTransactions(transactions, false).map(
            (transaction) => transaction.uuid,
        ),
        ['manual'],
    );
});

test('opening balance rows can be shown again', () => {
    const transactions = [
        { uuid: 'opening', is_opening_balance: true },
        { uuid: 'manual', is_opening_balance: false },
    ];

    assert.deepEqual(
        filterOpeningBalanceTransactions(transactions, true).map(
            (transaction) => transaction.uuid,
        ),
        ['opening', 'manual'],
    );
});

test('opening balance visibility preference is persisted in local storage', () => {
    const storage = new Map();

    globalThis.window = {
        localStorage: {
            getItem(key) {
                return storage.get(key) ?? null;
            },
            setItem(key, value) {
                storage.set(key, value);
            },
        },
    };

    persistOpeningBalanceVisibility(false);
    assert.equal(readOpeningBalanceVisibility(), false);

    persistOpeningBalanceVisibility(true);
    assert.equal(readOpeningBalanceVisibility(), true);

    delete globalThis.window;
});
