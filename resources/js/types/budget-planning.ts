import type { UserYearSuggestion } from './years';

export type BudgetPlanningOption<TValue = number | string> = {
    value: TValue;
    label: string;
};

export type BudgetPlanningMonth = {
    value: number;
    label: string;
    short_label: string;
};

export type BudgetPlanningSummaryCard = {
    key: string;
    label: string;
    amount_raw: number;
    share_of_income: number | null;
};

export type BudgetPlanningRow = {
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
    is_editable: boolean;
    has_children: boolean;
    budget_type: string;
    ancestor_uuids: string[];
    monthly_amounts_raw: number[];
    row_total_raw: number;
    direct_budget_total_raw: number;
    children: BudgetPlanningRow[];
};

export type BudgetPlanningSection = {
    key: string;
    label: string;
    description: string;
    rows: BudgetPlanningRow[];
    flat_rows: Omit<BudgetPlanningRow, 'children'>[];
    totals_by_month_raw: number[];
    total_raw: number;
};

export type BudgetPlanningFilters = {
    year: number;
    available_years: BudgetPlanningOption<number>[];
    group_options: BudgetPlanningOption<string>[];
};

export type BudgetPlanningSettings = {
    active_year: number | null;
    base_currency: string;
};

export type BudgetPlanningMeta = {
    copy_previous_year_available: boolean;
    previous_year: number;
    selectable_rows_count: number;
    parent_budget_conflicts: {
        uuid: string;
        name: string;
        full_path: string;
        section_key: string;
        section_label: string;
        direct_budget_total_raw: number;
    }[];
    year_is_closed: boolean;
    closed_year_message: string | null;
    year_suggestion: UserYearSuggestion | null;
};

export type BudgetPlanningData = {
    filters: BudgetPlanningFilters;
    settings: BudgetPlanningSettings;
    months: BudgetPlanningMonth[];
    summary_cards: BudgetPlanningSummaryCard[];
    sections: BudgetPlanningSection[];
    column_totals_raw: number[];
    grand_total_raw: number;
    meta: BudgetPlanningMeta;
};

export type BudgetPlanningSavedCell = {
    category_uuid: string;
    year: number;
    month: number;
    amount_raw: number;
    budget_type: string;
};

export type BudgetPlanningPageProps = {
    budgetPlanning: BudgetPlanningData;
};

export type BudgetCellSaveState = 'idle' | 'saving' | 'saved' | 'error';
