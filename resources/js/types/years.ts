export type UserYearSuggestion = {
    next_year: number;
    current_year: number;
    title: string;
    message: string;
};

export type UserYearItem = {
    id: number;
    year: number;
    is_closed: boolean;
    is_active: boolean;
    counts: {
        budgets: number;
        transactions: number;
        scheduled_entries: number;
        recurring_occurrences: number;
        recurring_entries: number;
    };
    usage_count: number;
    used: boolean;
    is_deletable: boolean;
};

export type UserYearsData = {
    data: UserYearItem[];
    summary: {
        total_count: number;
        open_count: number;
        closed_count: number;
        used_count: number;
        active_year: number | null;
    };
    meta: {
        next_year: number;
        current_calendar_year: number;
    };
};

export type YearsPageProps = {
    years: UserYearsData;
};
