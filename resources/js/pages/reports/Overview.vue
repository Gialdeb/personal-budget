<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowDownRight,
    ArrowRight,
    ArrowUp,
    ArrowUpRight,
    CalendarRange,
    ChartNoAxesCombined,
    ReceiptText,
    Scale,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ReportOverviewComparisonChart from '@/components/reports/ReportOverviewComparisonChart.vue';
import ReportOverviewTrendChart from '@/components/reports/ReportOverviewTrendChart.vue';
import { Badge } from '@/components/ui/badge';
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
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportsLayout from '@/layouts/reports/Layout.vue';
import { reports } from '@/routes';
import { kpis as reportKpis } from '@/routes/reports';
import type {
    BreadcrumbItem,
    DashboardAccountFilterOption,
    ReportMetricCountComparison,
    ReportMetricMoneyComparison,
    ReportOverviewPageProps,
    ReportPeriodFilterValue,
} from '@/types';

const props = defineProps<ReportOverviewPageProps>();
const { locale, t } = useI18n();

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

const selectedYear = ref(String(props.reportOverview.filters.year));
const selectedPeriod = ref<ReportPeriodFilterValue>(
    props.reportOverview.filters.period,
);
const selectedMonth = ref(
    props.reportOverview.filters.month !== null
        ? String(props.reportOverview.filters.month)
        : '',
);
const selectedAccountUuid = ref(
    props.reportOverview.filters.account_uuid ?? '__all__',
);

watch(
    () => props.reportOverview.filters,
    (filters) => {
        selectedYear.value = String(filters.year);
        selectedPeriod.value = filters.period;
        selectedMonth.value =
            filters.month !== null ? String(filters.month) : '';
        selectedAccountUuid.value = filters.account_uuid ?? '__all__';
    },
    { deep: true },
);

