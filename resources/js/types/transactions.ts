import type { TransactionTemplatePrefill } from './transaction-templates';

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
    create_only?: boolean;
    bank_name?: string | null;
    account_type_code?: string | null;
    is_owned?: boolean;
    is_shared?: boolean;
    membership_role?: string | null;
    membership_status?: string | null;
    can_edit?: boolean;
};

export type MonthlyTransactionSheetTrackedItemOption =
    MonthlyTransactionSheetOption & {
        name?: string;
        owner_user_id?: number;
        group_keys?: string[];
        category_uuids?: string[];
        logo_url?: string | null;
    };

export type MonthlyTransactionSheetScopeOption =
    MonthlyTransactionSheetOption & {
        owner_user_id?: number;
    };

export type MonthlyTransactionSheetTransaction = {
    is_shared_ledger?: boolean;
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
    scope_uuid: string | null;
    scope_label: string | null;
    tracked_item_uuid: string | null;
    tracked_item_label: string | null;
    tracked_item_logo_url: string | null;
    shared_expense_counterparty_uuid?: string | null;
    shared_expense_counterparty_label?: string | null;
    shared_expense_paid_by?: 'self' | 'counterparty' | null;
    shared_expense_my_share?: string | null;
    shared_expense_counterparty_share?: string | null;
    is_credit_card_transaction?: boolean;
    credit_card_cycle_end_date?: string | null;
    credit_card_payment_due_date?: string | null;
    recurring_occurrence_uuid: string | null;
    recurring_entry_uuid: string | null;
    recurring_payment_links?: Array<{
        occurrence_uuid: string;
        entry_uuid: string;
        title: string;
        due_date: string | null;
        show_url: string;
    }>;
    recurring_entry_show_url: string | null;
    is_credit_debt_transaction?: boolean;
    credit_debt_item_uuid?: string | null;
    credit_debt_item_type?: string | null;
    credit_debt_item_show_url?: string | null;
    currency_code: string | null;
    base_currency_code: string | null;
    converted_base_amount_raw: number | null;
    exchange_rate: string | null;
    exchange_rate_date: string | null;
    exchange_rate_source: string | null;
    is_multi_currency: boolean;
    amount_value_raw: number;
    amount_raw: number;
    balance_after_raw: number | null;
    status: string | null;
    source_type: string | null;
    is_reimbursement?: boolean;
    reimbursement_kind?:
        | 'transaction_refund'
        | 'shared_expense_settlement'
        | 'credit_debt_payment'
        | 'counterparty_payment'
        | 'counterparty_return'
        | null;
    is_refunded?: boolean;
    can_refund?: boolean;
    requires_payment_confirmation?: boolean;
    can_report_payment_problem?: boolean;
    can_undo_refund?: boolean;
    refund_transaction?: {
        uuid: string;
        transaction_date: string | null;
    } | null;
    refunded_transaction?: {
        uuid: string;
        transaction_date: string | null;
    } | null;
    created_at?: string | null;
    updated_at?: string | null;
    last_modified_at?: string | null;
    created_by?: MonthlyTransactionSheetAuditActor | null;
    updated_by?: MonthlyTransactionSheetAuditActor | null;
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
    account_type_code?: string | null;
    is_default?: boolean;
    owner_user_id?: number;
    category_contributor_user_ids?: number[];
    scope_contributor_user_ids?: number[];
    tracked_item_contributor_user_ids?: number[];
    bank_name?: string | null;
    is_owned?: boolean;
    is_shared?: boolean;
    uses_account_scoped_category_catalog?: boolean;
    membership_role?: string | null;
    membership_status?: string | null;
    can_edit?: boolean;
};

export type MonthlyTransactionSheetAuditActor = {
    uuid: string;
    name: string;
    email: string;
};

