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
    kind: string | null;
    kind_label: string | null;
    is_opening_balance: boolean;
    is_deleted: boolean;
    deleted_at: string | null;
    is_projected_recurring: boolean;
    is_recurring_transaction: boolean;
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
    recurring_occurrence_uuid: string | null;
    recurring_entry_uuid: string | null;
    recurring_entry_show_url: string | null;
    amount_value_raw: number;
    amount_raw: number;
    balance_after_raw: number | null;
    status: string | null;
    source_type: string | null;
    can_edit: boolean;
    can_delete: boolean;
    can_restore: boolean;
    can_force_delete: boolean;
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
    deleted_transactions: MonthlyTransactionSheetTransaction[];
    planned_occurrences: MonthlyTransactionSheetTransaction[];
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
        deleted_transactions_count: number;
        planned_occurrences_count: number;
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

export type RecurringEntryIndexCard = {
    uuid: string;
    show_url: string;
    title: string;
    description: string | null;
    notes: string | null;
    currency: string | null;
    entry_type: string | null;
    direction: string | null;
    status: string | null;
    expected_amount: number | null;
    total_amount: number | null;
    installments_count: number | null;
    recurrence_type: string | null;
    recurrence_interval: number | null;
    recurrence_rule: Record<string, unknown> | null;
    start_date: string | null;
    end_date: string | null;
    end_mode: string | null;
    next_occurrence_date: string | null;
    occurrences_limit: number | null;
    auto_generate_occurrences: boolean;
    auto_create_transaction: boolean;
    is_active: boolean;
    scope: {
        uuid: string;
        name: string;
    } | null;
    account: {
        uuid: string;
        name: string;
        currency: string | null;
    } | null;
    category: {
        uuid: string;
        name: string;
    } | null;
    tracked_item: {
        uuid: string;
        name: string;
    } | null;
    merchant: {
        uuid: string;
        name: string;
    } | null;
    stats: {
        total_occurrences: number;
        pending_occurrences: number;
        converted_occurrences: number;
        remaining_occurrences: number;
        remaining_amount: number | null;
    };
};

export type RecurringLinkedTransaction = {
    uuid: string;
    kind: string | null;
    transaction_date: string | null;
    amount: number;
    currency: string | null;
    show_url?: string | null;
    is_refunded?: boolean;
    can_refund?: boolean;
    refund_transaction?: {
        uuid: string;
        transaction_date: string | null;
        show_url: string | null;
    } | null;
};

export type RecurringMonthlyOccurrence = {
    uuid: string;
    sequence_number: number;
    status: string | null;
    expected_date: string | null;
    due_date: string | null;
    display_date: string;
    expected_amount: number | null;
    currency: string | null;
    notes: string | null;
    direction: string | null;
    entry_type: string | null;
    title: string | null;
    description: string | null;
    can_convert: boolean;
    converted_transaction: RecurringLinkedTransaction | null;
    recurring_entry: {
        uuid: string;
        title: string;
        status: string | null;
        auto_create_transaction: boolean;
        show_url: string;
        account: {
            uuid: string;
            name: string;
            currency: string | null;
        } | null;
        category: {
            uuid: string;
            name: string;
        } | null;
        tracked_item: {
            uuid: string;
            name: string;
        } | null;
    } | null;
};

export type RecurringMonthlyCalendarDay = {
    date: string;
    anchor: string;
    income_total: number;
    expense_total: number;
    occurrences_count: number;
    pending_count: number;
    converted_count: number;
    occurrences: RecurringMonthlyOccurrence[];
};

export type RecurringMonthlyCalendar = {
    year: number;
    month: number;
    month_label: string;
    period_label: string;
    starts_at: string;
    ends_at: string;
    summary: {
        entries_count: number;
        occurrences_count: number;
        pending_count: number;
        converted_count: number;
        planned_income_total: number;
        planned_expense_total: number;
    };
    days: RecurringMonthlyCalendarDay[];
};

export type RecurringEntriesPagePeriod = {
    year: number;
    month: number;
    month_label: string;
    period_label: string;
    starts_at: string;
    ends_at: string;
};

export type RecurringFormOption = {
    value: string;
    uuid?: string;
    label: string;
    currency?: string | null;
    direction_type?: string | null;
};

export type RecurringEntryFormOptions = {
    accounts: RecurringFormOption[];
    scopes: RecurringFormOption[];
    categories: RecurringFormOption[];
    tracked_items: RecurringFormOption[];
    merchants: RecurringFormOption[];
    directions: RecurringFormOption[];
    entry_types: RecurringFormOption[];
    statuses: RecurringFormOption[];
    end_modes: RecurringFormOption[];
    recurrence_types: RecurringFormOption[];
};

export type RecurringEntriesIndexPageProps = {
    recurringEntries: RecurringEntryIndexCard[];
    filters: Record<string, unknown>;
    activePeriod: RecurringEntriesPagePeriod;
    monthlyCalendar: RecurringMonthlyCalendar;
    formOptions: RecurringEntryFormOptions;
};

export type RecurringEntryShowPayload = {
    entry: RecurringEntryIndexCard;
    occurrences: Array<{
        uuid: string;
        sequence_number: number;
        expected_date: string | null;
        due_date: string | null;
        expected_amount: number | null;
        status: string | null;
        notes: string | null;
        can_convert: boolean;
        can_skip: boolean;
        can_cancel: boolean;
        can_undo_conversion: boolean;
        converted_transaction: RecurringLinkedTransaction | null;
    }>;
    summary: {
        total_occurrences: number;
        pending_occurrences: number;
        converted_occurrences: number;
        converted_amount: number;
        remaining_amount: number;
    };
    actions: {
        can_pause: boolean;
        can_resume: boolean;
        can_cancel: boolean;
        has_converted_occurrences: boolean;
    };
};

export type RecurringEntryShowPageProps = {
    recurringEntry: RecurringEntryShowPayload;
    formOptions: RecurringEntryFormOptions;
};
