export const PLANNED_RECURRING_VISIBILITY_STORAGE_KEY =
    'transactions.showPlannedRecurring';

export function readPlannedRecurringVisibility() {
    if (typeof window === 'undefined') {
        return false;
    }

    const storedValue = window.localStorage.getItem(
        PLANNED_RECURRING_VISIBILITY_STORAGE_KEY,
    );

    if (storedValue === null) {
        return false;
    }

    return storedValue === 'true';
}

export function persistPlannedRecurringVisibility(value) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        PLANNED_RECURRING_VISIBILITY_STORAGE_KEY,
        String(value),
    );
}
