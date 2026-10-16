import assert from 'node:assert/strict';
import test from 'node:test';
import { matchesSharedLedgerDisplay } from '../../resources/js/lib/transaction-presentation.js';
import {
    economicCategory,
    matchesTransactionDisplay,
    rowCategory,
    transactionAttributes,
    transactionTones,
} from '../../resources/js/lib/transaction-presentation.js';
const defaults = {
    income: true,
    expense: true,
    opening: true,
    refunded: false,
    recurring: true,
    shared: true,
};
const expense = {
    uuid: 'expense',
    direction: 'expense',
    type_key: 'bill',
    is_refunded: false,
};
const visible = (row, filters = {}) =>
    matchesTransactionDisplay(row, { ...defaults, ...filters });

test('economic nature is independent from macrogroup and overlapping attributes', () => {
    const row = {
        ...expense,
        is_reimbursement: true,
        shared_expense_counterparty_uuid: 'person',
        is_recurring_transaction: true,
    };
    assert.equal(economicCategory(row), 'expense');
    assert.deepEqual(transactionAttributes(row), [
        'refunded',
        'shared',
        'recurring',
    ]);
    assert.equal(rowCategory(row), 'refunded');
    assert.equal(economicCategory({ ...row, is_deleted: true }), 'expense');
    assert.equal(rowCategory({ ...row, is_deleted: true }), 'deleted');
    assert.equal(
        economicCategory({ ...expense, is_opening_balance: true }),
        'opening',
    );
    assert.equal(
        economicCategory({ ...expense, is_transfer: true }),
        'transfer',
    );
});

test('inclusion switches hide only the corresponding economic direction', () => {
    const rows = [
        expense,
        { uuid: 'income', direction: 'income' },
        { ...expense, uuid: 'opening', is_opening_balance: true },
        { ...expense, uuid: 'transfer', is_transfer: true },
    ];
    assert.deepEqual(
        rows
            .filter((row) => visible(row, { expense: false }))
            .map((row) => row.uuid),
        ['income', 'opening', 'transfer'],
    );
    assert.deepEqual(
        rows
            .filter((row) => visible(row, { income: false, opening: false }))
            .map((row) => row.uuid),
        ['expense', 'transfer'],
    );
    assert.ok(visible({ ...expense, is_deleted: true }));
});

test('refund filter selects canonical reimbursement entries and preserves income nature', () => {
    const ordinary = {
        direction: 'income',
        is_reimbursement: false,
        description: 'Rimborso',
    };
    const refund = {
        direction: 'income',
        kind: 'refund',
        is_reimbursement: true,
        reimbursement_kind: 'transaction_refund',
    };
    const original = { ...expense, is_refunded: true, is_reimbursement: false };
    assert.equal(visible(ordinary), true);
    assert.equal(visible(refund), true);
    assert.equal(visible(ordinary, { refunded: true }), false);
    assert.equal(visible(refund, { refunded: true }), true);
    assert.equal(visible(original, { refunded: true }), false);
    assert.equal(economicCategory(refund), 'income');
    assert.deepEqual(transactionAttributes(refund), ['refunded']);
    assert.deepEqual(transactionAttributes(original), []);
    assert.equal(
        visible(refund, { income: true, expense: false, refunded: true }),
        true,
    );
    assert.equal(visible(refund, { income: false, refunded: true }), false);
    assert.equal(
        visible(
            { ...ordinary, kind: 'refund', refunded_transaction: null },
            { refunded: true },
        ),
        false,
    );
});

test('recurring toggle uses the real link flag and never admits unrecorded previews', () => {
    assert.equal(visible({ ...expense, is_recurring_transaction: true }), true);
    assert.equal(
        visible(
            { ...expense, is_recurring_transaction: true },
            { recurring: false },
        ),
        false,
    );
    assert.equal(
        visible(
            { ...expense, description: 'Abbonamento mensile' },
            { recurring: false },
        ),
        true,
    );
    assert.equal(visible({ ...expense, is_projected_recurring: true }), false);
    assert.equal(
        visible({
            ...expense,
            is_projected_recurring: true,
            is_recurring_transaction: true,
        }),
        false,
    );
});

