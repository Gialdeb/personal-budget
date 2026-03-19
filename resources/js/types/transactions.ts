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
