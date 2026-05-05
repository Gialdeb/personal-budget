<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    CircleDollarSign,
    Download,
    FileText,
    Info,
    Table2,
    Trophy,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ReportCategoriesCompositionChart from '@/components/reports/ReportCategoriesCompositionChart.vue';
import ReportCategoryAnalysisChart from '@/components/reports/ReportCategoryAnalysisChart.vue';
import ReportCategoryAnalysisTrendChart from '@/components/reports/ReportCategoryAnalysisTrendChart.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportsLayout from '@/layouts/reports/Layout.vue';
import { reports } from '@/routes';
import { categoryAnalysis as reportCategoryAnalysisRoute } from '@/routes/reports';
import {
    exportMethod as exportReportCategoryAnalysisRoute,
    exportPdf as exportReportCategoryAnalysisPdfRoute,
} from '@/routes/reports/category-analysis';
import type { BreadcrumbItem } from '@/types/navigation';
import type {
    ReportCategoryAnalysisPageProps,
    ReportMetricMoneyComparison,
    ReportPeriodFilterValue,
} from '@/types/report';

const props = defineProps<ReportCategoryAnalysisPageProps>();
const { t } = useI18n();
const ALL_ACCOUNTS_VALUE = '__all__';
const ALL_SUBCATEGORIES_VALUE = '__all__';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('nav.reports'),
        href: reports(),
    },
    {
        title: props.activeReportSection.title,
        href: props.activeReportSection.href,
    },
];

const selectedYear = ref(String(props.reportCategoryAnalysis.filters.year));
const selectedPeriod = ref<ReportPeriodFilterValue>(
    props.reportCategoryAnalysis.filters.period,
);
const selectedMonth = ref(
    props.reportCategoryAnalysis.filters.month !== null
        ? String(props.reportCategoryAnalysis.filters.month)
        : '',
);
const selectedAccountUuid = ref(
    props.reportCategoryAnalysis.filters.account_uuid ?? ALL_ACCOUNTS_VALUE,
);
const selectedCategoryUuid = ref(
    props.reportCategoryAnalysis.filters.category_uuid ?? '',
);
const selectedSubcategoryUuid = ref(
    props.reportCategoryAnalysis.filters.subcategory_uuid ??
        ALL_SUBCATEGORIES_VALUE,
);

watch(
    () => props.reportCategoryAnalysis.filters,
    (filters) => {
        selectedYear.value = String(filters.year);
        selectedPeriod.value = filters.period;
        selectedMonth.value =
            filters.month !== null ? String(filters.month) : '';
        selectedAccountUuid.value = filters.account_uuid ?? ALL_ACCOUNTS_VALUE;
        selectedCategoryUuid.value = filters.category_uuid ?? '';
        selectedSubcategoryUuid.value =
            filters.subcategory_uuid ?? ALL_SUBCATEGORIES_VALUE;
    },
    { deep: true },
);

watch(selectedPeriod, (period) => {
    if (period === 'annual') {
        selectedMonth.value = '';

        return;
    }

    if (selectedMonth.value === '') {
        selectedMonth.value = String(props.reportContext.month ?? 1);
    }
});

const showMonthFilter = computed(() => selectedPeriod.value !== 'annual');
const analysisTitle = computed(() => {
    const focus =
        props.reportCategoryAnalysis.meta.subcategory_label ??
        props.reportCategoryAnalysis.meta.category_label;

    return focus
        ? `${t('reports.categoryAnalysis.title')} · ${focus}`
        : t('reports.categoryAnalysis.title');
});
const hasCategoryOptions = computed(
    () => props.reportCategoryAnalysis.filters.category_options.length > 0,
);
const categoryTreeOptions = computed(
    () => props.reportCategoryAnalysis.filters.category_tree_options,
);
const categoryTreeOptionByValue = computed(
    () =>
        new Map(
            categoryTreeOptions.value.map((option) => [option.value, option]),
        ),
);
const selectedCategoryFocusUuid = computed({
    get: () =>
        selectedSubcategoryUuid.value !== ALL_SUBCATEGORIES_VALUE
            ? selectedSubcategoryUuid.value
            : selectedCategoryUuid.value,
    set: (value: string) => updateCategoryFocus(value),
});

