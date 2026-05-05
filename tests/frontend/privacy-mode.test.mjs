import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    persistPrivacyMode,
    PRIVACY_MODE_STORAGE_KEY,
    readPrivacyMode,
} from '../../resources/js/lib/privacy-mode.js';

const sources = {
    toggle: readSource('resources/js/components/PrivacyModeToggle.vue'),
    sensitiveValue: readSource('resources/js/components/SensitiveValue.vue'),
    desktopNav: readSource('resources/js/components/AppSidebarHeader.vue'),
    mobileNav: readSource('resources/js/components/MobileBottomNav.vue'),
    usePrivacyMode: readSource('resources/js/composables/usePrivacyMode.ts'),
    dashboard: readSource('resources/js/pages/Dashboard.vue'),
    transactions: readSource('resources/js/pages/transactions/Show.vue'),
    monthlyRecap: readSource('resources/js/pages/dashboard/MonthlyRecap.vue'),
    reportsOverview: readSource('resources/js/pages/reports/Overview.vue'),
    reportsAccounts: readSource('resources/js/pages/reports/Accounts.vue'),
    reportsCategories: readSource('resources/js/pages/reports/Categories.vue'),
    reportsCategoryAnalysis: readSource(
        'resources/js/pages/reports/CategoryAnalysis.vue',
    ),
    budgetPlanning: readSource('resources/js/pages/budgets/Planning.vue'),
    budgetPlanningDesktop: readSource(
        'resources/js/components/budget-planning/BudgetPlanningGridDesktop.vue',
    ),
    budgetPlanningMobileList: readSource(
        'resources/js/components/budget-planning/BudgetPlanningMobileList.vue',
    ),
    budgetPlanningMobileRow: readSource(
        'resources/js/components/budget-planning/BudgetPlanningMobileRow.vue',
    ),
    budgetSummaryCards: readSource(
        'resources/js/components/budget-planning/BudgetSummaryCards.vue',
    ),
    accountsList: readSource(
        'resources/js/components/accounts/AccountsList.vue',
    ),
    accountsSettings: readSource('resources/js/pages/settings/Accounts.vue'),
    entrySearchResults: readSource(
        'resources/js/components/entry-search/EntrySearchResultMonthGroup.vue',
    ),
    recurringIndex: readSource(
        'resources/js/pages/transactions/recurring/Index.vue',
    ),
    recurringShow: readSource(
        'resources/js/pages/transactions/recurring/Show.vue',
    ),
    recurringMobileList: readSource(
        'resources/js/components/recurring/RecurringOccurrencesMobileList.vue',
    ),
    importsShow: readSource('resources/js/pages/imports/Show.vue'),
    profileSettings: readSource('resources/js/pages/settings/Profile.vue'),
    adminBilling: readSource('resources/js/pages/admin/UserBilling.vue'),
    adminAutomation: readSource('resources/js/pages/admin/Automation/Show.vue'),
    monthlySheet: readSource(
        'resources/js/pages/transactions/MonthlySheet.vue',
    ),
    dashboardPreviewChart: readSource(
        'resources/js/components/DashboardPreviewChart.vue',
    ),
    reportBreakdownChart: readSource(
        'resources/js/components/reports/ReportBreakdownChart.vue',
    ),
    reportAccountsChart: readSource(
        'resources/js/components/reports/ReportAccountsChart.vue',
    ),
    reportAccountsBalanceChart: readSource(
        'resources/js/components/reports/ReportAccountsBalanceChart.vue',
    ),
    reportAccountsCashFlowChart: readSource(
        'resources/js/components/reports/ReportAccountsCashFlowChart.vue',
    ),
    reportCategoriesTrendChart: readSource(
        'resources/js/components/reports/ReportCategoriesTrendChart.vue',
    ),
    reportCategoryAnalysisTrendChart: readSource(
        'resources/js/components/reports/ReportCategoryAnalysisTrendChart.vue',
    ),
    reportCategoriesCompositionChart: readSource(
        'resources/js/components/reports/ReportCategoriesCompositionChart.vue',
    ),
    reportOverviewComparisonChart: readSource(
        'resources/js/components/reports/ReportOverviewComparisonChart.vue',
    ),
    reportCategoryAnalysisChart: readSource(
        'resources/js/components/reports/ReportCategoryAnalysisChart.vue',
    ),
    reportOverviewTrendChart: readSource(
        'resources/js/components/reports/ReportOverviewTrendChart.vue',
    ),
    reportAccountsDistributionChart: readSource(
        'resources/js/components/reports/ReportAccountsDistributionChart.vue',
    ),
    appMessages: readSource('resources/js/i18n/messages/app.ts'),
};

function readSource(path) {
    return readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
}

