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
    income_total: number;
    expense_total: number;
    net_total: number;
    budget_total: number;
    current_balance_total: number;
    previous_balance_total: number;
    actual_vs_budget_delta: number;
    transactions_count: number;
    active_accounts_count: number;
    savings_rate: number;
    savings_mode: string;
};

export type DashboardTrendPoint = {
    label: number;
    income_total: number;
    expense_total: number;
    net_total: number;
};

export type DashboardCategoryBreakdownItem = {
    category_id: number | null;
    category_name: string;
    total_amount: number;
};

export type DashboardBudgetComparisonItem = {
    category_id: number | null;
    scope_id: number | null;
    category_name: string;
    scope_name: string;
    budget_total: number;
    actual_total: number;
    delta: number;
    percentage_used: number;
};

export type DashboardAccountSummaryItem = {
    account_id: number;
    account_name: string;
    bank_name: string | null;
    currency: string;
    opening_balance: number;
    current_balance: number;
    income_total: number;
    expense_total: number;
    net_total: number;
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
    overdue_total: number;
};

export type DashboardScheduledUpcomingItem = {
    id: number;
    title: string;
    scheduled_date: string;
    expected_amount: number;
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
    total_amount: number;
    transactions_count: number;
};

export type DashboardNotificationSummary = {
    review_needed_count: number;
    overdue_recurring_count: number;
    overdue_recurring_total: number;
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
