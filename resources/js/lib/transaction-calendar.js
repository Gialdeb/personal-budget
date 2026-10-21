import {
    economicCategory,
    transactionAttributes,
} from './transaction-presentation.js';

/** @type {Array<'income'|'expense'|'shared'|'advanced'|'recurring'|'refunded'|'transfer'|'opening'|'deleted'>} */
export const calendarCategoryOrder = [
    'income',
    'expense',
    'shared',
    'advanced',
    'recurring',
    'refunded',
    'transfer',
    'opening',
    'deleted',
];

export function transactionDayCounts(transactions, sharedLedgerMovements = []) {
    /** @type {Record<string, {total: number, counts: Record<string, number>}>} */
    const days = {};

    for (const transaction of transactions) {
        if (
            !transaction.date ||
            transaction.is_projected_recurring ||
            transaction.is_shared_ledger
        ) {
            continue;
        }

        const date = transaction.date.slice(0, 10);
        const day = (days[date] ??= { total: 0, counts: {} });
        day.total++;
        const categories = new Set([
            economicCategory(transaction),
            ...transactionAttributes(transaction),
        ]);

        for (const category of categories) {
            if (calendarCategoryOrder.includes(category)) {
                day.counts[category] = (day.counts[category] ?? 0) + 1;
            }
        }
    }

    for (const movement of sharedLedgerMovements) {
        if (!movement.date || !movement.is_shared_ledger) {
            continue;
        }

        const date = movement.date.slice(0, 10);
        const day = (days[date] ??= { total: 0, counts: {} });
        day.total++;
        day.counts.advanced = (day.counts.advanced ?? 0) + 1;
    }

    return days;
}

/** Parse a complete date without normalizing invalid dates or changing its year. */
export function transactionCalendarDate(value, year) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value ?? '')) {
        return null;
    }

    const [selectedYear, month, day] = value.split('-').map(Number);
    const count = new Date(Date.UTC(selectedYear, month, 0)).getUTCDate();

    return selectedYear === year &&
        month >= 1 &&
        month <= 12 &&
        day >= 1 &&
        day <= count
        ? { year: selectedYear, month, day }
        : null;
}

export function transactionCalendarDays(year, month) {
    if (month < 1 || month > 12) {
        return [];
    }

    const offset = (new Date(Date.UTC(year, month - 1, 1)).getUTCDay() + 6) % 7;
    const count = new Date(Date.UTC(year, month, 0)).getUTCDate();

    return [
        ...Array(offset).fill(null),
        ...Array.from({ length: count }, (_, i) => ({
            day: i + 1,
            date: `${year}-${String(month).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`,
        })),
    ];
}

/** A typed day belongs to the chosen month; it must never roll into another month. */
export function transactionDateFromDay(value, year, month, clamp = false) {
    if (!/^\d{1,2}$/.test(String(value)) || month < 1 || month > 12) {
        return '';
    }

    const count = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const day = clamp ? Math.min(Number(value), count) : Number(value);
    const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    return transactionCalendarDate(date, year) ? date : '';
}

/** Compare calendar months to the real current month, independently of the page period. */
export function transactionCalendarPeriod(value, year, now = new Date()) {
    const selected = transactionCalendarDate(value, year);

    if (!selected) {
        return 'invalid';
    }

    const difference =
        selected.year * 12 +
        selected.month -
        (now.getFullYear() * 12 + now.getMonth() + 1);

    return difference === 0 ? 'current' : difference < 0 ? 'past' : 'future';
}