test('disabled attributes exclude their rows while enabled attributes do not restrict ordinary movements', () => {
    const row = {
        ...expense,
        is_reimbursement: true,
        shared_expense_counterparty_uuid: 'person',
        is_recurring_transaction: true,
    };
    assert.equal(visible(row), true);

    for (const key of ['expense', 'shared', 'recurring']) {
        assert.equal(visible(row, { [key]: false }), false);
    }

    assert.equal(visible(expense, { shared: true, recurring: true }), true);
    assert.equal(
        visible(expense, { shared: false, recurring: false, refunded: false }),
        true,
    );
});

test('turning off inclusion chips does not add overlapping rows', () => {
    const rows = [
        expense,
        { ...expense, is_refunded: true },
        { ...expense, shared_expense_counterparty_uuid: 'person' },
        { ...expense, is_recurring_transaction: true },
        {
            ...expense,
            shared_expense_counterparty_uuid: 'person',
            is_recurring_transaction: true,
        },
    ];
    const flags = ['income', 'expense', 'opening', 'shared', 'recurring'];

    for (let mask = 0; mask < 64; mask++) {
        const filters = Object.fromEntries(
            flags.map((flag, index) => [flag, !!(mask & (1 << index))]),
        );
        const current = rows.filter((row) => visible(row, filters));

        for (const flag of flags) {
            const narrowed = rows.filter((row) =>
                visible(row, { ...filters, [flag]: false }),
            );
            assert.ok(narrowed.every((row) => current.includes(row)));
        }
    }
});

test('every classification has a distinct shared palette for badges and row indicators', () => {
    const keys = [
        'income',
        'expense',
        'opening',
        'deleted',
        'refunded',
        'shared',
        'recurring',
    ];
    assert.equal(
        new Set(keys.map((key) => transactionTones[key].badge)).size,
        keys.length,
    );

    for (const key of keys) {
        assert.match(transactionTones[key].badge, /border-/);
        assert.match(transactionTones[key].badge, /bg-/);
        assert.match(transactionTones[key].badge, /dark:/);
        assert.match(transactionTones[key].accent, /border-/);
    }
});

test('transfer switch is independent of income and expense and uses its own tone', () => {
    const transfer = {
        is_transfer: true,
        direction: 'expense',
        date: '2026-10-15',
    };
    assert.equal(economicCategory(transfer), 'transfer');
    assert.equal(visible(transfer, { transfer: false }), false);
    assert.equal(
        visible(transfer, { transfer: true, income: false, expense: false }),
        true,
    );
    assert.equal(visible(transfer, { day: '2026-10-15' }), true);
    assert.equal(visible(transfer, { day: '2026-10-16' }), false);
    assert.equal(visible(transfer, { day: '' }), true);
});

test('advance filters use the canonical payer, day and category independently from bank outflows', () => {
    const movement = {
        is_shared_ledger: true,
        shared_expense_paid_by: 'counterparty',
        date: '2026-10-10',
        shared_expense_counterparty_uuid: 'payer-a',
        tracked_item_uuid: 'merchant',
        category_uuid: 'food',
        type_key: 'expense',
        shared_expense_my_share: '30.00',
    };
    const filters = {
        shared: true,
        refunded: false,
        day: '',
        period: '2026-10',
        advancedBy: 'all',
        category: 'all',
        macrogroup: 'all',
        account: 'all',
        expense: false,
    };
    assert.equal(matchesSharedLedgerDisplay(movement, filters), true);

    for (const change of [
        { day: '2026-10-11' },
        { period: '2026-11' },
        { advancedBy: 'payer-b' },
        { advancedBy: 'merchant' },
        { category: 'other' },
        { macrogroup: 'income' },
        { shared: false },
        { account: 'bank-a' },
        { refunded: true },
    ]) {
        assert.equal(
            matchesSharedLedgerDisplay(movement, { ...filters, ...change }),
            false,
        );
    }

    assert.equal(
        matchesSharedLedgerDisplay(movement, {
            ...filters,
            advancedBy: 'payer-a',
            day: '2026-10-10',
        }),
        true,
    );
    assert.equal(
        matchesSharedLedgerDisplay(
            { ...movement, is_shared_ledger: false },
            filters,
        ),
        false,
    );
});
