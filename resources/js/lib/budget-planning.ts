import { messages } from '@/i18n/messages';
import { getCurrentLocale } from '@/lib/locale';
import type {
    BudgetPlanningData,
    BudgetPlanningRow,
    BudgetPlanningSection,
} from '@/types';

export function cloneBudgetPlanningData(
    data: BudgetPlanningData,
): BudgetPlanningData {
    return JSON.parse(JSON.stringify(data)) as BudgetPlanningData;
}

export function applyBudgetCellUpdate(
    data: BudgetPlanningData,
    categoryUuid: string,
    month: number,
    amount: number,
): void {
    const monthIndex = month - 1;

    data.sections = data.sections.map((section) =>
        recalculateSection(section, categoryUuid, monthIndex, amount),
    );
    data.column_totals_raw = buildColumnTotals(data.sections);
    data.grand_total_raw = round(sum(data.column_totals_raw));
    data.summary_cards = buildSummaryCards(data.sections);
}

function recalculateSection(
    section: BudgetPlanningSection,
    categoryUuid: string,
    monthIndex: number,
    amount: number,
): BudgetPlanningSection {
    section.rows = section.rows.map((row) =>
        recalculateRow(row, categoryUuid, monthIndex, amount),
    );
    section.flat_rows = flattenRows(section.rows);
    section.totals_by_month_raw = sumRowsByMonth(section.rows);
    section.total_raw = round(sum(section.totals_by_month_raw));

    return section;
}

function recalculateRow(
    row: BudgetPlanningRow,
    categoryUuid: string,
    monthIndex: number,
    amount: number,
): BudgetPlanningRow {
    if (row.children.length > 0) {
        row.children = row.children.map((child) =>
            recalculateRow(child, categoryUuid, monthIndex, amount),
        );
        row.monthly_amounts_raw = sumRowsByMonth(row.children);
        row.row_total_raw = round(sum(row.monthly_amounts_raw));

        return row;
    }

    if (row.uuid !== categoryUuid) {
        row.row_total_raw = round(sum(row.monthly_amounts_raw));

        return row;
    }

    row.monthly_amounts_raw[monthIndex] = round(amount);
    row.row_total_raw = round(sum(row.monthly_amounts_raw));

    return row;
}

function flattenRows(rows: BudgetPlanningRow[]): BudgetPlanningSection['flat_rows'] {
    return rows.flatMap((row) => {
        const { children, ...item } = row;

        return [item, ...flattenRows(children)];
    });
}

function sumRowsByMonth(rows: BudgetPlanningRow[]): number[] {
    const totals = Array.from({ length: 12 }, () => 0);

    rows.forEach((row) => {
        row.monthly_amounts_raw.forEach((value, index) => {
            totals[index] += value;
        });
    });

    return totals.map(round);
}

function buildColumnTotals(sections: BudgetPlanningSection[]): number[] {
    const totals = Array.from({ length: 12 }, () => 0);

    sections.forEach((section) => {
        section.totals_by_month_raw.forEach((value, index) => {
            totals[index] += value;
        });
    });

    return totals.map(round);
}

function buildSummaryCards(
    sections: BudgetPlanningSection[],
): BudgetPlanningData['summary_cards'] {
    const totalsBySection = new Map(
        sections.map((section) => [section.key, section.total_raw]),
    );
    const incomeTotal = round(totalsBySection.get('income') ?? 0);
    const expenseTotal = round(totalsBySection.get('expense') ?? 0);
    const billTotal = round(totalsBySection.get('bill') ?? 0);
    const debtTotal = round(totalsBySection.get('debt') ?? 0);
    const savingTotal = round(totalsBySection.get('saving') ?? 0);

    const plannedOutflow = round(
        [...totalsBySection.entries()]
            .filter(([key]) => !['income', 'transfer'].includes(key))
            .reduce((total, [, value]) => total + value, 0),
    );
    const locale = getCurrentLocale().startsWith('it') ? 'it' : 'en';
    const labels = messages[locale].app.enums.categoryGroups;

    return [
        buildCard('income', labels.income, incomeTotal, null),
        buildCard('remaining', labels.remaining, round(incomeTotal - plannedOutflow), incomeTotal),
        buildCard('expense', labels.expense, expenseTotal, incomeTotal),
        buildCard('bill', labels.bill, billTotal, incomeTotal),
        buildCard('debt', labels.debt, debtTotal, incomeTotal),
        buildCard('saving', labels.saving, savingTotal, incomeTotal),
    ];
}

function buildCard(
    key: string,
    label: string,
    amount: number,
    incomeTotal: number | null,
) {
    return {
        key,
        label,
        amount_raw: round(amount),
        share_of_income:
            incomeTotal && incomeTotal > 0
                ? round((amount / incomeTotal) * 100)
                : null,
    };
}

function sum(values: number[]): number {
    return values.reduce((total, value) => total + value, 0);
}

function round(value: number): number {
    return Math.round(value * 100) / 100;
}