const groupedAccountOptions = computed(() => {
    const paymentAccounts = props.reportOverview.filters.account_options.filter(
        (option) => option.account_type_code !== 'credit_card',
    );
    const creditCards = props.reportOverview.filters.account_options.filter(
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

const localizedPeriodOptions = computed(() =>
    props.reportOverview.filters.period_options.map((option) => ({
        ...option,
        label: t(
            `reports.filters.periods.${
                (
                    {
                        annual: 'annual',
                        monthly: 'monthly',
                        last_3_months: 'lastThreeMonths',
                        last_6_months: 'lastSixMonths',
                        ytd: 'ytd',
                    } as const
                )[option.value]
            }`,
        ),
    })),
);

const localizedMonthOptions = computed(() =>
    props.reportOverview.filters.month_options.map((option) => ({
        ...option,
        label: t(`app.periods.months.short.${option.value}`),
    })),
);

const showMonthFilter = computed(() => selectedPeriod.value !== 'annual');

watch(selectedPeriod, (period) => {
    if (period === 'annual') {
        selectedMonth.value = '';

        return;
    }

    if (selectedMonth.value === '') {
        const fallbackMonth = props.reportOverview.filters.month;
        selectedMonth.value =
            fallbackMonth !== null ? String(fallbackMonth) : '1';
    }
});

const transactionsCountLabel = computed(() => {
    const count = props.reportOverview.kpis.transactions_count;

    if (count === 1) {
        return `1 ${t('reports.overview.kpis.transactionUnit')}`;
    }

    return `${count} ${t('reports.overview.kpis.transactionsUnit')}`;
});

const hasOverviewData = computed(
    () =>
        props.reportOverview.kpis.transactions_count > 0 ||
        props.reportOverview.meta.unresolved_transactions_count > 0,
);

function comparisonSummary(
    comparison: ReportMetricMoneyComparison | ReportMetricCountComparison,
): string {
    const deltaLabel =
        'delta_formatted' in comparison
            ? comparison.delta_formatted
            : comparison.delta_label;

    if (comparison.delta_percentage_label !== null) {
        return `${deltaLabel} · ${comparison.delta_percentage_label}`;
    }

    return deltaLabel;
}

function comparisonTone(direction: 'up' | 'down' | 'neutral'): string {
    if (direction === 'up') {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    if (direction === 'down') {
        return 'text-rose-700 dark:text-rose-300';
    }

    return 'text-slate-500 dark:text-slate-400';
}

function comparisonIcon(direction: 'up' | 'down' | 'neutral') {
    if (direction === 'up') {
        return ArrowUp;
    }

    if (direction === 'down') {
        return ArrowDown;
    }

    return ArrowRight;
}

const metricCards = computed(() => [
    {
        key: 'income',
        title: t('reports.overview.kpis.income'),
        value: props.reportOverview.kpis.income_total,
        helper: t('reports.overview.kpis.periodTotal'),
        comparison: props.reportOverview.kpis.income_total_comparison,
        icon: ArrowUpRight,
        tone: 'border-emerald-200/80 bg-emerald-50/80 text-emerald-950 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-50',
        iconTone:
            'border-emerald-300/70 bg-white/65 text-emerald-700 dark:border-emerald-400/25 dark:bg-white/10 dark:text-emerald-200',
    },
    {
        key: 'expense',
        title: t('reports.overview.kpis.expense'),
        value: props.reportOverview.kpis.expense_total,
        helper: t('reports.overview.kpis.periodTotal'),
        comparison: props.reportOverview.kpis.expense_total_comparison,
        icon: ArrowDownRight,
        tone: 'border-rose-200/80 bg-rose-50/80 text-rose-950 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-50',
        iconTone:
            'border-rose-300/70 bg-white/65 text-rose-700 dark:border-rose-400/25 dark:bg-white/10 dark:text-rose-200',
    },
    {
        key: 'net',
        title: t('reports.overview.kpis.net'),
        value: props.reportOverview.kpis.net_total,
        helper: t('reports.overview.kpis.periodBalance'),
        comparison: props.reportOverview.kpis.net_total_comparison,
        icon: Wallet,
        tone: 'border-sky-200/80 bg-sky-50/80 text-sky-950 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-50',
        iconTone:
            'border-sky-300/70 bg-white/65 text-sky-700 dark:border-sky-400/25 dark:bg-white/10 dark:text-sky-200',
    },
    {
        key: 'transactions',
        title: t('reports.overview.kpis.transactions'),
        value: transactionsCountLabel.value,
        helper: t('reports.overview.kpis.includedMovements'),
        comparison: props.reportOverview.kpis.transactions_count_comparison,
        icon: ReceiptText,
        tone: 'border-slate-200/80 bg-slate-50/90 text-slate-950 dark:border-slate-500/20 dark:bg-slate-500/10 dark:text-slate-50',
        iconTone:
            'border-slate-300/70 bg-white/65 text-slate-700 dark:border-slate-400/25 dark:bg-white/10 dark:text-slate-200',
    },
    {
        key: 'average_net',
        title: t('reports.overview.kpis.averageNet'),
        value: props.reportOverview.kpis.average_net,
        helper: props.reportOverview.kpis.average_net_interval_label,
        comparison: props.reportOverview.kpis.average_net_comparison,
        icon: Scale,
        tone: 'border-amber-200/80 bg-amber-50/90 text-amber-950 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-50',
        iconTone:
            'border-amber-300/70 bg-white/65 text-amber-700 dark:border-amber-400/25 dark:bg-white/10 dark:text-amber-200',
    },
    {
        key: 'best_period',
        title: t('reports.overview.kpis.bestPeriod'),
        value:
            props.reportOverview.kpis.best_period_value ??
            t('reports.overview.kpis.notAvailable'),
        helper:
            props.reportOverview.kpis.best_period_label ??
            t('reports.overview.kpis.notAvailable'),
        comparison: null,
        icon: ChartNoAxesCombined,
        tone: 'border-violet-200/80 bg-violet-50/90 text-violet-950 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-50',
        iconTone:
            'border-violet-300/70 bg-white/65 text-violet-700 dark:border-violet-400/25 dark:bg-white/10 dark:text-violet-200',
    },
]);

const reportStatusBadges = computed(() => [
    localizedPeriodSummary.value,
    props.reportOverview.meta.scope_label,
]);

const localizedPeriodSummary = computed(() => {
    const year = props.reportOverview.filters.year;
    const month = props.reportOverview.filters.month;
    const localizedMonth =
        month === null
            ? null
            : new Intl.DateTimeFormat(
                  locale.value === 'it' ? 'it-IT' : 'en-US',
                  {
                      month: 'long',
                  },
              ).format(new Date(year, month - 1, 1));

    switch (props.reportOverview.filters.period) {
        case 'annual':
            return t('reports.filters.periodSummaries.annual', { year });
        case 'last_3_months':
            return t('reports.filters.periodSummaries.lastThreeMonths', {
                month: localizedMonth,
                year,
            });
        case 'last_6_months':
            return t('reports.filters.periodSummaries.lastSixMonths', {
                month: localizedMonth,
                year,
            });
        case 'ytd':
            return t('reports.filters.periodSummaries.ytd', {
                month: localizedMonth,
                year,
            });
        default:
            return month === null || localizedMonth === null
                ? String(year)
                : `${localizedMonth} ${year}`;
    }
});

const snapshotBuckets = computed(() => {
    const buckets = props.reportOverview.buckets;
    const observedBuckets = buckets.filter(
        (bucket) =>
            bucket.income_total_raw !== 0 ||
            bucket.expense_total_raw !== 0 ||
            bucket.net_total_raw !== 0,
    );

    return observedBuckets.slice(-4).reverse();
});

function accountOptionLabel(option: DashboardAccountFilterOption): string {
    return option.bank_name
        ? `${option.label} · ${option.bank_name}`
        : option.label;
}

function buildQuery(): Record<string, string | number> {
    const query: Record<string, string | number> = {
        year: Number(selectedYear.value),
        period: selectedPeriod.value,
    };

    if (showMonthFilter.value && selectedMonth.value !== '') {
        query.month = Number(selectedMonth.value);
    }

    if (selectedAccountUuid.value !== '__all__') {
        query.account_uuid = selectedAccountUuid.value;
    }

    return query;
}

function defaultFilterYear(): number {
    const availableYears = props.reportOverview.filters.available_years.map(
        (option) => Number(option.value),
    );
    const currentYear = new Date().getFullYear();

    if (availableYears.includes(currentYear)) {
        return currentYear;
    }

    return availableYears.at(-1) ?? props.reportOverview.filters.year;
}

function defaultFilterMonth(year: number): number {
    const now = new Date();

    return year === now.getFullYear() ? now.getMonth() + 1 : 12;
}

function applyFilters(): void {
    router.visit(reportKpis({ query: buildQuery() }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function resetFilters(): void {
    const year = defaultFilterYear();
    const month = defaultFilterMonth(year);

    selectedYear.value = String(year);
    selectedPeriod.value = 'monthly';
    selectedMonth.value = String(month);
    selectedAccountUuid.value = '__all__';

    router.visit(
        reportKpis({
            query: {
                year,
                period: 'monthly',
                month,
            },
        }),
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        },
    );
}
</script>

<template>
    <Head :title="props.activeReportSection.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ReportsLayout :report-sections="props.reportSections">
            <section
                class="rounded-[32px] border border-white/70 bg-[radial-gradient(circle_at_top_left,rgba(15,23,42,0.08),transparent_34%),linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,249,255,0.94))] p-5 shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,rgba(148,163,184,0.16),transparent_34%),linear-gradient(180deg,rgba(18,24,39,0.98),rgba(11,18,32,0.94))]"
            >
                <div
                    class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="badge in reportStatusBadges"
                                :key="badge"
                                variant="outline"
                                class="rounded-full bg-white/75 px-3 py-1 text-xs dark:bg-white/5"
                            >
                                {{ badge }}
                            </Badge>
                        </div>
                        <div class="space-y-2">
                            <h1
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                            >
                                {{ props.activeReportSection.title }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('reports.overview.hero.description') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="rounded-[30px] border border-white/70 bg-white/94 p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                data-test="reports-overview-filter-bar"
            >
                <div
                    class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div class="space-y-1">
                        <h2
                            class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                        >
                            {{ t('reports.filters.title') }}
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ t('reports.filters.description') }}
                        </p>
                    </div>

                    <div
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,190px)_minmax(0,230px)_minmax(0,220px)_minmax(0,1fr)]"
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
                                    v-for="option in props.reportOverview
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
                                    v-for="option in localizedPeriodOptions"
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
                                    v-for="option in localizedMonthOptions"
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
                </div>

                <div
                    class="mt-4 flex flex-col gap-3 border-t border-border/70 pt-4 md:flex-row md:items-center md:justify-between"
                >
                    <div
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1"
                        >
                            <CalendarRange class="h-3.5 w-3.5" />
                            {{ localizedPeriodSummary }}
                        </span>
                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1"
                        >
                            <Wallet class="h-3.5 w-3.5" />
                            {{ props.reportOverview.meta.scope_label }}
                        </span>
                    </div>

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

            <section
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                data-test="reports-overview-kpis"
            >
                <Card
                    v-for="card in metricCards"
                    :key="card.key"
                    :class="['rounded-[28px] border shadow-sm', card.tone]"
                >
                    <CardContent class="p-5">
                        <div
                            :class="[
                                'flex h-11 w-11 items-center justify-center rounded-2xl border',
                                card.iconTone,
                            ]"
                        >
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                        <p class="mt-4 text-sm font-medium opacity-80">
                            {{ card.title }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ card.value }}
                        </p>
                        <p class="mt-1 text-xs opacity-75">
                            {{ card.helper }}
                        </p>
                        <div
                            v-if="card.comparison"
                            class="mt-4 rounded-2xl border border-black/5 bg-white/55 px-3 py-2 text-xs dark:border-white/10 dark:bg-white/[0.05]"
                        >
                            <div
                                :class="[
                                    'flex items-center gap-1.5 font-semibold',
                                    comparisonTone(card.comparison.direction),
                                ]"
                            >
                                <component
                                    :is="
                                        comparisonIcon(
                                            card.comparison.direction,
                                        )
                                    "
                                    class="h-3.5 w-3.5"
                                />
                                <span>{{
                                    comparisonSummary(card.comparison)
                                }}</span>
                            </div>
                            <p class="mt-1 opacity-75">
                                {{
                                    t(
                                        'reports.overview.kpis.previousPeriodHint',
                                        {
                                            period: props.reportOverview.meta
                                                .previous_period_label,
                                        },
                                    )
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section
                v-if="props.reportOverview.meta.coverage_note"
                class="rounded-[24px] border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-100"
            >
                {{ props.reportOverview.meta.coverage_note }}
            </section>

            <section
                v-if="!hasOverviewData"
                class="rounded-[24px] border border-dashed border-slate-300/80 bg-slate-50/80 px-5 py-6 text-sm leading-6 text-slate-600 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300"
            >
                {{ t('reports.overview.emptyState') }}
            </section>

            <section
                class="grid gap-4 2xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]"
            >
                <ReportOverviewTrendChart
                    :kpis="props.reportOverview.kpis"
                    :currency="props.reportOverview.currency"
                    :title="t('reports.overview.distribution.title')"
                    :description="
                        t('reports.overview.distribution.description')
                    "
                    :empty-label="t('reports.overview.distribution.empty')"
                />

                <Card
                    class="rounded-[30px] border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(249,250,252,0.94))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(18,24,39,0.98),rgba(11,18,32,0.94))]"
                >
                    <CardHeader>
                        <CardTitle class="text-xl tracking-tight">
                            {{ t('reports.overview.snapshot.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('reports.overview.snapshot.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="bucket in snapshotBuckets"
                            :key="bucket.key"
                            class="rounded-2xl border border-border/70 bg-white/80 px-4 py-3 dark:bg-white/[0.03]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{ bucket.label }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                'reports.overview.snapshot.netLabel',
                                            )
                                        }}
                                    </p>
                                </div>
                                <p
                                    class="text-sm font-semibold"
                                    :class="
                                        bucket.net_total_raw >= 0
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-rose-700 dark:text-rose-300'
                                    "
                                >
                                    {{ bucket.net_total }}
                                </p>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div
                                    class="rounded-xl bg-emerald-50 px-3 py-2 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100"
                                >
                                    <span class="block opacity-70">{{
                                        t('reports.overview.kpis.income')
                                    }}</span>
                                    <span class="mt-1 block font-semibold">{{
                                        bucket.income_total
                                    }}</span>
                                </div>
                                <div
                                    class="rounded-xl bg-rose-50 px-3 py-2 text-rose-900 dark:bg-rose-500/10 dark:text-rose-100"
                                >
                                    <span class="block opacity-70">{{
                                        t('reports.overview.kpis.expense')
                                    }}</span>
                                    <span class="mt-1 block font-semibold">{{
                                        bucket.expense_total
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="snapshotBuckets.length === 0"
                            class="rounded-2xl border border-dashed border-slate-300/80 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                        >
                            {{ t('reports.overview.snapshot.empty') }}
                        </div>
                    </CardContent>
                </Card>
            </section>

            <ReportOverviewComparisonChart
                :chart="props.reportOverview.comparison"
                :currency="props.reportOverview.currency"
                :title="t('reports.overview.comparison.title')"
                :description="t('reports.overview.comparison.description')"
                :empty-label="t('reports.overview.comparison.empty')"
            />
        </ReportsLayout>
    </AppLayout>
</template>
