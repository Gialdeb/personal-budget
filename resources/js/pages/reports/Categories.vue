<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Layers3, Plus, Rows3, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ReportCategoriesCompositionChart from '@/components/reports/ReportCategoriesCompositionChart.vue';
import ReportCategoriesTrendChart from '@/components/reports/ReportCategoriesTrendChart.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportsLayout from '@/layouts/reports/Layout.vue';
import { reports } from '@/routes';
import { edit as editCategories } from '@/routes/categories';
import { categories as reportCategoriesRoute } from '@/routes/reports';
import { index as transactionsIndex } from '@/routes/transactions';
import type { DashboardAccountFilterOption } from '@/types/dashboard';
import type { BreadcrumbItem } from '@/types/navigation';
import type {
    ReportCategoriesPageProps,
    ReportCategoryFocusValue,
    ReportPeriodFilterValue,
} from '@/types/report';

const props = defineProps<ReportCategoriesPageProps>();
const { t } = useI18n();

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

const selectedFocus = ref<ReportCategoryFocusValue>(
    props.reportCategories.filters.focus,
);
const selectedYear = ref(String(props.reportCategories.filters.year));
const selectedPeriod = ref<ReportPeriodFilterValue>(
    props.reportCategories.filters.period,
);
const selectedMonth = ref(
    props.reportCategories.filters.month !== null
        ? String(props.reportCategories.filters.month)
        : '',
);
const selectedAccountUuid = ref(
    props.reportCategories.filters.account_uuid ?? '__all__',
);
const excludeInternal = ref(props.reportCategories.filters.exclude_internal);

watch(
    () => props.reportCategories.filters,
    (filters) => {
        selectedFocus.value = filters.focus;
        selectedYear.value = String(filters.year);
        selectedPeriod.value = filters.period;
        selectedMonth.value =
            filters.month !== null ? String(filters.month) : '';
        selectedAccountUuid.value = filters.account_uuid ?? '__all__';
        excludeInternal.value = filters.exclude_internal;
    },
    { deep: true },
);

const groupedAccountOptions = computed(() => {
    const paymentAccounts =
        props.reportCategories.filters.account_options.filter(
            (option) => option.account_type_code !== 'credit_card',
        );
    const creditCards = props.reportCategories.filters.account_options.filter(
        (option) => option.account_type_code === 'credit_card',
    );

    return [
        {
            key: 'payment_accounts',
            label: t('dashboard.filters.paymentAccountsGroup'),
            options: paymentAccounts,
        },
        {
            key: 'credit_cards',
            label: t('dashboard.filters.creditCardsGroup'),
            options: creditCards,
        },
    ].filter((group) => group.options.length > 0);
});

const showMonthFilter = computed(() => selectedPeriod.value !== 'annual');

watch(selectedPeriod, (period) => {
    if (period === 'annual') {
        selectedMonth.value = '';

        return;
    }

    if (selectedMonth.value === '') {
        const fallbackMonth = props.reportCategories.filters.month;
        selectedMonth.value =
            fallbackMonth !== null ? String(fallbackMonth) : '1';
    }
});

const focusPills = computed(() => props.reportCategories.filters.focus_options);

const unresolvedNote = computed(() => {
    const count = props.reportCategories.meta.unresolved_transactions_count;

    if (count <= 0) {
        return null;
    }

    return t('reports.overview.categoriesPage.unresolvedNote', { count });
});

const summaryHighlights = computed(() => [
    {
        key: 'main_category',
        label: t('reports.overview.categoriesPage.mainCategory'),
        value:
            props.reportCategories.summary.main_category_label ??
            t('reports.overview.categoriesPage.notAvailable'),
        helper: props.reportCategories.summary.main_category_share_label,
        icon: Sparkles,
    },
    {
        key: 'selected_share',
        label: t('reports.overview.categoriesPage.mainCategoryShare'),
        value:
            props.reportCategories.summary.main_category_share_label ??
            t('reports.overview.categoriesPage.notAvailable'),
        helper: props.reportCategories.summary.main_category_total,
        icon: Layers3,
    },
    {
        key: 'active_categories',
        label: t('reports.overview.categoriesPage.activeCategories'),
        value: String(props.reportCategories.summary.active_categories_count),
        helper: t('reports.overview.categoriesPage.categoriesTracked'),
        icon: Rows3,
    },
    {
        key: 'top_subcategory',
        label: t('reports.overview.categoriesPage.topSubcategory'),
        value:
            props.reportCategories.summary.top_subcategory_label ??
            t('reports.overview.categoriesPage.notAvailable'),
        helper: null,
        icon: ChevronRight,
    },
]);

function accountOptionLabel(option: DashboardAccountFilterOption): string {
    return option.bank_name
        ? `${option.label} · ${option.bank_name}`
        : option.label;
}

