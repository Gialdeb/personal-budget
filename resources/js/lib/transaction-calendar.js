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
