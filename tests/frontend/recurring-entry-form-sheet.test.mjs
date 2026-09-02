import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL(
        '../../resources/js/components/recurring/RecurringEntryFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);

test('installment amount changes require an explicit recalculation confirmation', () => {
    assert.match(source, /&&\s*!confirmInstallmentRecalculation/);
    assert.match(source, /installmentRecalculationDialogOpen\.value = true/);
    assert.match(source, /@click="submit\(\)"/);
    assert.match(
        source,
        /installmentRecalculationDialogOpen = false;\s*submit\(true\);/,
    );
});
