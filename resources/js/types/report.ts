import type {
    DashboardAccountFilterOption,
    DashboardOption,
} from './dashboard';

export type ReportLauncherSection = {
    key: string;
    title: string;
    summary: string;
    status: string;
    href: string;
};

export type ReportContext = {
    year: number;
    month: number | null;
};

export type ReportLauncherPageProps = {
    reportContext: ReportContext;
    reportSections: ReportLauncherSection[];
};

export type ReportSectionPageProps = ReportLauncherPageProps & {
    activeReportSection: ReportLauncherSection;
};

export type ReportPeriodFilterValue =
    | 'annual'
    | 'monthly'
    | 'last_3_months'
    | 'last_6_months'
    | 'ytd';

export type ReportFilterOption<TValue = string> = {
    value: TValue;
    label: string;
};

export type ReportOverviewBucket = {
    key: string;
    label: string;
    income_total: string;
    income_total_raw: number;
    expense_total: string;
    expense_total_raw: number;
    net_total: string;
    net_total_raw: number;
};

export type ReportOverviewFilters = {
    year: number;
    month: number | null;
    period: ReportPeriodFilterValue;
    account_uuid: string | null;
    available_years: DashboardOption<number>[];
    month_options: DashboardOption<number>[];
    period_options: ReportFilterOption<ReportPeriodFilterValue>[];
    account_options: DashboardAccountFilterOption[];
    show_month_filter: boolean;
};

export type ReportOverviewKpis = {
    income_total: string;
    income_total_raw: number;
    expense_total: string;
    expense_total_raw: number;
    net_total: string;
    net_total_raw: number;
    transactions_count: number;
    average_net: string;
    average_net_raw: number;
    average_net_interval_label: string;
    best_period_label: string | null;
    best_period_value: string | null;
    best_period_value_raw: number | null;
    worst_period_label: string | null;
    worst_period_value: string | null;
    worst_period_value_raw: number | null;
};

export type ReportMetricMoneyComparison = {
    previous_raw: number;
    previous_formatted: string;
    delta_raw: number;
    delta_formatted: string;
    delta_percentage: number | null;
    delta_percentage_label: string | null;
    direction: 'up' | 'down' | 'neutral';
};

export type ReportMetricCountComparison = {
    previous_raw: number;
    delta_raw: number;
    delta_label: string;
    delta_percentage: number | null;
    delta_percentage_label: string | null;
    direction: 'up' | 'down' | 'neutral';
};

export type ReportOverviewChartData = {
    labels: string[];
    income_values: number[];
    expense_values: number[];
    net_values: number[];
    granularity?: 'day' | 'month';
};

export type ReportOverviewData = {
    currency: string;
    meta: {
        period_label: string;
        scope_label: string;
        granularity: 'day' | 'month';
        previous_period_label: string;
        unresolved_transactions_count: number;
        coverage_note: string | null;
    };
    filters: ReportOverviewFilters;
    kpis: ReportOverviewKpis & {
        income_total_comparison: ReportMetricMoneyComparison;
        expense_total_comparison: ReportMetricMoneyComparison;
        net_total_comparison: ReportMetricMoneyComparison;
        transactions_count_comparison: ReportMetricCountComparison;
        average_net_comparison: ReportMetricMoneyComparison;
    };
    trend: ReportOverviewChartData;
    comparison: ReportOverviewChartData;
    buckets: ReportOverviewBucket[];
};

export type ReportOverviewPageProps = ReportSectionPageProps & {
    reportOverview: ReportOverviewData;
};

export type ReportCategoryFocusValue = 'all' | 'income' | 'expense' | 'saving';

export type ReportCategoryNode = {
    key: string;
    name: string;
    label: string;
    value: number;
    total: string;
    color: string;
    share_percentage: number;
    share_label: string;
    children_count: number;
    children: ReportCategoryNode[];
    itemStyle: {
        color: string;
    };
};

export type ReportCategoryTopItem = {
    key: string;
    label: string;
    total: string;
    total_raw: number;
    share_percentage: number;
    share_label: string;
    subcategories_count: number;
    color: string;
};

export type ReportCategoryTrendSeries = {
    key: string;
    name: string;
    color: string;
    values: number[];
    total: string;
};

export type ReportCategoryTrendData = {
    labels: string[];
    granularity: 'day' | 'month';
    series: ReportCategoryTrendSeries[];
};

export type ReportCategoryRecentTransaction = {
    uuid: string;
    date_label: string;
    description: string;
    category_label: string;
    amount: string;
    amount_raw: number;
    direction: string | null;
    color: string;
};

