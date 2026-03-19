export type DashboardOption<TValue = number | null> = {
    value: TValue;
    label: string;
};

export type DashboardSettings = {
    active_year: number | null;
    base_currency: string;
    dashboard: Record<string, unknown>;
};

export type DashboardOverview = {
    income_total: string;
    income_total_raw: number;
    expense_total: string;
    expense_total_raw: number;
    net_total: string;
    net_total_raw: number;
    budget_total: string;
    budget_total_raw: number;
    current_balance_total: string;
    current_balance_total_raw: number;
    previous_balance_total: string;
    previous_balance_total_raw: number;
    actual_vs_budget_delta: string;
    actual_vs_budget_delta_raw: number;
    transactions_count: number;
    active_accounts_count: number;
    savings_rate: number;
    savings_mode: string;
};

export type DashboardTrendPoint = {
    label: number;
    income_total: string;
    income_total_raw: number;
    expense_total: string;
    expense_total_raw: number;
    net_total: string;
    net_total_raw: number;
};

export type DashboardCategoryBreakdownItem = {
    category_id: number | null;
    category_name: string;
    total_amount: string;
    total_amount_raw: number;
};

export type DashboardBudgetComparisonItem = {
    category_id: number | null;
    scope_id: number | null;
    category_name: string;
    scope_name: string;
    budget_total: string;
    budget_total_raw: number;
    actual_total: string;
    actual_total_raw: number;
    delta: string;
    delta_raw: number;
    percentage_used: number;
};

export type DashboardParentCategoryBudgetItem = {
    category_id: number;
    category_name: string;
    budget_total: string;
    budget_total_raw: number;
    actual_total: string;
    actual_total_raw: number;
    delta: string;
    delta_raw: number;
    percentage_used: number;
};

export type DashboardAccountSummaryItem = {
    account_id: number;
    account_name: string;
    bank_name: string | null;
    currency: string;
    opening_balance: string;
    opening_balance_raw: number;
    current_balance: string;
    current_balance_raw: number;
    income_total: string;
    income_total_raw: number;
    expense_total: string;
    expense_total_raw: number;
    net_total: string;
    net_total_raw: number;
    transactions_count: number;
};

export type DashboardRecurringSummary = {
    planned_count: number;
    due_count: number;
    matched_count: number;
    converted_count: number;
    cancelled_count: number;
    skipped_count: number;
    overdue_count: number;
    overdue_total: string;
    overdue_total_raw: number;
};

export type DashboardScheduledUpcomingItem = {
    id: number;
    title: string;
    scheduled_date: string;
    expected_amount: string;
    expected_amount_raw: number;
    status: string;
};

export type DashboardScheduledSummary = {
    planned_count: number;
    due_count: number;
    matched_count: number;
    converted_count: number;
    cancelled_count: number;
    upcoming: DashboardScheduledUpcomingItem[];
};

export type DashboardMerchantBreakdownItem = {
    merchant_id: number | null;
    merchant_name: string;
    total_amount: string;
    total_amount_raw: number;
    transactions_count: number;
};

export type DashboardNotificationSummary = {
    review_needed_count: number;
    overdue_recurring_count: number;
    overdue_recurring_total: string;
    overdue_recurring_total_raw: number;
    planned_scheduled_count: number;
    due_scheduled_count: number;
};

export type DashboardFilters = {
    year: number;
    month: number | null;
    available_years: DashboardOption<number>[];
    month_options: DashboardOption[];
};

export type DashboardData = {
    filters: DashboardFilters;
    settings: DashboardSettings;
    overview: DashboardOverview;
    monthly_trend: DashboardTrendPoint[];
    expense_by_category: DashboardCategoryBreakdownItem[];
    budget_vs_actual: DashboardBudgetComparisonItem[];
    parent_category_budget_status: DashboardParentCategoryBudgetItem[];
    accounts_summary: DashboardAccountSummaryItem[];
    recurring_summary: DashboardRecurringSummary;
    scheduled_summary: DashboardScheduledSummary;
    income_by_category: DashboardCategoryBreakdownItem[];
    merchant_breakdown: DashboardMerchantBreakdownItem[];
    notifications: DashboardNotificationSummary;
};

export type DashboardPageProps = {
    dashboard: DashboardData;
};
