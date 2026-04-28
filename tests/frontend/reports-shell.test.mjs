import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const routesSource = readFileSync(
    new URL('../../routes/dashboard.php', import.meta.url),
    'utf8',
);
const reportPageSource = readFileSync(
    new URL('../../resources/js/pages/reports/Index.vue', import.meta.url),
    'utf8',
);
const reportOverviewPageSource = readFileSync(
    new URL('../../resources/js/pages/reports/Overview.vue', import.meta.url),
    'utf8',
);
const reportCategoriesPageSource = readFileSync(
    new URL('../../resources/js/pages/reports/Categories.vue', import.meta.url),
    'utf8',
);
const reportAccountsPageSource = readFileSync(
    new URL('../../resources/js/pages/reports/Accounts.vue', import.meta.url),
    'utf8',
);
const reportsLayoutSource = readFileSync(
    new URL('../../resources/js/layouts/reports/Layout.vue', import.meta.url),
    'utf8',
);
const reportOverviewChartSource = readFileSync(
    new URL(
        '../../resources/js/components/reports/ReportOverviewTrendChart.vue',
        import.meta.url,
    ),
    'utf8',
);
const reportOverviewComparisonSource = readFileSync(
    new URL(
        '../../resources/js/components/reports/ReportOverviewComparisonChart.vue',
        import.meta.url,
    ),
    'utf8',
);
const reportCategoriesCompositionSource = readFileSync(
    new URL(
        '../../resources/js/components/reports/ReportCategoriesCompositionChart.vue',
        import.meta.url,
    ),
    'utf8',
);
const reportCategoriesTrendSource = readFileSync(
    new URL(
        '../../resources/js/components/reports/ReportCategoriesTrendChart.vue',
        import.meta.url,
    ),
    'utf8',
);
const reportAccountsBalanceChartSource = readFileSync(
    new URL(
        '../../resources/js/components/reports/ReportAccountsBalanceChart.vue',
        import.meta.url,
    ),
    'utf8',
);
const reportAccountsCashFlowChartSource = readFileSync(
    new URL(
        '../../resources/js/components/reports/ReportAccountsCashFlowChart.vue',
        import.meta.url,
    ),
    'utf8',
);
const reportAccountsDistributionChartSource = readFileSync(
    new URL(
        '../../resources/js/components/reports/ReportAccountsDistributionChart.vue',
        import.meta.url,
    ),
    'utf8',
);
const reportsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/reports.ts', import.meta.url),
    'utf8',
);
const headerSource = readFileSync(
    new URL(
        '../../resources/js/components/AppSidebarHeader.vue',
        import.meta.url,
    ),
    'utf8',
);

test('reports route is registered in the authenticated dashboard routes', () => {
    assert.match(
        routesSource,
        /Route::get\('reports', \[ReportController::class, 'index']\)->name\('reports'\);/,
    );
    assert.match(
        routesSource,
        /Route::get\('reports\/kpis', \[ReportController::class, 'kpis']\)->middleware\('feature\.reports:kpis'\)->name\('reports\.kpis'\);/,
    );
    assert.match(
        routesSource,
        /Route::get\('reports\/categories', \[ReportController::class, 'categories']\)->middleware\('feature\.reports:categories'\)->name\('reports\.categories'\);/,
    );
});