export type ReportCategoriesFilters = {
    year: number;
    month: number | null;
    period: ReportPeriodFilterValue;
    account_uuid: string | null;
    focus: ReportCategoryFocusValue;
    exclude_internal: boolean;
    available_years: DashboardOption<number>[];
    month_options: DashboardOption<number>[];
    period_options: ReportFilterOption<ReportPeriodFilterValue>[];
    account_options: DashboardAccountFilterOption[];
    focus_options: ReportFilterOption<ReportCategoryFocusValue>[];
    show_month_filter: boolean;
};

export type ReportCategoriesData = {
    currency: string;
    meta: {
        period_label: string;
        scope_label: string;
        focus_label: string;
        granularity: 'day' | 'month';
        unresolved_transactions_count: number;
    };
    filters: ReportCategoriesFilters;
    summary: {
        total_selected: string;
        total_selected_raw: number;
        categories_count: number;
        active_categories_count: number;
        main_category_label: string | null;
        main_category_total: string | null;
        main_category_share_label: string | null;
        top_subcategory_label: string | null;
    };
    composition: {
        sunburst_nodes: ReportCategoryNode[];
        treemap_nodes: ReportCategoryNode[];
    };
    top_categories: ReportCategoryTopItem[];
    trend: ReportCategoryTrendData;
    recent_transactions: ReportCategoryRecentTransaction[];
};

export type ReportCategoriesPageProps = ReportSectionPageProps & {
    reportCategories: ReportCategoriesData;
};

export type ReportAccountMetric = {
    value_raw: number;
    value: string;
    previous_raw: number;
    previous: string;
    delta_raw: number;
    delta: string;
    delta_percentage: number | null;
    delta_percentage_label: string | null;
    comparison_available: boolean;
};

export type ReportAccountCard = {
    uuid: string;
    name: string;
    bank_name: string | null;
    type_label: string;
    type_code: string | null;
    currency: string;
    color: string;
    initials: string;
    current_balance_raw: number;
    current_balance: string;
    opening_balance_raw: number;
    opening_balance: string;
    income_raw: number;
    income: string;
    expense_raw: number;
    expense: string;
    net_raw: number;
    net: string;
    share_percentage: number;
    share_label: string;
    previous_balance_raw: number;
    delta_percentage: number | null;
    delta_label: string | null;
    period_delta_raw: number;
    sparkline: number[];
};

export type ReportAccountBalanceSeries = {
    uuid: string;
    name: string;
    color: string;
    values: number[];
    current: string;
};

export type ReportAccountCashFlow = {
    labels: string[];
    income_values: number[];
    expense_values: number[];
    has_data: boolean;
};

export type ReportAccountDistributionItem = {
    uuid: string;
    name: string;
    color: string;
    value_raw: number;
    value: string;
    share_percentage: number;
    share_label: string;
};

export type ReportAccountTopCategory = {
    label: string;
    color: string;
    total_raw: number;
    total: string;
};

export type ReportAccountRecentTransaction = {
    uuid: string;
    date_label: string;
    description: string;
    category_label: string;
    amount_raw: number;
    amount: string;
    color: string;
    direction: string | null;
};

export type ReportAccountsData = {
    currency: string;
    meta: {
        period_label: string;
        scope_label: string;
        previous_period_label: string;
        granularity: 'day' | 'month';
    };
    filters: {
        year: number;
        month: number | null;
        period: ReportPeriodFilterValue;
        account_uuid: string | null;
        available_years: DashboardOption<number>[];
        month_options: DashboardOption<number>[];
        period_options: ReportFilterOption<ReportPeriodFilterValue>[];
        account_options: DashboardAccountFilterOption[];
        show_month_filter: boolean;
    };
    summary: {
        total_balance: string;
        total_balance_raw: number;
        active_accounts_count: number;
        selected_account_uuid: string | null;
        selected_account_name: string | null;
        selected_account_type: string | null;
        selected_account_balance: string;
        selected_account_balance_raw: number;
        selected_account_share_label: string;
        selected_account_opening_balance: string;
    };
    kpis: {
        income: ReportAccountMetric;
        expense: ReportAccountMetric;
        net: ReportAccountMetric;
        best_period: {
            label: string | null;
            value_raw: number;
            value: string | null;
            summary: string | null;
            worst_label: string | null;
            worst_value_raw: number;
            worst_value: string | null;
        };
    };
    accounts: ReportAccountCard[];
    balance_trend: {
        labels: string[];
        granularity: 'day' | 'month';
        selected_account_uuid: string | null;
        series: ReportAccountBalanceSeries[];
    };
    cash_flow: ReportAccountCashFlow;
    distribution: ReportAccountDistributionItem[];
    top_categories: ReportAccountTopCategory[];
    recent_transactions: ReportAccountRecentTransaction[];
    comparison_rows: ReportAccountCard[];
};

export type ReportAccountsPageProps = ReportSectionPageProps & {
    reportAccounts: ReportAccountsData;
};