const kpiCards = computed(() => [
    {
        key: 'total',
        label: t('reports.categoryAnalysis.kpis.totalSpent'),
        value: props.reportCategoryAnalysis.summary.total_spent,
        helper: props.reportCategoryAnalysis.meta.period_label,
        icon: CircleDollarSign,
        borderClass: 'border-l-rose-500',
    },
    {
        key: 'average',
        label: t('reports.categoryAnalysis.kpis.averagePeriod'),
        value: props.reportCategoryAnalysis.summary.average_period,
        helper: props.reportCategoryAnalysis.summary.average_period_label,
        icon: TrendingUp,
        borderClass: 'border-l-slate-400',
    },
    {
        key: 'best',
        label: t('reports.categoryAnalysis.kpis.bestMonth'),
        value:
            props.reportCategoryAnalysis.summary.best_period_value ??
            t('reports.categoryAnalysis.kpis.noData'),
        helper: props.reportCategoryAnalysis.summary.best_period_label,
        icon: Trophy,
        borderClass: 'border-l-emerald-500',
    },
    {
        key: 'budget',
        label: t('reports.categoryAnalysis.budget.label'),
        value: props.reportCategoryAnalysis.meta.budget.supported
            ? props.reportCategoryAnalysis.meta.budget.total
            : t('reports.categoryAnalysis.kpis.noData'),
        helper: props.reportCategoryAnalysis.meta.budget.supported
            ? `${t('reports.categoryAnalysis.budget.variance')} ${props.reportCategoryAnalysis.meta.budget.variance}`
            : t('reports.categoryAnalysis.budget.unavailable'),
        icon: BarChart3,
        borderClass:
            props.reportCategoryAnalysis.meta.budget.status === 'over'
                ? 'border-l-red-500'
                : 'border-l-blue-500',
    },
    {
        key: 'worst',
        label: t('reports.categoryAnalysis.kpis.worstMonth'),
        value:
            props.reportCategoryAnalysis.summary.worst_period_value ??
            t('reports.categoryAnalysis.kpis.noData'),
        helper: props.reportCategoryAnalysis.summary.worst_period_label,
        icon: BarChart3,
        borderClass: 'border-l-red-500',
    },
]);

function comparisonParts(comparison: ReportMetricMoneyComparison): {
    label: string;
    className: string;
    icon: typeof ArrowUpRight;
} {
    const isUp = comparison.direction === 'up';

    return {
        label:
            comparison.delta_percentage_label !== null
                ? `${comparison.delta_percentage_label} · ${comparison.delta_formatted}`
                : comparison.delta_formatted,
        className: isUp
            ? 'text-rose-600 dark:text-rose-300'
            : comparison.direction === 'down'
              ? 'text-emerald-600 dark:text-emerald-300'
              : 'text-slate-500 dark:text-slate-400',
        icon: isUp ? ArrowUpRight : ArrowDownRight,
    };
}

function isSensitiveKpi(key: string): boolean {
    return ['total', 'average', 'best', 'budget', 'worst'].includes(key);
}

const previousPeriodComparison = computed(() =>
    comparisonParts(props.reportCategoryAnalysis.comparisons.previous_period),
);
const previousYearComparison = computed(() =>
    comparisonParts(props.reportCategoryAnalysis.comparisons.previous_year),
);
const insightBadgeLabel = computed(() => {
    if (props.reportCategoryAnalysis.meta.insight.tone === 'warning') {
        return t('reports.categoryAnalysis.insight.badgeWarning');
    }

    if (props.reportCategoryAnalysis.meta.insight.tone === 'stable') {
        return t('reports.categoryAnalysis.insight.badgeStable');
    }

    return t('reports.categoryAnalysis.insight.badgeInfo');
});
const insightClasses = computed(() =>
    props.reportCategoryAnalysis.meta.insight.tone === 'warning'
        ? 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100'
        : props.reportCategoryAnalysis.meta.insight.tone === 'stable'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-100'
          : 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-100',
);
const hasActualSpend = computed(
    () => props.reportCategoryAnalysis.meta.has_actual_spend,
);

