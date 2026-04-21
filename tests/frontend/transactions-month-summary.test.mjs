import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const transactionsShowSource = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);
const monthlySheetSource = readFileSync(
    new URL(
        '../../resources/js/pages/transactions/MonthlySheet.vue',
        import.meta.url,
    ),
    'utf8',
);

test('ending balance uses carried period balances instead of the visible month rows', () => {
    assert.match(
        transactionsShowSource,
        /sheet\.value\.meta\.period_ending_balances/,
    );
    assert.match(transactionsShowSource, /selectedAccount\.value !== 'all'/);
    assert.match(
        transactionsShowSource,
        /balance\.account_uuid === selectedAccount\.value/,
    );
    assert.doesNotMatch(
        transactionsShowSource,
        /filteredTransactions\.value\.find\(\s*\(transaction\) => transaction\.balance_after_raw !== null/s,
    );
});

test('monthly transactions summary uses shared card and muted surface tokens', () => {
    assert.match(monthlySheetSource, /border-border\/80 bg-card\/88 shadow-sm/);
    assert.match(
        monthlySheetSource,
        /border-b border-border\/70 bg-muted\/65 p-4/,
    );
    assert.match(
        monthlySheetSource,
        /border-border\/80 bg-background\/80 hover:bg-background/,
    );
    assert.match(monthlySheetSource, /text-muted-foreground/);
});