test('privacy mode persists in localStorage', () => {
    const storage = new Map();

    global.window = {
        localStorage: {
            getItem(key) {
                return storage.has(key) ? storage.get(key) : null;
            },
            setItem(key, value) {
                storage.set(key, value);
            },
        },
    };

    assert.equal(readPrivacyMode(), false);

    persistPrivacyMode(true);

    assert.equal(storage.get(PRIVACY_MODE_STORAGE_KEY), '1');
    assert.equal(readPrivacyMode(), true);

    persistPrivacyMode(false);

    assert.equal(storage.get(PRIVACY_MODE_STORAGE_KEY), '0');
    assert.equal(readPrivacyMode(), false);

    delete global.window;
});

test('desktop navigation exposes the privacy toggle', () => {
    assert.match(sources.desktopNav, /PrivacyModeToggle/);
    assert.match(sources.toggle, /data-test="privacy-mode-toggle"/);
    assert.match(sources.toggle, /privacyModeLabel/);
    assert.match(sources.appMessages, /hideAmounts: 'Nascondi importi'/);
    assert.match(sources.appMessages, /showAmounts: 'Mostra importi'/);
    assert.match(sources.appMessages, /hideAmounts: 'Hide amounts'/);
    assert.match(sources.appMessages, /showAmounts: 'Show amounts'/);
    assert.doesNotMatch(sources.usePrivacyMode, /'Nascondi importi'/);
    assert.doesNotMatch(sources.usePrivacyMode, /'Mostra importi'/);
    assert.match(sources.toggle, /EyeOff v-if="isPrivacyModeEnabled"/);
    assert.match(sources.toggle, /Eye v-else/);
    assert.match(sources.toggle, /aria-pressed="isPrivacyModeEnabled"/);
});

test('mobile navigation exposes an accessible privacy toggle', () => {
    assert.match(sources.mobileNav, /data-test="privacy-mode-toggle-mobile"/);
    assert.match(sources.mobileNav, /:aria-label="privacyModeLabel"/);
    assert.match(sources.mobileNav, /:aria-pressed="isPrivacyModeEnabled"/);
    assert.match(sources.mobileNav, /EyeOff v-if="isPrivacyModeEnabled"/);
});

test('sensitive value renders masked and unmasked states', () => {
    assert.match(sources.sensitiveValue, /variant\?: 'inline' \| 'veil'/);
    assert.match(sources.sensitiveValue, /data-privacy-mode/);
    assert.match(sources.sensitiveValue, /v-if="isPrivacyModeEnabled"/);
    assert.match(sources.sensitiveValue, /<slot v-else>/);
    assert.match(sources.sensitiveValue, /text-transparent/);
});

test('privacy component is applied to primary money surfaces', () => {
    for (const [name, source] of Object.entries({
        dashboard: sources.dashboard,
        transactions: sources.transactions,
        monthlyRecap: sources.monthlyRecap,
        reportsOverview: sources.reportsOverview,
        reportsAccounts: sources.reportsAccounts,
        reportsCategories: sources.reportsCategories,
        reportsCategoryAnalysis: sources.reportsCategoryAnalysis,
        budgetPlanning: sources.budgetPlanning,
        budgetPlanningDesktop: sources.budgetPlanningDesktop,
        budgetPlanningMobileList: sources.budgetPlanningMobileList,
        budgetPlanningMobileRow: sources.budgetPlanningMobileRow,
        budgetSummaryCards: sources.budgetSummaryCards,
        accountsList: sources.accountsList,
        accountsSettings: sources.accountsSettings,
        entrySearchResults: sources.entrySearchResults,
        recurringIndex: sources.recurringIndex,
        recurringShow: sources.recurringShow,
        recurringMobileList: sources.recurringMobileList,
        importsShow: sources.importsShow,
        profileSettings: sources.profileSettings,
        adminBilling: sources.adminBilling,
        adminAutomation: sources.adminAutomation,
        monthlySheet: sources.monthlySheet,
        reportAccountsDistributionChart:
            sources.reportAccountsDistributionChart,
    })) {
        assert.match(source, /SensitiveValue/, `${name} uses SensitiveValue`);
    }
});

test('privacy mode masks chart currency formatters', () => {
    for (const [name, source] of Object.entries({
        dashboardPreviewChart: sources.dashboardPreviewChart,
        reportBreakdownChart: sources.reportBreakdownChart,
        reportAccountsChart: sources.reportAccountsChart,
        reportAccountsBalanceChart: sources.reportAccountsBalanceChart,
        reportAccountsCashFlowChart: sources.reportAccountsCashFlowChart,
        reportCategoriesTrendChart: sources.reportCategoriesTrendChart,
        reportCategoryAnalysisTrendChart:
            sources.reportCategoryAnalysisTrendChart,
        reportCategoriesCompositionChart:
            sources.reportCategoriesCompositionChart,
        reportOverviewComparisonChart: sources.reportOverviewComparisonChart,
        reportCategoryAnalysisChart: sources.reportCategoryAnalysisChart,
        reportOverviewTrendChart: sources.reportOverviewTrendChart,
        reportAccountsDistributionChart:
            sources.reportAccountsDistributionChart,
    })) {
        assert.match(source, /usePrivacyMode/, `${name} uses privacy mode`);
        assert.match(source, /Importo nascosto/, `${name} masks chart amounts`);
    }
});