test('reports page behaves as a launcher instead of a final analytics dashboard', () => {
    assert.doesNotMatch(reportPageSource, /DashboardPreviewChart/);
    assert.doesNotMatch(reportPageSource, /ReportBreakdownChart/);
    assert.doesNotMatch(reportPageSource, /ReportAccountsChart/);
    assert.match(reportPageSource, /ReportsLayout/);
    assert.match(reportPageSource, /reportKpis\(\)/);
    assert.match(reportPageSource, /router\.visit\(reportKpis\(\)/);
    assert.match(reportPageSource, /v-if="isMobileViewport"/);
    assert.match(reportsLayoutSource, /reports-mobile-launcher/);
    assert.match(reportsLayoutSource, /reports-desktop-sidebar/);
    assert.match(reportsLayoutSource, /reports-mobile-page-header/);
    assert.match(reportsLayoutSource, /budgetPlanning\(\)/);
    assert.doesNotMatch(reportsLayoutSource, /activePeriodLabel/);
});

test('reports overview page wires the first real analytics section with filters, kpis, and charts', () => {
    assert.match(reportOverviewPageSource, /reports-overview-filter-bar/);
    assert.match(reportOverviewPageSource, /reports-overview-kpis/);
    assert.match(reportOverviewPageSource, /ReportOverviewTrendChart/);
    assert.match(reportOverviewPageSource, /ReportOverviewComparisonChart/);
    assert.match(
        reportOverviewPageSource,
        /reportKpis\(\{ query: buildQuery\(\) }/,
    );
    assert.match(reportOverviewPageSource, /reports\.filters\.title/);
    assert.match(reportOverviewPageSource, /reports\.overview\.kpis\.income/);
    assert.match(
        reportOverviewPageSource,
        /reports\.overview\.distribution\.title/,
    );
    assert.match(
        reportOverviewPageSource,
        /reports\.overview\.comparison\.title/,
    );
    assert.match(
        reportOverviewPageSource,
        /reports\.overview\.kpis\.previousPeriodHint/,
    );
    assert.match(reportOverviewPageSource, /comparisonSummary\(/);
    assert.match(
        reportOverviewPageSource,
        /reports\.overview\.snapshot\.empty/,
    );
    assert.match(
        reportOverviewPageSource,
        /reports\.filters\.monthDisabledAnnual/,
    );
    assert.match(reportOverviewPageSource, /const snapshotBuckets = computed/);
    assert.match(
        reportOverviewPageSource,
        /const observedBuckets = buckets\.filter/,
    );
    assert.match(reportOverviewChartSource, /type:\s*'pie'/);
    assert.match(reportOverviewChartSource, /radius:\s*\['42%', '70%']/);
    assert.match(reportOverviewChartSource, /avoidLabelOverlap:\s*false/);
    assert.match(reportOverviewChartSource, /stillShowZeroSum:\s*true/);
    assert.match(reportOverviewChartSource, /borderRadius:\s*10/);
    assert.match(reportOverviewChartSource, /labelLine:\s*\{\s*show:\s*false/);
    assert.match(reportOverviewChartSource, /reports\.overview\.kpis\.net/);
    assert.match(
        reportOverviewChartSource,
        /Math\.abs\(props\.kpis\.net_total_raw\)/,
    );
    assert.match(reportOverviewChartSource, /const hasChartData = computed/);
    assert.match(
        reportOverviewChartSource,
        /reports\.overview\.distribution\.centerLabel/,
    );
    assert.doesNotMatch(reportOverviewChartSource, /transactionCountLabel/);
    assert.match(reportOverviewChartSource, /import\('echarts\/core'\)/);
    assert.match(reportOverviewChartSource, /void initializeChart\(\)/);
    assert.match(reportOverviewComparisonSource, /type:\s*'bar'/);
    assert.match(reportOverviewComparisonSource, /type:\s*'line'/);
    assert.match(reportOverviewComparisonSource, /import\('echarts\/core'\)/);
    assert.match(reportOverviewComparisonSource, /void initializeChart\(\)/);
    assert.match(reportOverviewComparisonSource, /disposeChart\(\)/);
    assert.match(reportOverviewPageSource, /function defaultFilterYear\(\)/);
    assert.match(reportOverviewPageSource, /preserveState:\s*false/);
});

test('reports categories page wires the dedicated category analytics section', () => {
    assert.match(
        reportCategoriesPageSource,
        /ReportCategoriesCompositionChart/,
    );
    assert.match(reportCategoriesPageSource, /ReportCategoriesTrendChart/);
    assert.match(
        reportCategoriesPageSource,
        /reportCategoriesRoute\(\{ query: buildQuery\(\) }/,
    );
    assert.match(
        reportCategoriesPageSource,
        /reports\.overview\.categoriesPage\.title/,
    );
    assert.match(
        reportCategoriesPageSource,
        /reports\.overview\.categoriesPage\.topCategories/,
    );
    assert.match(
        reportCategoriesPageSource,
        /reports\.overview\.categoriesPage\.mainCategory/,
    );
    assert.match(reportCategoriesPageSource, /resetFilters/);
    assert.match(
        reportCategoriesPageSource,
        /reports\.filters\.monthDisabledAnnual/,
    );
    assert.match(reportCategoriesCompositionSource, /type:\s*'sunburst'/);
    assert.match(reportCategoriesCompositionSource, /type:\s*'treemap'/);
    assert.match(
        reportCategoriesCompositionSource,
        /import\('echarts\/core'\)/,
    );
    assert.match(reportCategoriesTrendSource, /stack:\s*'category-trend'/);
    assert.match(reportCategoriesTrendSource, /import\('echarts\/core'\)/);
});

test('reports categories top list uses static user-facing labels without fake drill-down affordances', () => {
    assert.doesNotMatch(reportCategoriesPageSource, /systemTag/);
    assert.doesNotMatch(
        reportCategoriesPageSource,
        /mt-1 h-4 w-4 shrink-0 text-slate-400/,
    );
    assert.doesNotMatch(reportsMessagesSource, /topCategoriesHint:\s*'Tocca/);
    assert.doesNotMatch(reportsMessagesSource, /topCategoriesHint:\s*'Tap/);
    assert.doesNotMatch(reportsMessagesSource, /systemTag:\s*'SYS'/);
    assert.match(reportsMessagesSource, /categorie incluse nel gruppo/);
    assert.match(reportsMessagesSource, /categories included in this group/);
    assert.match(reportCategoriesPageSource, /categoriesPage\.subcategories/);
});

test('reports accounts page implements the account vision section with mobile navigation', () => {
    assert.match(
        reportAccountsPageSource,
        /reportAccountsRoute\(\{ query: buildQuery\(\) }/,
    );
    assert.match(reportAccountsPageSource, /edit as accountsEdit/);
    assert.match(reportAccountsPageSource, /function visitCreateAccount/);
    assert.match(
        reportAccountsPageSource,
        /accountsEdit\(\{\s*query:\s*\{\s*create:\s*'1'/,
    );
    assert.match(reportAccountsPageSource, /@click="visitCreateAccount"/);
    assert.match(
        reportAccountsPageSource,
        /reports\.overview\.accountsPage\.title/,
    );
    assert.match(reportAccountsPageSource, /selectedAccountUuid/);
    assert.match(reportAccountsPageSource, /sticky top-0 z-20/);
    assert.match(reportAccountsPageSource, /ReportAccountsBalanceChart/);
    assert.match(reportAccountsPageSource, /ReportAccountsCashFlowChart/);
    assert.match(reportAccountsPageSource, /ReportAccountsDistributionChart/);
    assert.match(reportAccountsPageSource, /reportAccounts\.balance_trend/);
    assert.match(reportAccountsPageSource, /reportAccounts\.cash_flow/);
    assert.match(reportAccountsPageSource, /accountKpiCards/);
    assert.match(reportAccountsPageSource, /comparisonParts/);
    assert.match(reportAccountsPageSource, /comparison_available/);
    assert.match(reportAccountsPageSource, /vsPreviousYear/);
    assert.match(reportAccountsPageSource, /comparisonUnavailable/);
    assert.doesNotMatch(reportAccountsPageSource, /previous_period_label\s*}}/);
    assert.match(reportAccountsPageSource, /shareTrackWidth/);
    assert.match(reportAccountsPageSource, /comparison_rows/);
    assert.match(
        reportAccountsPageSource,
        /sparklineAreaPoints\(\s*row\.sparkline/,
    );
    assert.match(reportAccountsPageSource, /emptyComparison/);
    assert.match(reportAccountsPageSource, /recent_transactions/);
    assert.match(reportAccountsBalanceChartSource, /type:\s*'line'/);
    assert.match(reportAccountsBalanceChartSource, /props\.chart\.series/);
    assert.match(reportAccountsBalanceChartSource, /areaStyle/);
    assert.match(reportAccountsBalanceChartSource, /import\('echarts\/core'\)/);
    assert.match(reportAccountsCashFlowChartSource, /type:\s*'bar'/);
    assert.match(reportAccountsCashFlowChartSource, /cashFlow\.income_values/);
    assert.match(reportAccountsCashFlowChartSource, /cashFlow\.has_data/);
    assert.match(reportAccountsCashFlowChartSource, /void initializeChart\(\)/);
    assert.match(reportAccountsCashFlowChartSource, /stack:\s*'cash-flow'/);
    assert.match(
        reportAccountsCashFlowChartSource,
        /import\('echarts\/core'\)/,
    );
    assert.match(reportAccountsDistributionChartSource, /type:\s*'pie'/);
    assert.match(
        reportAccountsDistributionChartSource,
        /radius:\s*\['58%', '78%']/,
    );
    assert.match(
        reportAccountsDistributionChartSource,
        /import\('echarts\/core'\)/,
    );
    assert.match(reportsMessagesSource, /multiAccountTrend/);
    assert.match(reportsMessagesSource, /emptyBalanceTrend/);
});

test('desktop quick actions expose the planning shortcut alongside the new report shell', () => {
    assert.match(headerSource, /t\('app\.shell\.actions\.openPlanning'\)/);
    assert.match(headerSource, /href:\s*budgetPlanning\(\)/);
});
