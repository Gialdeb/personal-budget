import assert from 'node:assert/strict';
import test from 'node:test';
import { transactionDayCounts } from '../../resources/js/lib/transaction-calendar.js';

test('calendar counts economic types and actual attributes without duplicating day total', () => {
    const days = transactionDayCounts([
        {
            date: '2026-10-15',
            direction: 'expense',
            shared_expense_counterparty_uuid: 'person',
            is_recurring_transaction: true,
        },
        { date: '2026-10-15', direction: 'income', is_reimbursement: true },
        { date: '2026-10-16', is_transfer: true },
        {
            date: '2026-10-16',
            direction: 'income',
            is_opening_balance: true,
            is_deleted: true,
        },
        {
            date: '2026-10-15',
            direction: 'expense',
            is_projected_recurring: true,
        },
    ]);
    assert.deepEqual(days['2026-10-15'], {
        total: 2,
        counts: { expense: 1, shared: 1, recurring: 1, income: 1, refunded: 1 },
    });
    assert.deepEqual(days['2026-10-16'], {
        total: 2,
        counts: { transfer: 1, opening: 1, deleted: 1 },
    });
    assert.equal(days['2026-10-17'], undefined);
    assert.equal(days['2026-10-15'].counts.transfer, undefined);
});
