import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);

test('opening balance rows keep the opening badge visible', () => {
    assert.match(source, /transactions\.sheet\.grid\.openingBadge/);
});

test('opening balance rows do not render the income or expense type badge', () => {
    assert.match(source, /v-if="\s*!transaction\.is_opening_balance\s*"/);
});

test('transactions page exposes a toggle to show opening balances', () => {
    assert.match(source, /transactions\.sheet\.filters\.showOpeningBalances/);
    assert.match(source, /showOpeningBalances/);
});

test('transactions page exposes a toggle and dedicated markers for recurring movements', () => {
    assert.match(source, /transactions\.sheet\.filters\.showPlannedRecurring/);
    assert.match(source, /showPlannedRecurring/);
    assert.match(source, /transactions\.sheet\.grid\.recurringBadge/);
    assert.match(source, /transactions\.sheet\.grid\.plannedRecurringBadge/);
    assert.match(source, /data-transaction-row/);
    assert.match(source, /highlight/);
});

test('transactions page exposes visibility filter and restore action for deleted rows', () => {
    assert.match(source, /transactions\.sheet\.filters\.visibility/);
    assert.match(source, /transactions\.sheet\.filters\.showDeletedOnly/);
    assert.match(source, /<SelectItem value="active">/);
    assert.match(source, /<SelectItem value="deleted">/);
    assert.match(source, /<SelectItem value="all">/);
    assert.match(source, /transactions\.sheet\.actions\.restore/);
    assert.match(source, /transactions\.sheet\.actions\.forceDelete/);
    assert.match(source, /transactions\.sheet\.grid\.deletedBadge/);
    assert.match(source, /restoreTransaction/);
    assert.match(source, /forceDeleteTransaction/);
});

test('scheduled transactions expose recurring management instead of delete-only handling', () => {
    assert.match(source, /transactions\.sheet\.actions\.openRecurring/);
    assert.match(source, /transaction\.kind === 'scheduled'/);
    assert.match(source, /transaction\.recurring_entry_show_url/);
    assert.match(source, /TooltipProvider/);
    assert.match(source, /TooltipTrigger as-child/);
    assert.match(source, /ArrowUpRight/);
    assert.match(source, /aria-label="t\('transactions\.sheet\.actions\.openRecurring'\)"/);
});
