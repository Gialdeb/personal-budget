export const TRANSACTION_FILTER_STORAGE_KEY = 'soamco:transactions:filters:v1';
export const transactionFilterDefaults = {
    transfer: true,
    day: '',
    income: true,
    expense: true,
    opening: true,
    refunded: false,
    shared: true,
    deleted: false,
    recurring: true,
    macrogroup: 'all',
    category: 'all',
    account: 'all',
    search: '',
    advancedBy: 'all',
};
const booleanKeys = [
    'transfer',
    'income',
    'expense',
    'opening',
    'refunded',
    'shared',
    'deleted',
    'recurring',
];
const queryKeys = {
    transfer: 'tx_transfer',
    day: 'tx_day',
    income: 'tx_income',
    expense: 'tx_expense',
    opening: 'tx_opening',
    refunded: 'tx_refunded',
    shared: 'tx_shared',
    deleted: 'tx_deleted',
    recurring: 'tx_recurring',
    macrogroup: 'type',
    category: 'category_uuid',
    account: 'account_uuid',
    search: 'search',
    advancedBy: 'tx_advanced_by',
};

export function normalizeTransactionFilters(value) {
    const filters = { ...transactionFilterDefaults };

    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return filters;
    }

    for (const key of booleanKeys) {
        if (typeof value[key] === 'boolean') {
            filters[key] = value[key];
        }
    }

    for (const key of [
        'macrogroup',
        'category',
        'account',
        'search',
        'advancedBy',
    ]) {
        if (typeof value[key] === 'string' && value[key].length <= 1000) {
            filters[key] = value[key];
        }
    }

    if (
        typeof value.day === 'string' &&
        /^\d{4}-\d{2}-\d{2}$/.test(value.day)
    ) {
        filters.day = value.day;
    }

    return filters;
}

export function readTransactionFilterPreference(
    storage,
    key = TRANSACTION_FILTER_STORAGE_KEY,
) {
    try {
        const saved = JSON.parse(storage?.getItem(key) ?? 'null');

        return saved?.remember === true
            ? normalizeTransactionFilters(saved.filters)
            : null;
    } catch {
        return null;
    }
}

export function storeTransactionFilterPreference(
    storage,
    filters,
    remember,
    key = TRANSACTION_FILTER_STORAGE_KEY,
) {
    try {
        if (!storage) {
            return !remember;
        }

        if (remember) {
            storage?.setItem(
                key,
                JSON.stringify({
                    remember: true,
                    filters: normalizeTransactionFilters(filters),
                }),
            );
        } else {
            storage?.removeItem(key);
        }

        return true;
    } catch {
        return false;
    }
}

export function transactionFiltersFromUrl(url) {
    const params = new URL(url, 'https://transactions.local').searchParams;

    if (
        !params.has('tx_filters') &&
        !Object.values(queryKeys).some((key) => params.has(key))
    ) {
        return null;
    }

    const filters = { ...transactionFilterDefaults };

    for (const [key, query] of Object.entries(queryKeys)) {
        if (!params.has(query)) {
            continue;
        }

        const value = params.get(query);

        if (booleanKeys.includes(key)) {
            if (value === '0' || value === '1') {
                filters[key] = value === '1';
            }
        } else {
            filters[key] = value;
        }
    }

    return normalizeTransactionFilters(filters);
}

export function resolveTransactionFilters(url, saved) {
    const filters =
        transactionFiltersFromUrl(url) ?? normalizeTransactionFilters(saved);
    const period = new URL(url, 'https://transactions.local').pathname.match(
        /^\/transactions\/(\d{4})\/(\d{1,2})(?:\/|$)/,
    );

    if (
        period &&
        filters.day &&
        !filters.day.startsWith(`${period[1]}-${period[2].padStart(2, '0')}-`)
    ) {
        filters.day = '';
    }

    return filters;
}

export function transactionFilterUrl(url, filters) {
    const parsed = new URL(url, 'https://transactions.local');
    const normalized = normalizeTransactionFilters(filters);

    for (const [key, query] of Object.entries(queryKeys)) {
        parsed.searchParams.delete(query);

        if (normalized[key] !== transactionFilterDefaults[key]) {
            parsed.searchParams.set(
                query,
                booleanKeys.includes(key)
                    ? normalized[key]
                        ? '1'
                        : '0'
                    : normalized[key],
            );
        }
    }

    parsed.searchParams.set('tx_filters', '1');

    return parsed.pathname + parsed.search + parsed.hash;
}

export function transactionFiltersAreCustomized(filters) {
    return Object.keys(transactionFilterDefaults).some(
        (key) => filters[key] !== transactionFilterDefaults[key],
    );
}

export function clearTransactionFilterUrl(url) {
    const parsed = new URL(url, 'https://transactions.local');

    for (const query of [...Object.values(queryKeys), 'tx_filters']) {
        parsed.searchParams.delete(query);
    }

    return parsed.pathname + parsed.search + parsed.hash;
}
