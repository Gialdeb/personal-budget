export const entrySearchQueryKeys = [
    'q',
    'scope',
    'across_months',
    'account_uuid',
    'category_uuid',
    'direction',
    'amount_min',
    'amount_max',
    'with_notes',
    'with_reference',
    'recurring_status',
];

export function defaultEntrySearchState(defaultScope = 'all') {
    return {
        q: '',
        scope: defaultScope,
        acrossMonths: defaultScope === 'all',
        accountUuid: null,
        categoryUuid: null,
        direction: null,
        amountMin: '',
        amountMax: '',
        withNotes: false,
        withReference: false,
        recurringStatus: null,
    };
}

export function parseEntrySearchState(search, defaultScope = 'all') {
    const params = new URLSearchParams(search);
    const base = defaultEntrySearchState(defaultScope);
    const scope = params.get('scope');

    return sanitizeEntrySearchStateForScope({
        ...base,
        q: params.get('q') ?? '',
        scope:
            scope === 'transactions' || scope === 'recurring' || scope === 'all'
                ? scope
                : defaultScope,
        acrossMonths: params.get('across_months') === '1',
        accountUuid: params.get('account_uuid') || null,
        categoryUuid: params.get('category_uuid') || null,
        direction: params.get('direction') || null,
        amountMin: params.get('amount_min') ?? '',
        amountMax: params.get('amount_max') ?? '',
        withNotes: params.get('with_notes') === '1',
        withReference: params.get('with_reference') === '1',
        recurringStatus: params.get('recurring_status') || null,
    });
}

export function buildEntrySearchQuery(state, defaultScope = 'all') {
    const normalizedState = sanitizeEntrySearchStateForScope(state);
    const query = {};

    if (normalizedState.q.trim() !== '') {
        query.q = normalizedState.q.trim();
    }

    if (normalizedState.scope !== defaultScope) {
        query.scope = normalizedState.scope;
    }

    if (normalizedState.acrossMonths) {
        query.across_months = '1';
    }

    if (normalizedState.accountUuid) {
        query.account_uuid = normalizedState.accountUuid;
    }

    if (normalizedState.categoryUuid) {
        query.category_uuid = normalizedState.categoryUuid;
    }

    if (normalizedState.direction) {
        query.direction = normalizedState.direction;
    }

    if (normalizedState.amountMin !== '') {
        query.amount_min = normalizedState.amountMin;
    }

    if (normalizedState.amountMax !== '') {
        query.amount_max = normalizedState.amountMax;
    }

    if (normalizedState.withNotes) {
        query.with_notes = '1';
    }

    if (normalizedState.withReference) {
        query.with_reference = '1';
    }

    if (normalizedState.recurringStatus) {
        query.recurring_status = normalizedState.recurringStatus;
    }

    return query;
}

export function countActiveEntrySearchFilters(state) {
    const normalizedState = sanitizeEntrySearchStateForScope(state);

    return [
        normalizedState.accountUuid,
        normalizedState.categoryUuid,
        normalizedState.direction,
        normalizedState.amountMin !== '' ? normalizedState.amountMin : null,
        normalizedState.amountMax !== '' ? normalizedState.amountMax : null,
        normalizedState.withNotes ? 'notes' : null,
        normalizedState.withReference ? 'reference' : null,
        normalizedState.recurringStatus,
    ].filter(Boolean).length;
}

export function hasEntrySearchCriteria(state) {
    const normalizedState = sanitizeEntrySearchStateForScope(state);

    return (
        normalizedState.q.trim() !== '' ||
        countActiveEntrySearchFilters(normalizedState) > 0
    );
}

export function replaceEntrySearchQueryInUrl(state, defaultScope = 'all') {
    const currentUrl = new URL(window.location.href);

    for (const key of entrySearchQueryKeys) {
        currentUrl.searchParams.delete(key);
    }

    const query = buildEntrySearchQuery(state, defaultScope);

    for (const [key, value] of Object.entries(query)) {
        currentUrl.searchParams.set(key, value);
    }

    return `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`;
}

export function sanitizeEntrySearchStateForScope(state) {
    if (state.scope === 'recurring') {
        return state;
    }

    return {
        ...state,
        recurringStatus: null,
    };
}