function buildQuery(): Record<string, string | number | boolean> {
    const query: Record<string, string | number | boolean> = {
        year: Number(selectedYear.value),
        period: selectedPeriod.value,
        focus: selectedFocus.value,
        exclude_internal: excludeInternal.value,
    };

    if (showMonthFilter.value && selectedMonth.value !== '') {
        query.month = Number(selectedMonth.value);
    }

    if (selectedAccountUuid.value !== '__all__') {
        query.account_uuid = selectedAccountUuid.value;
    }

    return query;
}

function visitWithCurrentFilters(): void {
    router.visit(reportCategoriesRoute({ query: buildQuery() }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function setFocus(focus: ReportCategoryFocusValue): void {
    selectedFocus.value = focus;
    visitWithCurrentFilters();
}

function applyFilters(): void {
    visitWithCurrentFilters();
}

function resetFilters(): void {
    selectedFocus.value = 'all';
    selectedYear.value = String(props.reportContext.year);
    selectedPeriod.value = 'annual';
    selectedMonth.value = '';
    selectedAccountUuid.value = '__all__';
    excludeInternal.value = true;

    router.visit(
        reportCategoriesRoute({
            query: {
                year: props.reportContext.year,
                period: 'annual',
                focus: 'all',
                exclude_internal: true,
            },
        }),
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function updateExcludeInternal(value: boolean): void {
    excludeInternal.value = value;
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
                                t('reports.overview.categoriesPage.areaLabel', {
                                    year: props.reportCategories.filters.year,
                                })
                            }}
                        </p>
                        <h1
                            class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                        >
                            {{ t('reports.overview.categoriesPage.title') }}
                        </h1>
                        <p
                            class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{
                                t('reports.overview.categoriesPage.description')
                            }}
                        </p>
                    </div>

                    <Button as-child class="hidden rounded-full md:inline-flex">
                        <Link :href="editCategories()">
                            <Plus class="mr-2 h-4 w-4" />
                            {{
                                t('reports.overview.categoriesPage.newCategory')
                            }}
                        </Link>
                    </Button>
                </div>
            </section>

            <section
                class="rounded-[30px] border border-white/70 bg-white/94 p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
            >
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="focus in focusPills"
                        :key="focus.value"
                        :variant="
                            selectedFocus === focus.value ? 'default' : 'ghost'
                        "
                        class="rounded-full"
                        @click="setFocus(focus.value)"
                    >
                        {{ focus.label }}
                    </Button>
                </div>

                <div
                    class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,190px)_minmax(0,220px)_minmax(0,220px)_minmax(0,1fr)]"
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
                                v-for="option in props.reportCategories.filters
                                    .available_years"
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
                                v-for="option in props.reportCategories.filters
                                    .period_options"
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
                                v-for="option in props.reportCategories.filters
                                    .month_options"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="selectedAccountUuid">
                        <SelectTrigger
                            class="h-11 rounded-full bg-white/90 dark:bg-white/5"
                        >
                            <SelectValue
                                :placeholder="t('reports.filters.resource')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">
                                {{ t('reports.filters.allResources') }}
                            </SelectItem>
                            <SelectGroup
                                v-for="group in groupedAccountOptions"
                                :key="group.key"
                            >
                                <SelectLabel>{{ group.label }}</SelectLabel>
                                <SelectItem
                                    v-for="option in group.options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ accountOptionLabel(option) }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <div
                    class="mt-4 flex flex-col gap-3 border-t border-border/70 pt-4 xl:flex-row xl:items-center xl:justify-between"
                >
                    <label
                        class="flex items-center gap-3 rounded-2xl border border-slate-200/80 px-4 py-3 dark:border-white/10"
                    >
                        <Checkbox
                            :checked="excludeInternal"
                            @update:checked="
                                updateExcludeInternal(Boolean($event))
                            "
                        />
                        <span
                            class="text-sm text-slate-700 dark:text-slate-200"
                        >
                            {{
                                t(
                                    'reports.overview.categoriesPage.excludeInternal',
                                )
                            }}
                        </span>
                    </label>

                    <div class="flex gap-2">
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
                </div>

                <p
                    v-if="!showMonthFilter"
                    class="mt-3 text-xs leading-5 text-muted-foreground"
                >
                    {{ t('reports.filters.monthDisabledAnnual') }}
                </p>
            </section>

            <p
                class="hidden text-sm font-medium text-slate-700 md:block dark:text-slate-200"
            >
                {{ t('reports.overview.categoriesPage.allCategories') }}
            </p>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card
                    v-for="item in summaryHighlights"
                    :key="item.key"
                    class="rounded-[24px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
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
                                {{ item.value }}
                            </p>
                            <p
                                v-if="item.helper"
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                <SensitiveValue
                                    v-if="item.key === 'selected_share'"
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

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_420px]">
                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader
                        class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                    >
                        <div class="space-y-2">
                            <CardTitle
                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t(
                                        'reports.overview.categoriesPage.totalComposition',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.overview.categoriesPage.compositionHint',
                                    )
                                }}
                            </CardDescription>
                        </div>
                        <div class="space-y-1 md:text-right">
                            <p
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'reports.overview.categoriesPage.selectedTotal',
                                    )
                                }}
                            </p>
                            <p
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    props.reportCategories.summary
                                        .total_selected
                                }}
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="hidden md:block">
                            <ReportCategoriesCompositionChart
                                :nodes="
                                    props.reportCategories.composition
                                        .sunburst_nodes
                                "
                                :currency="props.reportCategories.currency"
                                :empty-label="
                                    t(
                                        'reports.overview.categoriesPage.emptyComposition',
                                    )
                                "
                                variant="sunburst"
                            />
                        </div>
                        <div class="md:hidden">
                            <ReportCategoriesCompositionChart
                                :nodes="
                                    props.reportCategories.composition
                                        .treemap_nodes
                                "
                                :currency="props.reportCategories.currency"
                                :empty-label="
                                    t(
                                        'reports.overview.categoriesPage.emptyComposition',
                                    )
                                "
                                variant="treemap"
                            />
                        </div>
                        <p
                            v-if="unresolvedNote"
                            class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                        >
                            {{ unresolvedNote }}
                        </p>
                        <p
                            v-if="
                                props.reportCategories.summary
                                    .total_selected_raw === 0
                            "
                            class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                        >
                            {{
                                t(
                                    'reports.overview.categoriesPage.emptySummary',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle
                            class="text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                t(
                                    'reports.overview.categoriesPage.topCategories',
                                )
                            }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'reports.overview.categoriesPage.topCategoriesHint',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="item in props.reportCategories
                                .top_categories"
                            :key="item.key"
                            class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-1 h-3 w-3 rounded-full"
                                    :style="{ backgroundColor: item.color }"
                                />
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ item.label }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    item.subcategories_count ===
                                                    0
                                                        ? t(
                                                              'reports.overview.categoriesPage.noSubcategory',
                                                          )
                                                        : item.subcategories_count ===
                                                            1
                                                          ? t(
                                                                'reports.overview.categoriesPage.oneSubcategory',
                                                            )
                                                          : t(
                                                                'reports.overview.categoriesPage.subcategories',
                                                                {
                                                                    count: item.subcategories_count,
                                                                },
                                                            )
                                                }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p
                                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                <SensitiveValue
                                                    :value="item.total"
                                                />
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{ item.share_label }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-3 h-2 rounded-full bg-slate-100 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-full rounded-full"
                                            :style="{
                                                width: `${Math.max(item.share_percentage, 4)}%`,
                                                backgroundColor: item.color,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                props.reportCategories.top_categories.length ===
                                0
                            "
                            class="rounded-2xl border border-dashed border-slate-300/80 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                        >
                            {{
                                t(
                                    'reports.overview.categoriesPage.emptyComposition',
                                )
                            }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <section
                class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.92fr)]"
            >
                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle
                            class="text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                t('reports.overview.categoriesPage.trendTitle')
                            }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'reports.overview.categoriesPage.trendDescription',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ReportCategoriesTrendChart
                            :chart="props.reportCategories.trend"
                            :currency="props.reportCategories.currency"
                            :empty-label="
                                t('reports.overview.categoriesPage.emptyTrend')
                            "
                        />
                    </CardContent>
                </Card>

                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader
                        class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                    >
                        <div class="space-y-2">
                            <CardTitle
                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t(
                                        'reports.overview.categoriesPage.recentTitle',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.overview.categoriesPage.recentDescription',
                                    )
                                }}
                            </CardDescription>
                        </div>
                        <Button as-child variant="outline" class="rounded-full">
                            <Link :href="transactionsIndex()">
                                {{
                                    t('reports.overview.categoriesPage.seeAll')
                                }}
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="movement in props.reportCategories
                                .recent_transactions"
                            :key="movement.uuid"
                            class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <p
                                class="w-14 shrink-0 text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ movement.date_label }}
                            </p>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ movement.description }}
                                </p>
                                <div
                                    class="mt-1 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :style="{
                                            backgroundColor: movement.color,
                                        }"
                                    />
                                    <span class="truncate">{{
                                        movement.category_label
                                    }}</span>
                                </div>
                            </div>
                            <p
                                class="shrink-0 text-sm font-semibold"
                                :class="
                                    movement.amount_raw < 0
                                        ? 'text-rose-600 dark:text-rose-300'
                                        : 'text-emerald-700 dark:text-emerald-300'
                                "
                            >
                                <SensitiveValue :value="movement.amount" />
                            </p>
                        </div>

                        <div
                            v-if="
                                props.reportCategories.recent_transactions
                                    .length === 0
                            "
                            class="rounded-2xl border border-dashed border-slate-300/80 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                        >
                            {{
                                t('reports.overview.categoriesPage.emptyRecent')
                            }}
                        </div>
                    </CardContent>
                </Card>
            </section>
        </ReportsLayout>
    </AppLayout>
</template>
