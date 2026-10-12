import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
const source = readFileSync(
    new URL('../../resources/js/lib/salary-presets.ts', import.meta.url),
    'utf8',
);
const js = ts.transpile(source.replace(/export /g, ''), {
    target: ts.ScriptTarget.ES2022,
});
const { salaryPresetSchedules } = new Function(
    `${js}; return { salaryPresetSchedules };`,
)();

for (const [preset, months] of [
    ['12', []],
    ['13', [12]],
    ['14', [12, 6]],
]) {
    test(`salary ${preset} UI preset creates editable generic schedules`, () => {
        const schedules = salaryPresetSchedules(preset, '2026-01-01', '2000', [
            'Extra A',
            'Extra B',
        ]);
        assert.deepEqual(
            schedules.map((row) => Number(row.start_date.slice(5, 7))),
            months,
        );
        assert.ok(
            schedules.every(
                (row) =>
                    row.recurrence_type === 'yearly' && row.amount === '2000',
            ),
        );

        if (schedules.length) {
            schedules[0].label = 'Custom label';
            schedules[0].amount = '1234';
            assert.equal(schedules[0].amount, '1234');
        }
    });
}

test('presets never schedule before employment starts and custom has no mandatory extras', () => {
    const rows = salaryPresetSchedules('14', '2026-10-12', '2000', ['A', 'B']);
    assert.deepEqual(
        rows.map((row) => row.start_date),
        ['2026-12-01', '2027-06-01'],
    );
    assert.deepEqual(
        salaryPresetSchedules('custom', '2026-01-01', '2000', []),
        [],
    );
});

test('salary and normal recurring entries share one posting choice UI', () => {
    const entryForm = readFileSync(
        new URL(
            '../../resources/js/components/recurring/RecurringEntryFormSheet.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.ok(entryForm.includes('<RecurringPostingMode'));
    const form = readFileSync(
        new URL(
            '../../resources/js/components/recurring/RecurringPostingMode.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.equal(
        (
            form.match(/transactions\.recurring\.form\.labels\.postingMode/g) ??
            []
        ).length,
        1,
    );
    assert.equal(
        (
            form.match(
                /transactions\.recurring\.form\.helper\.postingManual/g,
            ) ?? []
        ).length,
        1,
    );
    assert.equal(
        (
            form.match(
                /transactions\.recurring\.form\.helper\.postingAutomatic/g,
            ) ?? []
        ).length,
        1,
    );
    assert.ok(!form.includes("t('salary.automatic')"));
    assert.ok(!form.includes('<div v-if="!salaryMode" class="space-y-2">'));
});