function buildQuery(): Record<string, string | number> {
    const query: Record<string, string | number> = {
        year: Number(selectedYear.value),
        period: selectedPeriod.value,
    };

    if (showMonthFilter.value && selectedMonth.value !== '') {
        query.month = Number(selectedMonth.value);
    }

    if (selectedAccountUuid.value !== ALL_ACCOUNTS_VALUE) {
        query.account_uuid = selectedAccountUuid.value;
    }

    if (selectedCategoryUuid.value !== '') {
        query.category_uuid = selectedCategoryUuid.value;
    }

    if (selectedSubcategoryUuid.value !== ALL_SUBCATEGORIES_VALUE) {
        query.subcategory_uuid = selectedSubcategoryUuid.value;
    }

    return query;
}

function applyFilters(): void {
    router.visit(reportCategoryAnalysisRoute({ query: buildQuery() }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function exportWithCurrentFilters(): void {
    window.location.assign(
        exportReportCategoryAnalysisRoute.url({ query: buildQuery() }),
    );
}

function exportPdfWithCurrentFilters(): void {
    window.location.assign(
        exportReportCategoryAnalysisPdfRoute.url({ query: buildQuery() }),
    );
}

function updateCategoryFocus(value: string): void {
    const option = categoryTreeOptionByValue.value.get(value);
    const rootCategoryUuid = option?.ancestor_uuids?.[0] ?? value;

    selectedCategoryUuid.value = rootCategoryUuid;
    selectedSubcategoryUuid.value =
        rootCategoryUuid === value ? ALL_SUBCATEGORIES_VALUE : value;
}

function resetFilters(): void {
    selectedYear.value = String(props.reportContext.year);
    selectedPeriod.value = 'annual';
    selectedMonth.value = '';
    selectedAccountUuid.value = ALL_ACCOUNTS_VALUE;
    selectedCategoryUuid.value =
        props.reportCategoryAnalysis.filters.category_options[0]?.value ?? '';
    selectedSubcategoryUuid.value = ALL_SUBCATEGORIES_VALUE;
    applyFilters();
}

function deltaClass(value: number): string {
    if (value > 0) {
        return 'text-right font-medium text-rose-600 dark:text-rose-300';
    }

    if (value < 0) {
        return 'text-right font-medium text-emerald-600 dark:text-emerald-300';
    }

    return 'text-right font-medium text-muted-foreground';
}
</script>

<template>
    <Head :title="props.activeReportSection.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ReportsLayout :report-sections="props.reportSections">
            <section
                class="rounded-[32px] border border-white/70 bg-white/94 p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/72"
            >
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                >
                    <div class="space-y-2">
                        <p
                            class="text-[11px] font-semibold tracking-[0.22em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{
                                t('reports.categoryAnalysis.areaLabel', {
                                    year: props.reportCategoryAnalysis.filters
                                        .year,
                                })
                            }}
                        </p>
                        <h1
                            class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                        >
                            {{ analysisTitle }}
                        </h1>
                        <p
                            class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ t('reports.categoryAnalysis.description') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            class="rounded-full"
                            @click="exportWithCurrentFilters"
                        >
                            <Download class="mr-2 h-4 w-4" />
                            {{ t('reports.categoryAnalysis.export.xlsx') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="rounded-full"
                            @click="exportPdfWithCurrentFilters"
                        >
                            <FileText class="mr-2 h-4 w-4" />
                            {{ t('reports.categoryAnalysis.export.pdf') }}
                        </Button>
                    </div>
                </div>
            </section>

            <section
                class="rounded-[30px] border border-white/70 bg-white/94 p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
            >
                <div
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,150px)_minmax(0,190px)_minmax(0,190px)_minmax(0,320px)_minmax(0,1fr)]"
                >
                    <Select v-model="selectedYear">
                        <SelectTrigger
                            class="h-11 rounded-full bg-white/90 dark:bg-white/5"
                        >
                            <SelectValue
                                :placeholder="t('reports.filters.year')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.reportCategoryAnalysis
                                    .filters.available_years"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="selectedPeriod">
                        <SelectTrigger
                            class="h-11 rounded-full bg-white/90 dark:bg-white/5"
                        >
                            <SelectValue
                                :placeholder="t('reports.filters.period')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.reportCategoryAnalysis
                                    .filters.period_options"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-if="showMonthFilter" v-model="selectedMonth">
                        <SelectTrigger
                            class="h-11 rounded-full bg-white/90 dark:bg-white/5"
                        >
                            <SelectValue
                                :placeholder="
                                    t('reports.filters.referenceMonth')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.reportCategoryAnalysis
                                    .filters.month_options"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <SearchableSelect
                        v-model="selectedCategoryFocusUuid"
                        :options="categoryTreeOptions"
                        :placeholder="t('reports.categoryAnalysis.category')"
                        :search-placeholder="
                            t('reports.categoryAnalysis.searchCategory')
                        "
                        :empty-label="
                            t('reports.categoryAnalysis.emptyCategories')
                        "
                        :back-label="t('reports.categoryAnalysis.back')"
                        hierarchical
                        trigger-class="min-h-11 rounded-full bg-white/90 dark:bg-white/5"
                        content-class="min-w-[22rem]"
                    />

                    <Select v-model="selectedAccountUuid">
                        <SelectTrigger
                            class="h-11 rounded-full bg-white/90 dark:bg-white/5"
                        >
                            <SelectValue
                                :placeholder="t('reports.filters.resource')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_ACCOUNTS_VALUE">
                                {{ t('reports.filters.allResources') }}
                            </SelectItem>
                            <SelectItem
                                v-for="option in props.reportCategoryAnalysis
                                    .filters.account_options"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="resetFilters"
                    >
                        {{ t('reports.filters.reset') }}
                    </Button>
                    <Button class="rounded-full" @click="applyFilters">
                        {{ t('reports.filters.apply') }}
                    </Button>
                </div>
            </section>

            <Card
                v-if="!hasCategoryOptions"
                class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
            >
                <CardContent class="p-6 text-sm text-muted-foreground">
                    {{ t('reports.categoryAnalysis.emptyState') }}
                </CardContent>
            </Card>

            <template v-else>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <Card
                        v-for="item in kpiCards"
                        :key="item.key"
                        :class="[
                            'rounded-[24px] border-l-4 border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70',
                            item.borderClass,
                        ]"
                    >
                        <CardContent class="flex items-start gap-3 p-4">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200/80 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200"
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ item.label }}
                                </p>
                                <p
                                    class="mt-2 truncate text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                                >
                                    <SensitiveValue
                                        v-if="isSensitiveKpi(item.key)"
                                        variant="veil"
                                        :value="item.value"
                                    />
                                    <template v-else>
                                        {{ item.value }}
                                    </template>
                                </p>
                                <p
                                    v-if="item.helper"
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    <SensitiveValue
                                        v-if="item.key === 'budget'"
                                        :value="item.helper"
                                    />
                                    <template v-else>
                                        {{ item.helper }}
                                    </template>
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div
                    class="rounded-[22px] border border-slate-200/80 bg-white/92 p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start">
                        <div
                            :class="[
                                'flex min-w-0 flex-1 items-start gap-3 rounded-2xl border px-3 py-2.5 text-sm',
                                insightClasses,
                            ]"
                        >
                            <div
                                class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/70 text-current dark:bg-white/10"
                            >
                                <Info class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold">
                                        {{
                                            props.reportCategoryAnalysis.meta
                                                .insight.title
                                        }}
                                    </p>
                                    <span
                                        class="rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-semibold tracking-[0.14em] uppercase dark:bg-white/10"
                                    >
                                        {{ insightBadgeLabel }}
                                    </span>
                                </div>
                                <p class="mt-1 leading-6">
                                    {{
                                        props.reportCategoryAnalysis.meta
                                            .insight.message
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1 lg:max-w-[46%]">
                            <p
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'reports.categoryAnalysis.scope.summaryLabel',
                                    )
                                }}
                            </p>
                            <p
                                class="mt-1 text-sm leading-6 text-slate-700 dark:text-slate-300"
                            >
                                {{
                                    props.reportCategoryAnalysis.meta
                                        .scope_summary
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!hasActualSpend"
                    class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50/80 p-5 text-sm text-slate-700 dark:border-white/15 dark:bg-white/[0.04] dark:text-slate-200"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 dark:border-white/10 dark:bg-white/10 dark:text-slate-200"
                        >
                            <Info class="h-4 w-4" />
                        </div>
                        <div class="min-w-0">
                            <p
                                class="font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    props.reportCategoryAnalysis.meta
                                        .empty_state_title
                                }}
                            </p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                {{
                                    props.reportCategoryAnalysis.meta
                                        .empty_state_message
                                }}
                            </p>
                            <p
                                v-if="
                                    props.reportCategoryAnalysis.meta.budget
                                        .supported
                                "
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                {{
                                    t(
                                        'reports.categoryAnalysis.emptyDataset.budgetNote',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <Card
                        class="rounded-[20px] border-white/70 bg-white/90 shadow-sm dark:border-white/10 dark:bg-slate-950/60"
                    >
                        <CardContent
                            class="flex items-center justify-between gap-4 p-4"
                        >
                            <div>
                                <p
                                    class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        t(
                                            'reports.categoryAnalysis.kpis.previousPeriod',
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        props.reportCategoryAnalysis.meta
                                            .previous_period_label
                                    }}
                                </p>
                            </div>
                            <div
                                :class="[
                                    'flex items-center gap-2 text-sm font-semibold',
                                    previousPeriodComparison.className,
                                ]"
                            >
                                <component
                                    :is="previousPeriodComparison.icon"
                                    class="h-4 w-4"
                                />
                                <SensitiveValue
                                    :value="previousPeriodComparison.label"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[20px] border-white/70 bg-white/90 shadow-sm dark:border-white/10 dark:bg-slate-950/60"
                    >
                        <CardContent
                            class="flex items-center justify-between gap-4 p-4"
                        >
                            <div>
                                <p
                                    class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        t(
                                            'reports.categoryAnalysis.kpis.previousYear',
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        props.reportCategoryAnalysis.meta
                                            .previous_year_label
                                    }}
                                </p>
                            </div>
                            <div
                                :class="[
                                    'flex items-center gap-2 text-sm font-semibold',
                                    previousYearComparison.className,
                                ]"
                            >
                                <template
                                    v-if="
                                        props.reportCategoryAnalysis.comparisons
                                            .previous_year.available
                                    "
                                >
                                    <component
                                        :is="previousYearComparison.icon"
                                        class="h-4 w-4"
                                    />
                                    <SensitiveValue
                                        :value="previousYearComparison.label"
                                    />
                                </template>
                                <span v-else class="text-muted-foreground">
                                    {{
                                        t(
                                            'reports.categoryAnalysis.comparisons.unavailableShort',
                                        )
                                    }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_420px]">
                    <Card
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.trendTitle',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.trendDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ReportCategoryAnalysisTrendChart
                                :chart="props.reportCategoryAnalysis.trend"
                                :currency="
                                    props.reportCategoryAnalysis.currency
                                "
                                :empty-label="
                                    t(
                                        'reports.categoryAnalysis.charts.emptyTrend',
                                    )
                                "
                            />
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.breakdownTitle',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.breakdownDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ReportCategoriesCompositionChart
                                :nodes="
                                    props.reportCategoryAnalysis
                                        .subcategory_breakdown.nodes
                                "
                                :currency="
                                    props.reportCategoryAnalysis.currency
                                "
                                :empty-label="
                                    t(
                                        'reports.categoryAnalysis.charts.emptyBreakdown',
                                    )
                                "
                                variant="sunburst"
                            />
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <Card
                        v-if="
                            props.reportCategoryAnalysis.year_comparison
                                .supported
                        "
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.yearComparisonTitle',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.yearComparisonDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ReportCategoryAnalysisChart
                                :chart="
                                    props.reportCategoryAnalysis.year_comparison
                                "
                                :currency="
                                    props.reportCategoryAnalysis.currency
                                "
                                :empty-label="
                                    t(
                                        'reports.categoryAnalysis.charts.emptyYearComparison',
                                    )
                                "
                            />
                        </CardContent>
                    </Card>

                    <Card
                        v-if="props.reportCategoryAnalysis.cumulative.supported"
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.cumulativeTitle',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.categoryAnalysis.charts.cumulativeDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ReportCategoryAnalysisChart
                                :chart="props.reportCategoryAnalysis.cumulative"
                                :currency="
                                    props.reportCategoryAnalysis.currency
                                "
                                :empty-label="
                                    t(
                                        'reports.categoryAnalysis.charts.emptyCumulative',
                                    )
                                "
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card
                    v-if="
                        props.reportCategoryAnalysis.subcategory_timeline
                            .supported
                    "
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{
                                t(
                                    'reports.categoryAnalysis.charts.subcategoryTimelineTitle',
                                )
                            }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'reports.categoryAnalysis.charts.subcategoryTimelineDescription',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ReportCategoryAnalysisChart
                            :chart="
                                props.reportCategoryAnalysis
                                    .subcategory_timeline
                            "
                            :currency="props.reportCategoryAnalysis.currency"
                            :empty-label="
                                t(
                                    'reports.categoryAnalysis.charts.emptySubcategoryTimeline',
                                )
                            "
                            height-class="h-[340px]"
                        />
                    </CardContent>
                </Card>

                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader class="flex flex-row items-start gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200/80 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200"
                        >
                            <Table2 class="h-4 w-4" />
                        </div>
                        <div>
                            <CardTitle class="text-base">
                                {{ t('reports.categoryAnalysis.table.title') }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.categoryAnalysis.table.description',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table
                            v-if="
                                props.reportCategoryAnalysis.monthly_rows
                                    .length > 0
                            "
                        >
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {{
                                            t(
                                                'reports.categoryAnalysis.table.period',
                                            )
                                        }}
                                    </TableHead>
                                    <TableHead class="text-right">
                                        {{
                                            t(
                                                'reports.categoryAnalysis.table.spent',
                                            )
                                        }}
                                    </TableHead>
                                    <TableHead class="text-right">
                                        {{
                                            t(
                                                'reports.categoryAnalysis.budget.label',
                                            )
                                        }}
                                    </TableHead>
                                    <TableHead class="text-right">
                                        {{
                                            t(
                                                'reports.categoryAnalysis.budget.variance',
                                            )
                                        }}
                                    </TableHead>
                                    <TableHead class="text-right">
                                        {{
                                            t(
                                                'reports.categoryAnalysis.table.previousYear',
                                            )
                                        }}
                                    </TableHead>
                                    <TableHead class="text-right">
                                        {{
                                            t(
                                                'reports.categoryAnalysis.table.deltaPreviousYear',
                                            )
                                        }}
                                    </TableHead>
                                    <TableHead>
                                        {{
                                            t(
                                                'reports.categoryAnalysis.table.dominantSubcategory',
                                            )
                                        }}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in props.reportCategoryAnalysis
                                        .monthly_rows"
                                    :key="row.key"
                                >
                                    <TableCell class="font-medium">
                                        {{ row.label }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <SensitiveValue :value="row.spent" />
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <SensitiveValue
                                            :value="row.budget ?? '-'"
                                        />
                                    </TableCell>
                                    <TableCell
                                        :class="
                                            row.budget === null
                                                ? 'text-right text-muted-foreground'
                                                : deltaClass(
                                                      row.budget_delta_raw,
                                                  )
                                        "
                                    >
                                        <SensitiveValue
                                            :value="row.budget_delta ?? '-'"
                                        />
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <SensitiveValue
                                            :value="row.previous_year ?? '-'"
                                        />
                                    </TableCell>
                                    <TableCell
                                        :class="
                                            deltaClass(
                                                row.delta_previous_year_raw,
                                            )
                                        "
                                    >
                                        <SensitiveValue
                                            :value="row.delta_previous_year"
                                        />
                                        <span
                                            v-if="
                                                row.delta_previous_year_percentage_label
                                            "
                                            class="ml-1 text-xs text-muted-foreground"
                                        >
                                            ({{
                                                row.delta_previous_year_percentage_label
                                            }})
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="
                                                row.dominant_subcategory_label
                                            "
                                            class="font-medium text-slate-800 dark:text-slate-200"
                                        >
                                            {{ row.dominant_subcategory_label }}
                                        </span>
                                        <span
                                            v-if="row.dominant_subcategory"
                                            class="ml-1 text-xs text-muted-foreground"
                                        >
                                            {{ row.dominant_subcategory }}
                                        </span>
                                        <span
                                            v-if="
                                                !row.dominant_subcategory_label
                                            "
                                            class="text-muted-foreground"
                                        >
                                            -
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t('reports.categoryAnalysis.table.empty') }}
                        </p>
                    </CardContent>
                </Card>
            </template>
        </ReportsLayout>
    </AppLayout>
</template>