export type MonthlyTransactionSheetEditorCategoryOption = {
    id?: number;
    value: string;
    uuid: string;
    label: string;
    full_path?: string;
    slug?: string;
    account_uuid?: string;
    owner_user_id?: number;
    type_key: string;
    direction_type: string | null;
    group_type: string | null;
    icon?: string | null;
    color?: string | null;
    is_active: boolean;
    is_selectable?: boolean;
    sort_order?: number | null;
    ancestor_ids?: number[];
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
    shared_ledger_movements?: MonthlyTransactionSheetTransaction[];
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
        type_options: MonthlyTransactionSheetOption[];
        default_account_uuid: string | null;
        accounts: MonthlyTransactionSheetEditorAccountOption[];
        categories: Record<
            string,
            MonthlyTransactionSheetEditorCategoryOption[]
        >;
        category_overview_items: MonthlyTransactionSheetOverviewItem[];
        scopes: MonthlyTransactionSheetScopeOption[];
        tracked_items: MonthlyTransactionSheetTrackedItemOption[];
        shared_expense_counterparties: MonthlyTransactionSheetOption[];
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
        period_ending_balances: Array<{
            account_id: number;
            account_uuid: string;
            balance_raw: number;
            last_recorded_at: string | null;
        }>;
        has_budget_data: boolean;
    };
};

export type MonthlyTransactionSheetPageProps = {
    transaction_template?: TransactionTemplatePrefill | null;
    monthlySheet: MonthlyTransactionSheetData;
    year: number;
    month: number;
};

