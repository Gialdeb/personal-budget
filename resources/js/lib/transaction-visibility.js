export const TRANSACTION_VISIBILITY_STORAGE_KEY =
    'transactions.visibilityFilter';

export function readTransactionVisibility() {
    if (typeof window === 'undefined') {
        return 'active';
    }

    const storedValue = window.localStorage.getItem(
        TRANSACTION_VISIBILITY_STORAGE_KEY,
    );

    if (
        storedValue === 'active' ||
        storedValue === 'deleted' ||
        storedValue === 'all'
    ) {
        return storedValue;
    }

    return 'active';
}

export function persistTransactionVisibility(value) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(TRANSACTION_VISIBILITY_STORAGE_KEY, value);
}
