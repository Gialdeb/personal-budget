export type EntrySearchScope = 'all' | 'transactions' | 'recurring';

export type EntrySearchResultKind = 'transaction' | 'recurring';

export type EntrySearchFilterOption = {
    value: string;
    label: string;
    full_path?: string;
    icon?: string | null;
    color?: string | null;
    ancestor_uuids?: string[];
    is_selectable?: boolean;
};

export type EntrySearchState = {
    q: string;
    scope: EntrySearchScope;
    acrossMonths: boolean;
    accountUuid: string | null;
    categoryUuid: string | null;
    direction: string | null;
    amountMin: string;
    amountMax: string;
    withNotes: boolean;
    withReference: boolean;
    recurringStatus: string | null;
};

export type EntrySearchResultItem = {
    id: string;
    kind: EntrySearchResultKind;
    title: string;
    subtitle: string | null;
    amount: number | null;
    currency_code: string | null;
    date: string;
    month_key: string;
    month_start: string;
    target_url: string;
    highlight_key: string;
};

export type EntrySearchMonthGroup = {
    month_key: string;
    month_start: string;
    items: EntrySearchResultItem[];
};

export type EntrySearchResponse = {
    filters: Record<string, unknown>;
    total_results: number;
    groups: EntrySearchMonthGroup[];
};

export type EntrySearchSharedData = {
    account_options: EntrySearchFilterOption[];
    category_options: EntrySearchFilterOption[];
};