export type RecurringEntryIndexCard = {
    salary_url?: string | null;
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
    is_amount_variable: boolean;
    total_amount: number | null;
    installments_count: number | null;
    recurrence_type: string | null;
    recurrence_interval: number | null;
    recurrence_rule: Record<string, unknown> | null;
    start_date: string | null;
    trial_duration?: number | null;
    trial_unit?: 'days' | 'weeks' | 'months' | null;
    first_billing_date?: string;
    trial_active?: boolean;
    trial_locked?: boolean;
    end_date: string | null;
    end_mode: string | null;
    next_occurrence_date: string | null;
    occurrences_limit: number | null;
    auto_generate_occurrences: boolean;
    auto_create_transaction: boolean;
    is_active: boolean;
    reminder_days_before: number[];
    can_edit: boolean;
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
        logo_url: string | null;
    } | null;
    shared_expense_counterparty: {
        uuid: string;
        name: string;
    } | null;
    shared_expense_paid_by?: 'self' | 'counterparty' | null;
    shared_expense_my_share?: string | null;
    shared_expense_counterparty_share?: string | null;
    merchant: {
        uuid: string;
        name: string;
    } | null;
    stats: {
        total_occurrences: number;
        pending_occurrences: number;
        converted_occurrences: number;
        can_realign_refunded_schedule?: boolean;
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
    remaining_amount?: number;
    external_payment?: RecurringEntryShowPayload['occurrences'][number]['external_payment'];
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
    can_skip: boolean;
    can_restore: boolean;
    recurring_entry: {
        uuid: string;
        title: string;
        status: string | null;
        can_edit: boolean;
        auto_create_transaction: boolean;
        shared_expense_counterparty: {
            uuid: string;
            name: string;
        } | null;
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
            logo_url: string | null;
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
        installment_paid_totals: { currency: string; amount: number }[];
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
    logo_url?: string | null;
    value: string;
    uuid?: string;
    label: string;
    full_path?: string;
    bank_name?: string | null;
    slug?: string;
    account_uuid?: string;
    account_type_code?: string | null;
    is_default?: boolean;
    currency?: string | null;
    direction_type?: string | null;
    owner_user_id?: number;
    group_keys?: string[];
    category_uuids?: string[];
    ancestor_uuids?: string[];
    is_owned?: boolean;
    is_shared?: boolean;
    membership_role?: string | null;
    membership_status?: string | null;
    can_edit?: boolean;
    icon?: string | null;
    color?: string | null;
    is_selectable?: boolean;
    sort_order?: number | null;
    category_contributor_user_ids?: number[];
    scope_contributor_user_ids?: number[];
    tracked_item_contributor_user_ids?: number[];
    uses_account_scoped_catalog?: boolean;
};

export type RecurringEntryFormOptions = {
    default_account_uuid: string | null;
    accounts: RecurringFormOption[];
    filter_accounts: RecurringFormOption[];
    scopes: RecurringFormOption[];
    categories: Record<string, RecurringFormOption[]>;
    tracked_items: Record<string, RecurringFormOption[]>;
    shared_expense_counterparties: RecurringFormOption[];
    merchants: RecurringFormOption[];
    directions: RecurringFormOption[];
    entry_types: RecurringFormOption[];
    statuses: RecurringFormOption[];
    end_modes: RecurringFormOption[];
    recurrence_types: RecurringFormOption[];
    reminders: {
        max_custom_alerts: number;
        max_days_before: number;
    };
};

export type RecurringEntryDateOptions = {
    available_years: number[];
    min: string | null;
    today: string;
};

export type RecurringEntriesIndexPageProps = {
    recurringEntries: RecurringEntryIndexCard[];
    filters: Record<string, unknown>;
    activePeriod: RecurringEntriesPagePeriod;
    monthlyCalendar: RecurringMonthlyCalendar;
    formOptions: RecurringEntryFormOptions;
    dateOptions: RecurringEntryDateOptions;
};

export type RecurringEntryShowPayload = {
    occurrence_years: Array<{
        year: number;
        count: number;
        overdue_count: number;
        overdue_amount: number;
    }>;
    occurrence_page: {
        year: number;
        offset: number;
        next_offset: number | null;
    };

    entry: RecurringEntryIndexCard;
    amount_history: Array<{
        id: number;
        previous_amount: number;
        new_amount: number;
        currency: string;
        effective_from: string;
        previous_until: string;
        changed_at: string;
        difference: number;
        percentage: number | null;
    }>;
    occurrences: Array<{
        uuid: string;
        sequence_number: number;
        expected_date: string | null;
        due_date: string | null;
        has_date_override: boolean;
        resource: {
            uuid: string;
            name: string;
            currency: string | null;
            is_overridden: boolean;
        } | null;
        expected_amount: number | null;
        status: string | null;
        external_payment?: {
            uuid: string;
            payer: string | null;
            amount: number;
            date: string;
            currency: string;
            settled: boolean;
            show_url: string;
        } | null;
        settlement_status?:
            | 'settled'
            | 'partial'
            | 'due'
            | 'amount_confirmation'
            | null;
        allocated_amount?: number;
        can_register_payment: boolean;
        payment_history?: Array<{
            uuid: string;
            date: string;
            amount: number;
            show_url: string;
            refunded: boolean;
            refund_url: string | null;
        }>;
        payments: Array<{
            uuid: string;
            date: string;
            amount: number;
            classification: 'advance' | 'balance';
            account: string | null;
            reference: string | null;
            show_url: string;
        }>;
        remaining_amount?: number;
        automatic_posting_blocked_at?: string | null;
        payment_failed_at?: string | null;
        notes: string | null;
        can_update_amount: boolean;
        can_update_occurrence: boolean;
        can_convert: boolean;
        can_skip: boolean;
        can_restore: boolean;
        can_cancel: boolean;
        can_undo_conversion: boolean;
        converted_transaction: RecurringLinkedTransaction | null;
    }>;
    summary: {
        total_occurrences: number;
        pending_occurrences: number;
        converted_occurrences: number;
        settled_occurrences: number;
        converted_amount: number;
        remaining_amount: number;
        paid_count: number;
        paid_amount: number;
        paid_year: number | null;
    };
    actions: {
        can_pause: boolean;
        can_resume: boolean;
        can_cancel: boolean;
        can_delete: boolean;
        has_converted_occurrences: boolean;
    };
};

export type RecurringEntryShowPageProps = {
    transactionSheet?: MonthlyTransactionSheetData;
    recurringEntry: RecurringEntryShowPayload;
    formOptions: RecurringEntryFormOptions;
    dateOptions: RecurringEntryDateOptions;
};
