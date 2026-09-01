import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildDashboardMonthCallout,
    classifyDashboardPeriod,
} from '../../resources/js/lib/dashboard-month-callout.js';

function point(year, month, actual, projected, state = 'current') {
    return {
        year,
        month,
        actual_expense_raw: actual,
        projected_expense_raw: projected,
        state,
    };
}

test('classifies months across year boundaries', () => {
    assert.equal(
        classifyDashboardPeriod(
            { year: 2026, month: 2 },
            { year: 2026, month: 8 },
        ),
        'past',
    );
    assert.equal(
        classifyDashboardPeriod(
            { year: 2026, month: 9 },
            { year: 2026, month: 8 },
        ),
        'future',
    );
    assert.equal(
        classifyDashboardPeriod(
            { year: 2026, month: 8 },
            { year: 2026, month: 8 },
        ),
        'current',
    );
    assert.equal(
        classifyDashboardPeriod(
            { year: 2027, month: 1 },
            { year: 2026, month: 12 },
        ),
        'future',
    );
    assert.equal(
        classifyDashboardPeriod(
            { year: 2026, month: 12 },
            { year: 2027, month: 1 },
        ),
        'past',
    );
});

test('uses real values for past months and projected values for future months', () => {
    const current = point(2026, 8, 780, 1020, 'current');
    const pastHigher = buildDashboardMonthCallout({
        selected: point(2026, 2, 930, 930, 'past'),
        current,
        currentPeriod: { year: 2026, month: 8 },
    });
    const pastLower = buildDashboardMonthCallout({
        selected: point(2026, 3, 660, 660, 'past'),
        current,
        currentPeriod: { year: 2026, month: 8 },
    });
    const futureHigher = buildDashboardMonthCallout({
        selected: point(2026, 9, 0, 1300, 'future'),
        current,
        currentPeriod: { year: 2026, month: 8 },
    });
    const futureLower = buildDashboardMonthCallout({
        selected: point(2026, 10, 0, 900, 'future'),
        current,
        currentPeriod: { year: 2026, month: 8 },
    });

    assert.deepEqual(
        [
            pastHigher.direction,
            pastHigher.severity,
            pastHigher.difference_amount,
        ],
        ['higher', 'warning', 150],
    );
    assert.deepEqual(
        [pastLower.direction, pastLower.severity, pastLower.difference_amount],
        ['lower', 'positive', -120],
    );
    assert.deepEqual(
        [
            futureHigher.direction,
            futureHigher.comparison_amount,
            futureHigher.difference_amount,
        ],
        ['higher', 1020, 280],
    );
    assert.deepEqual(
        [
            futureLower.direction,
            futureLower.comparison_amount,
            futureLower.difference_amount,
        ],
        ['lower', 1020, -120],
    );
});

test('handles current, equal, missing, zero and percentage edge cases without conflating null and zero', () => {
    const current = point(2026, 8, 0, 0, 'current');
    const selectedCurrent = buildDashboardMonthCallout({
        selected: current,
        current,
        currentPeriod: { year: 2026, month: 8 },
    });
    const equal = buildDashboardMonthCallout({
        selected: point(2026, 9, 0, 0, 'future'),
        current,
        currentPeriod: { year: 2026, month: 8 },
    });
    const unavailableSelected = buildDashboardMonthCallout({
        selected: point(2026, 9, null, null, 'future'),
        current,
        currentPeriod: { year: 2026, month: 8 },
    });
    const unavailableComparison = buildDashboardMonthCallout({
        selected: point(2026, 9, 0, 20, 'future'),
        current: point(2026, 8, null, null, 'current'),
        currentPeriod: { year: 2026, month: 8 },
    });
    const immaterial = buildDashboardMonthCallout({
        selected: point(2026, 9, 0, 100.01, 'future'),
        current: point(2026, 8, 0, 100, 'current'),
        currentPeriod: { year: 2026, month: 8 },
    });

    assert.deepEqual(
        [
            selectedCurrent.period_relation,
            selectedCurrent.direction,
            selectedCurrent.selected_amount,
        ],
        ['current', 'same', 0],
    );
    assert.deepEqual(
        [equal.direction, equal.severity, equal.difference_percentage],
        ['same', 'neutral', null],
    );
    assert.equal(unavailableSelected.direction, 'unavailable');
    assert.equal(unavailableComparison.direction, 'unavailable');
    assert.equal(immaterial.direction, 'same');
});
