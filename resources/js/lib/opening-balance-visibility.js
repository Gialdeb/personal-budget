export const OPENING_BALANCE_VISIBILITY_STORAGE_KEY =
    'transactions.showOpeningBalances';

export function readOpeningBalanceVisibility() {
    if (typeof window === 'undefined') {
        return true;
    }

    const storedValue = window.localStorage.getItem(
        OPENING_BALANCE_VISIBILITY_STORAGE_KEY,
    );

    if (storedValue === null) {
        return true;
    }

    return storedValue === 'true';
}

export function persistOpeningBalanceVisibility(value) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        OPENING_BALANCE_VISIBILITY_STORAGE_KEY,
        String(value),
    );
}

export function filterOpeningBalanceTransactions(transactions, visible) {
    if (visible) {
        return transactions;
    }

    return transactions.filter(
        (transaction) => !transaction.is_opening_balance,
    );
}
