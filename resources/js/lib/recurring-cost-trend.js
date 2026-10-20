export function latestPaymentComparison(points) {
    const latest = points.at(-1);
    const previous = points.at(-2);

    return {
        amount: latest?.amount ?? null,
        changeRatio:
            latest && previous && previous.amount !== 0
                ? (latest.amount - previous.amount) / Math.abs(previous.amount)
                : null,
    };
}

export function paymentMonthLabels(points, locale, showYear = false) {
    const spansYears =
        new Set(points.map((point) => point.date.slice(0, 4))).size > 1;
    const formatter = new Intl.DateTimeFormat(locale, {
        month: 'short',
        ...(showYear || spansYears ? { year: '2-digit' } : {}),
    });

    return points.map((point) =>
        formatter.format(new Date(`${point.date}T12:00:00`)),
    );
}

export function paymentYears(points) {
    return [...new Set(points.map((point) => point.date.slice(0, 4)))].sort();
}

export function paymentsForYear(points, year) {
    return points
        .filter((point) => year === 'all' || point.date.slice(0, 4) === year)
        .sort((left, right) => left.date.localeCompare(right.date));
}

export function paymentStatistics(points) {
    const values = points.map((point) => point.amount);

    return {
        minimum: values.length ? Math.min(...values) : null,
        maximum: values.length ? Math.max(...values) : null,
        average: values.length
            ? values.reduce((sum, value) => sum + value, 0) / values.length
            : null,
    };
}

export function costChartPoints(
    payments,
    manualCosts = [],
    changes = [],
    fixed = false,
) {
    return [
        ...payments.map((point) => ({
            ...point,
            key: `real-${point.transaction_uuid}`,
            source: 'real',
        })),
        ...manualCosts.map((point) => ({
            ...point,
            key: `manual-${point.uuid}`,
            manual_uuid: point.uuid,
            source: 'manual',
        })),
        ...(fixed
            ? changes.map((change) => ({
                  key: `automatic-${change.id}`,
                  source: 'automatic',
                  date: change.effective_from,
                  amount: change.new_amount,
              }))
            : []),
    ].sort(
        (left, right) =>
            left.date.localeCompare(right.date) ||
            left.key.localeCompare(right.key),
    );
}

export function realPaymentPoints(points) {
    return points.filter((point) => point.source === 'real');
}

/** Annual totals are informational points, never individual transactions. */
export function annualCostChartPoints(years) {
    return years.map((year) => ({
        key: `annual-${year.year}`,
        source: 'annual',
        date: `${year.year}-01-01`,
        amount: year.total,
    }));
}
