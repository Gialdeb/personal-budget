export type TransactionNavigationMonth = {
    value: number;
    label: string;
    full_label: string;
    count: number;
    has_data: boolean;
    is_selected: boolean;
    href: string;
};

export type TransactionNavigationContext = {
    year: number;
    month: number | null;
    active_year: number | null;
    available_years: number[];
    is_month_selected: boolean;
    period_label: string;
};

export type TransactionNavigationSummary = {
    records_count: number;
    status: string;
    status_tone: 'data' | 'empty';
    records_label: string;
    coverage_months_count: number;
    coverage_total_months: number;
    coverage_percentage: number;
    coverage_label: string;
    last_recorded_at: string | null;
    period_end_at: string;
};

export type TransactionsNavigation = {
    enabled: boolean;
    context: TransactionNavigationContext;
    months: TransactionNavigationMonth[];
    summary: TransactionNavigationSummary;
};

export type TransactionsPageData = {
    year: number;
    month: number;
    month_label: string;
    period_label: string;
    records_count: number;
    last_recorded_at: string | null;
};

export type TransactionsPageProps = {
    transactionsPage: TransactionsPageData;
};

export type MonthlyTransactionSheetSummaryCard = {
    key: string;
    label: string;
    actual_raw: number;
    budgeted_raw: number;
    variance_raw: number;
    variance_percentage: number | null;
};

export type MonthlyTransactionSheetOption = {
    value: string;
    uuid?: string | null;
    label: string;
};

export type MonthlyTransactionSheetTrackedItemOption = MonthlyTransactionSheetOption & {
    group_keys?: string[];
    category_uuids?: string[];
};

export type MonthlyTransactionSheetTransaction = {
    uuid: string;
    date: string | null;
    date_label: string | null;
    type: string;
    type_key: string;
    is_transfer: boolean;
    direction: string | null;
    direction_label: string | null;
    category_uuid: string | null;
    category_label: string;
    category_path: string;
    description: string | null;
    detail: string | null;
    notes: string | null;
    account_uuid: string | null;
    account_label: string;
    related_transaction_uuid: string | null;
    related_account_uuid: string | null;
    related_account_label: string | null;
    tracked_item_uuid: string | null;
    tracked_item_label: string | null;
    amount_value_raw: number;
    amount_raw: number;
    balance_after_raw: number | null;
    status: string | null;
    source_type: string | null;
};

export type MonthlyTransactionSheetEditorAccountOption = {
    value: string;
    uuid: string;
    label: string;
    currency: string;
};

export type MonthlyTransactionSheetEditorCategoryOption = {
    value: string;
    uuid: string;
    label: string;
    type_key: string;
    direction_type: string | null;
    group_type: string | null;
    is_active: boolean;
    ancestor_uuids: string[];
};

export type MonthlyTransactionSheetOverviewItem = {
    key: string;
    label: string;
    actual_raw: number;
    budget_raw: number;
    progress_percentage: number;
    remaining_raw: number;
    excess_raw: number;
    count: number;
    uuid?: string;
    group_key?: string;
};

export type MonthlyTransactionSheetRow = {
    uuid: string;
    parent_uuid: string | null;
    name: string;
    full_path: string;
    depth: number;
    group_type: string | null;
    direction_type: string | null;
    icon: string | null;
    color: string | null;
    is_active: boolean;
    is_selectable: boolean;
    has_children: boolean;
    ancestor_uuids: string[];
    actual_income_raw: number;
    actual_expense_raw: number;
    actual_net_raw: number;
    budgeted_amount_raw: number;
    variance_raw: number;
    transaction_count: number;
    direct_income_raw: number;
    direct_expense_raw: number;
    children: MonthlyTransactionSheetRow[];
};

export type MonthlyTransactionSheetSection = {
    key: string;
    label: string;
    description: string;
    rows: MonthlyTransactionSheetRow[];
    flat_rows: MonthlyTransactionSheetRow[];
    totals: {
        income: number;
        expense: number;
        net: number;
        budget: number;
        variance: number;
        count: number;
    };
};

export type MonthlyTransactionSheetData = {
    filters: {
        year: number;
        month: number;
        available_years: Array<{ value: number; label: string }>;
        group_options: Array<{ value: string; label: string }>;
        category_options: MonthlyTransactionSheetOption[];
        account_options: MonthlyTransactionSheetOption[];
    };
    settings: {
        active_year: number | null;
        base_currency: string;
    };
    period: {
        year: number;
        month: number;
        month_label: string;
        is_current_month: boolean;
    };
    summary_cards: MonthlyTransactionSheetSummaryCard[];
    transactions: MonthlyTransactionSheetTransaction[];
    editor: {
        can_edit: boolean;
        group_options: MonthlyTransactionSheetOption[];
        accounts: MonthlyTransactionSheetEditorAccountOption[];
        categories: MonthlyTransactionSheetEditorCategoryOption[];
        tracked_items: MonthlyTransactionSheetTrackedItemOption[];
    };
    overview: {
        groups: MonthlyTransactionSheetOverviewItem[];
        categories: MonthlyTransactionSheetOverviewItem[];
    };
    sections: MonthlyTransactionSheetSection[];
    totals: {
        actual_income_raw: number;
        actual_expense_raw: number;
        budgeted_income_raw: number;
        budgeted_expense_raw: number;
        net_actual_raw: number;
        net_budgeted_raw: number;
    };
    meta: {
        year_is_closed: boolean;
        closed_year_message: string | null;
        transactions_count: number;
        last_balance_raw: number | null;
        last_recorded_at: string | null;
        has_budget_data: boolean;
    };
};

export type MonthlyTransactionSheetPageProps = {
    monthlySheet: MonthlyTransactionSheetData;
    year: number;
    month: number;
};
