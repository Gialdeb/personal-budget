import {
    economicCategory,
    transactionAttributes,
} from './transaction-presentation.js';

/** @type {Array<'income'|'expense'|'shared'|'recurring'|'refunded'|'transfer'|'opening'|'deleted'>} */
export const calendarCategoryOrder = [
    'income',
    'expense',
    'shared',
    'recurring',
    'refunded',
    'transfer',
    'opening',
    'deleted',
];

export function transactionDayCounts(transactions) {
    /** @type {Record<string, {total: number, counts: Record<string, number>}>} */
    const days = {};

    for (const transaction of transactions) {
        if (!transaction.date || transaction.is_projected_recurring) {
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

    return days;
}
