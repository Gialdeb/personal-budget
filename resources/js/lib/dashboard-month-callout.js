export const MONTH_CALLOUT_THRESHOLD = 0.01;

/**
 * @typedef {{ year: number, month: number, state?: 'past' | 'current' | 'future', actual_expense_raw: number | null, projected_expense_raw: number | null }} DashboardMonthCalloutPoint
 */

/**
 * @typedef {{ year: number, month: number }} DashboardPeriod
 */

function amountOrNull(value) {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function rounded(value) {
    return Math.round(value * 100) / 100;
}

/**
 * @param {DashboardPeriod} selected
 * @param {DashboardPeriod} current
 */
export function classifyDashboardPeriod(selected, current) {
    const selectedIndex = selected.year * 12 + selected.month;
    const currentIndex = current.year * 12 + current.month;

    if (selectedIndex < currentIndex) {
return 'past';
}

    if (selectedIndex > currentIndex) {
return 'future';
}

    return 'current';
}

/**
 * @param {{ selected: DashboardMonthCalloutPoint, current?: DashboardMonthCalloutPoint | null, currentPeriod?: DashboardPeriod | null }} input
 */
export function buildDashboardMonthCallout({
    selected,
    current = null,
    currentPeriod = null,
}) {
    const relation = currentPeriod
        ? classifyDashboardPeriod(selected, currentPeriod)
        : selected.state;
    const selectedAmount = amountOrNull(
        relation === 'future'
            ? selected.projected_expense_raw
            : selected.actual_expense_raw,
    );

    if (relation === 'current') {
        return {
            period_relation: relation,
            direction: selectedAmount === null ? 'unavailable' : 'same',
            severity: 'neutral',
            selected_amount: selectedAmount,
            comparison_amount: null,
            difference_amount: null,
            difference_percentage: null,
        };
    }

    const comparisonAmount = amountOrNull(
        relation === 'future'
            ? current?.projected_expense_raw
            : current?.actual_expense_raw,
    );

    if (selectedAmount === null || comparisonAmount === null) {
        return {
            period_relation: relation,
            direction: 'unavailable',
            severity: 'neutral',
            selected_amount: selectedAmount,
            comparison_amount: comparisonAmount,
            difference_amount: null,
            difference_percentage: null,
        };
    }

    const differenceAmount = rounded(selectedAmount - comparisonAmount);
    const direction =
        Math.abs(differenceAmount) <= MONTH_CALLOUT_THRESHOLD
            ? 'same'
            : differenceAmount > 0
              ? 'higher'
              : 'lower';

    return {
        period_relation: relation,
        direction,
        severity:
            direction === 'higher'
                ? 'warning'
                : direction === 'lower'
                  ? 'positive'
                  : 'neutral',
        selected_amount: selectedAmount,
        comparison_amount: comparisonAmount,
        difference_amount: differenceAmount,
        difference_percentage:
            comparisonAmount === 0
                ? null
                : Math.round((differenceAmount / comparisonAmount) * 1000) / 10,
    };
}
