<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    Download,
    Plus,
    Trophy,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ReportAccountsBalanceChart from '@/components/reports/ReportAccountsBalanceChart.vue';
import ReportAccountsCashFlowChart from '@/components/reports/ReportAccountsCashFlowChart.vue';
import ReportAccountsDistributionChart from '@/components/reports/ReportAccountsDistributionChart.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import ReportsLayout from '@/layouts/reports/Layout.vue';
import { reports } from '@/routes';
import { edit as accountsEdit } from '@/routes/accounts';
import { accounts as reportAccountsRoute } from '@/routes/reports';
import { exportMethod as exportReportAccountsRoute } from '@/routes/reports/accounts';
import type { BreadcrumbItem } from '@/types/navigation';
import type {
    ReportAccountCard,
    ReportAccountMetric,
    ReportAccountsPageProps,
    ReportPeriodFilterValue,
} from '@/types/report';

const props = defineProps<ReportAccountsPageProps>();
const { t } = useI18n();
const ALL_ACCOUNTS_VALUE = 'all';

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

const selectedYear = ref(String(props.reportAccounts.filters.year));
const selectedPeriod = ref<ReportPeriodFilterValue>(
    props.reportAccounts.filters.period,
);
const selectedMonth = ref(
    props.reportAccounts.filters.month !== null
        ? String(props.reportAccounts.filters.month)
        : '',
);
const selectedAccountUuid = ref(
    props.reportAccounts.filters.account_uuid ?? ALL_ACCOUNTS_VALUE,
);

watch(
    () => props.reportAccounts.filters,
    (filters) => {
        selectedYear.value = String(filters.year);
        selectedPeriod.value = filters.period;
        selectedMonth.value =
            filters.month !== null ? String(filters.month) : '';
        selectedAccountUuid.value = filters.account_uuid ?? ALL_ACCOUNTS_VALUE;
    },
    { deep: true },
);

const showMonthFilter = computed(() => selectedPeriod.value !== 'annual');
const selectedAccount = computed<ReportAccountCard | null>(
    () =>
        props.reportAccounts.accounts.find(
            (account) => account.uuid === selectedAccountUuid.value,
        ) ??
        props.reportAccounts.accounts[0] ??
        null,
);
const maxTopCategory = computed(() =>
    Math.max(
        1,
        ...props.reportAccounts.top_categories.map((item) => item.total_raw),
    ),
);
const comparisonReferenceLabel = computed(() =>
    selectedPeriod.value === 'annual'
        ? t('reports.overview.accountsPage.vsPreviousYear')
        : t('reports.overview.accountsPage.vsPreviousPeriod'),
);
const accountKpiCards = computed(() => [
    {
        key: 'income',
        label: t('reports.overview.accountsPage.income'),
        value: props.reportAccounts.kpis.income.value,
        comparison: comparisonParts(props.reportAccounts.kpis.income),
        icon: ArrowUpRight,
        accentClass: 'from-emerald-500 to-emerald-400',
        iconClass: 'text-emerald-600 dark:text-emerald-300',
        deltaClass: 'text-emerald-600 dark:text-emerald-300',
        borderClass: 'border-l-emerald-500',
    },
    {
        key: 'expense',
        label: t('reports.overview.accountsPage.expense'),
        value: props.reportAccounts.kpis.expense.value,
        comparison: comparisonParts(props.reportAccounts.kpis.expense),
        icon: ArrowDownRight,
        accentClass: 'from-rose-500 to-red-400',
        iconClass: 'text-rose-600 dark:text-rose-300',
        deltaClass: 'text-rose-600 dark:text-rose-300',
        borderClass: 'border-l-rose-500',
    },
    {
        key: 'net',
        label: t('reports.overview.accountsPage.net'),
        value: props.reportAccounts.kpis.net.value,
        comparison: comparisonParts(props.reportAccounts.kpis.net),
        icon: Wallet,
        accentClass: 'from-indigo-500 to-blue-500',
        iconClass: 'text-indigo-600 dark:text-indigo-300',
        deltaClass:
            (props.reportAccounts.kpis.net.delta_raw ?? 0) >= 0
                ? 'text-emerald-600 dark:text-emerald-300'
                : 'text-rose-600 dark:text-rose-300',
        borderClass: 'border-l-indigo-500',
    },
    {
        key: 'best',
        label: t('reports.overview.accountsPage.bestPeriod'),
        value:
            props.reportAccounts.kpis.best_period.summary ??
            t('reports.overview.accountsPage.noBestPeriod'),
        comparison: {
            leading: '',
            rest:
                props.reportAccounts.kpis.best_period.worst_label !== null &&
                props.reportAccounts.kpis.best_period.worst_value !== null
                    ? `${t('reports.overview.accountsPage.worstPeriod')}: ${props.reportAccounts.kpis.best_period.worst_label} · ${props.reportAccounts.kpis.best_period.worst_value}`
                    : t('reports.overview.accountsPage.comparisonUnavailable'),
            available: false,
        },
        icon: Trophy,
        accentClass: 'from-amber-500 to-orange-400',
        iconClass: 'text-amber-600 dark:text-amber-300',
        deltaClass: 'text-slate-500 dark:text-slate-400',
        borderClass: 'border-l-amber-500',
    },
]);

watch(selectedPeriod, (period) => {
    if (period === 'annual') {
        selectedMonth.value = '';

        return;
    }

    if (selectedMonth.value === '') {
        selectedMonth.value = String(props.reportContext.month ?? 1);
    }
});

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

    return query;
}

function visitWithCurrentFilters(): void {
    router.visit(reportAccountsRoute({ query: buildQuery() }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function exportWithCurrentFilters(): void {
    window.location.assign(
        exportReportAccountsRoute.url({ query: buildQuery() }),
    );
}

function visitCreateAccount(): void {
    router.visit(
        accountsEdit({
            query: {
                create: '1',
            },
        }).url,
    );
}

function selectAccount(uuid: string): void {
    selectedAccountUuid.value = uuid;
    visitWithCurrentFilters();
}

function resetFilters(): void {
    selectedYear.value = String(props.reportContext.year);
    selectedPeriod.value = 'annual';
    selectedMonth.value = '';
    selectedAccountUuid.value = ALL_ACCOUNTS_VALUE;
    visitWithCurrentFilters();
}

function comparisonParts(metric: ReportAccountMetric): {
    leading: string;
    rest: string;
    available: boolean;
} {
    if (
        !metric.comparison_available ||
        metric.delta_percentage_label === null
    ) {
        return {
            leading: '',
            rest: t('reports.overview.accountsPage.comparisonUnavailable'),
            available: false,
        };
    }

    return {
        leading: metric.delta_percentage_label,
        rest: comparisonReferenceLabel.value,
        available: true,
    };
}

function sparklinePoints(values: number[], width = 132, height = 34): string {
    if (values.length === 0) {
        return '';
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const spread = Math.max(max - min, 1);

    return values
        .map((value, index) => {
            const x =
                values.length === 1
                    ? width
                    : (index / (values.length - 1)) * width;
            const y = height - ((value - min) / spread) * height;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}

function sparklineAreaPoints(
    values: number[],
    width = 132,
    height = 34,
): string {
    const points = sparklinePoints(values, width, height);

    if (points === '') {
        return '';
    }

    return `0,${height} ${points} ${width},${height}`;
}

function shareTrackWidth(share: number): string {
    return `${Math.max(3, Math.min(100, share))}%`;
}
</script>

<template>
    <Head :title="t('reports.overview.accountsPage.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ReportsLayout :report-sections="props.reportSections">
            <section class="space-y-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-xs font-semibold tracking-[0.24em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{
                                t('reports.overview.accountsPage.areaLabel', {
                                    year: props.reportAccounts.filters.year,
                                })
                            }}
                        </p>
                        <h1
                            class="mt-2 text-3xl font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ t('reports.overview.accountsPage.title') }}
                        </h1>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ t('reports.overview.accountsPage.description') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            class="rounded-2xl"
                            @click="visitCreateAccount"
                        >
                            <Plus class="size-4" />
                            {{ t('reports.overview.accountsPage.addAccount') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="rounded-2xl"
                            :disabled="
                                props.reportAccounts.accounts.length === 0
                            "
                            @click="exportWithCurrentFilters"
                        >
                            <Download class="size-4" />
                            {{ t('reports.overview.accountsPage.export') }}
                        </Button>
                    </div>
                </div>

                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardContent
                        class="grid gap-3 p-4 md:grid-cols-[repeat(4,minmax(0,1fr))_auto] md:items-end"
                    >
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ t('reports.filters.year') }}
                            </span>
                            <Select v-model="selectedYear">
                                <SelectTrigger class="rounded-2xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in props.reportAccounts
                                            .filters.available_years"
                                        :key="option.value"
                                        :value="String(option.value)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </label>

                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ t('reports.filters.period') }}
                            </span>
                            <Select v-model="selectedPeriod">
                                <SelectTrigger class="rounded-2xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in props.reportAccounts
                                            .filters.period_options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </label>

                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ t('reports.filters.referenceMonth') }}
                            </span>
                            <Select
                                v-model="selectedMonth"
                                :disabled="!showMonthFilter"
                            >
                                <SelectTrigger class="rounded-2xl">
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'reports.filters.monthDisabledAnnual',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in props.reportAccounts
                                            .filters.month_options"
                                        :key="option.value"
                                        :value="String(option.value)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </label>

                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ t('reports.overview.accountsPage.account') }}
                            </span>
                            <Select v-model="selectedAccountUuid">
                                <SelectTrigger class="rounded-2xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="ALL_ACCOUNTS_VALUE">
                                        {{
                                            t(
                                                'reports.overview.accountsPage.allAccounts',
                                            )
                                        }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in props.reportAccounts
                                            .filters.account_options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </label>

                        <div class="flex gap-2">
                            <Button
                                class="rounded-2xl"
                                @click="visitWithCurrentFilters"
                            >
                                {{ t('reports.filters.apply') }}
                            </Button>
                            <Button
                                variant="ghost"
                                class="rounded-2xl"
                                @click="resetFilters"
                            >
                                {{ t('reports.filters.reset') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div
                    class="sticky top-0 z-20 -mx-4 flex gap-3 overflow-x-auto border-y border-slate-200/80 bg-slate-50/95 px-4 py-3 backdrop-blur md:hidden dark:border-slate-800 dark:bg-slate-950/95"
                >
                    <button
                        v-for="account in props.reportAccounts.accounts"
                        :key="account.uuid"
                        type="button"
                        class="flex min-w-[148px] items-center gap-2 rounded-2xl border bg-white px-3 py-2 text-left shadow-sm dark:bg-slate-900"
                        :class="
                            account.uuid === selectedAccountUuid
                                ? 'border-slate-900 dark:border-slate-100'
                                : 'border-slate-200 dark:border-slate-800'
                        "
                        @click="selectAccount(account.uuid)"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-xl text-xs font-bold"
                            :style="{
                                backgroundColor: `${account.color}22`,
                                color: account.color,
                            }"
                        >
                            {{ account.initials }}
                        </span>
                        <span class="min-w-0">
                            <span
                                class="block truncate text-xs font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ account.name }}
                            </span>
                            <span
                                class="block text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                {{ account.current_balance }}
                            </span>
                        </span>
                    </button>
                </div>

                <div class="grid gap-4 xl:grid-cols-4">
                    <button
                        v-for="account in props.reportAccounts.accounts"
                        :key="account.uuid"
                        type="button"
                        class="group relative overflow-hidden rounded-[28px] border bg-white/92 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-slate-950/70"
                        :class="
                            account.uuid === selectedAccountUuid
                                ? 'border-[var(--account-color)] ring-2 ring-slate-900/10 dark:ring-white/15'
                                : 'border-white/70 dark:border-white/10'
                        "
                        :style="{ '--account-color': account.color }"
                        @click="selectAccount(account.uuid)"
                    >
                        <span
                            class="absolute inset-x-4 top-0 h-1 rounded-b-full"
                            :style="{ backgroundColor: account.color }"
                        />
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex size-11 items-center justify-center rounded-2xl text-sm font-bold"
                                    :style="{
                                        backgroundColor: `${account.color}1f`,
                                        color: account.color,
                                    }"
                                >
                                    {{ account.initials }}
                                </span>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ account.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ account.type_label }}
                                    </p>
                                </div>
                            </div>
                            <Building2
                                class="size-4 shrink-0 text-slate-400 transition group-hover:text-slate-600 dark:group-hover:text-slate-200"
                            />
                        </div>
                        <div
                            class="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_132px] sm:items-end xl:grid-cols-1"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-400 uppercase"
                                >
                                    {{
                                        t(
                                            'reports.overview.accountsPage.currentBalance',
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-2xl font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ account.current_balance }}
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="
                                            (account.delta_percentage ?? 0) >= 0
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
                                        "
                                    >
                                        {{ account.delta_label ?? '0,0%' }}
                                    </span>
                                    <span
                                        class="text-[11px] text-slate-500 dark:text-slate-400"
                                    >
                                        {{ account.share_label }}
                                        {{
                                            t(
                                                'reports.overview.accountsPage.assetShare',
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                            <svg
                                class="h-12 w-full overflow-visible text-[var(--account-color)]"
                                viewBox="0 0 132 40"
                                preserveAspectRatio="none"
                                aria-hidden="true"
                            >
                                <polygon
                                    :points="
                                        sparklineAreaPoints(
                                            account.sparkline,
                                            132,
                                            38,
                                        )
                                    "
                                    fill="currentColor"
                                    opacity="0.1"
                                />
                                <polyline
                                    :points="
                                        sparklinePoints(
                                            account.sparkline,
                                            132,
                                            38,
                                        )
                                    "
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                    </button>
                </div>

                <section
                    class="grid gap-4 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.35fr)]"
                >
                    <Card
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-start gap-4">
                                <div
                                    class="w-1 self-stretch rounded-full"
                                    :style="{
                                        backgroundColor:
                                            selectedAccount?.color ?? '#64748b',
                                    }"
                                />
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            selectedAccount?.name ??
                                            t(
                                                'reports.overview.accountsPage.noAccount',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            props.reportAccounts.summary
                                                .selected_account_type
                                        }}
                                    </p>
                                    <p
                                        class="mt-5 text-4xl font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            props.reportAccounts.summary
                                                .selected_account_balance
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-emerald-600 dark:text-emerald-300"
                                    >
                                        {{
                                            props.reportAccounts.summary
                                                .selected_account_share_label
                                        }}
                                        {{
                                            t(
                                                'reports.overview.accountsPage.assetShare',
                                            )
                                        }}
                                    </p>
                                    <div class="mt-8 grid grid-cols-2 gap-4">
                                        <div>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'reports.overview.accountsPage.openingBalance',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    props.reportAccounts.summary
                                                        .selected_account_opening_balance
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'reports.overview.accountsPage.activeAccounts',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    props.reportAccounts.summary
                                                        .active_accounts_count
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <ReportAccountsBalanceChart
                        :chart="props.reportAccounts.balance_trend"
                        :currency="props.reportAccounts.currency"
                        :title="t('reports.overview.accountsPage.balanceTrend')"
                        :description="
                            t(
                                'reports.overview.accountsPage.balanceTrendDescription',
                                {
                                    previous:
                                        props.reportAccounts.meta
                                            .previous_period_label,
                                },
                            )
                        "
                        :empty-label="
                            t('reports.overview.accountsPage.emptyBalanceTrend')
                        "
                    />
                </section>

                <div class="grid gap-4 xl:grid-cols-4">
                    <Card
                        v-for="metric in accountKpiCards"
                        :key="metric.key"
                        class="overflow-hidden rounded-[28px] border border-l-4 bg-white/95 shadow-sm dark:border-white/10 dark:bg-slate-950/75"
                        :class="metric.borderClass"
                    >
                        <CardContent class="relative p-5">
                            <div
                                class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r"
                                :class="metric.accentClass"
                            />
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid size-9 place-items-center rounded-2xl bg-slate-50 ring-1 ring-slate-200/70 dark:bg-slate-900 dark:ring-slate-800"
                                >
                                    <component
                                        :is="metric.icon"
                                        class="size-4"
                                        :class="metric.iconClass"
                                    />
                                </span>
                                <p
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-300"
                                >
                                    {{ metric.label }}
                                </p>
                            </div>
                            <p
                                class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                            >
                                {{ metric.value }}
                            </p>
                            <p class="mt-2 text-sm font-semibold">
                                <span
                                    v-if="metric.comparison.leading !== ''"
                                    :class="metric.deltaClass"
                                >
                                    {{ metric.comparison.leading }}
                                </span>
                                <span
                                    class="font-medium text-slate-500 dark:text-slate-400"
                                    :class="
                                        metric.comparison.leading !== ''
                                            ? 'ml-1'
                                            : ''
                                    "
                                >
                                    {{ metric.comparison.rest }}
                                </span>
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <ReportAccountsCashFlowChart
                        :cash-flow="props.reportAccounts.cash_flow"
                        :currency="props.reportAccounts.currency"
                        :title="t('reports.overview.accountsPage.cashFlow')"
                        :description="
                            t(
                                'reports.overview.accountsPage.cashFlowDescription',
                            )
                        "
                        :empty-label="
                            t('reports.overview.accountsPage.emptyCashFlow')
                        "
                    />

                    <Card
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle>
                                {{
                                    t(
                                        'reports.overview.accountsPage.distribution',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.overview.accountsPage.distributionDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ReportAccountsDistributionChart
                                :items="props.reportAccounts.distribution"
                                :total="
                                    props.reportAccounts.summary.total_balance
                                "
                                :total-label="
                                    t('reports.overview.accountsPage.total')
                                "
                                :empty-label="
                                    t(
                                        'reports.overview.accountsPage.emptyDistribution',
                                    )
                                "
                            />
                            <div class="mt-5 space-y-3">
                                <div
                                    v-for="item in props.reportAccounts
                                        .distribution"
                                    :key="item.uuid"
                                    class="space-y-1.5 rounded-2xl border border-slate-100 bg-slate-50/70 p-3 text-sm dark:border-slate-800 dark:bg-slate-900/50"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <span
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <span
                                                class="size-2.5 rounded-full"
                                                :style="{
                                                    backgroundColor: item.color,
                                                }"
                                            />
                                            <span
                                                class="truncate font-semibold text-slate-950 dark:text-slate-50"
                                                >{{ item.name }}</span
                                            >
                                        </span>
                                        <span
                                            class="font-semibold text-slate-950 dark:text-slate-50"
                                            >{{ item.value }}</span
                                        >
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-1.5 flex-1 rounded-full bg-slate-200 dark:bg-slate-800"
                                        >
                                            <div
                                                class="h-full rounded-full"
                                                :style="{
                                                    width: shareTrackWidth(
                                                        item.share_percentage,
                                                    ),
                                                    backgroundColor: item.color,
                                                }"
                                            />
                                        </div>
                                        <span
                                            class="w-12 text-right text-xs font-semibold text-slate-500 dark:text-slate-400"
                                        >
                                            {{ item.share_label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section
                    class="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
                >
                    <Card
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle>
                                {{
                                    t(
                                        'reports.overview.accountsPage.topCategories',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.overview.accountsPage.topCategoriesDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="(category, index) in props.reportAccounts
                                    .top_categories"
                                :key="category.label"
                                class="rounded-2xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-900/50"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <span
                                            class="grid size-7 shrink-0 place-items-center rounded-xl bg-white text-xs font-bold text-slate-500 shadow-sm ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span
                                            class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ category.label }}
                                        </span>
                                    </div>
                                    <span
                                        class="shrink-0 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ category.total }}
                                    </span>
                                </div>
                                <div
                                    class="h-2.5 rounded-full bg-white shadow-inner dark:bg-slate-950"
                                >
                                    <div
                                        class="h-full rounded-full"
                                        :style="{
                                            width: `${Math.max(6, (category.total_raw / maxTopCategory) * 100)}%`,
                                            backgroundColor: category.color,
                                        }"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="
                                    props.reportAccounts.top_categories
                                        .length === 0
                                "
                                class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'reports.overview.accountsPage.emptyTopCategories',
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle>
                                {{
                                    t(
                                        'reports.overview.accountsPage.comparisonTable',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.overview.accountsPage.comparisonDescription',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                class="hidden grid-cols-[1.35fr_1fr_0.9fr_0.9fr_0.9fr_0.9fr] gap-4 px-4 text-[11px] font-semibold tracking-[0.16em] text-slate-400 uppercase lg:grid"
                            >
                                <span>{{
                                    t('reports.overview.accountsPage.account')
                                }}</span>
                                <span>{{
                                    t(
                                        'reports.overview.accountsPage.currentBalance',
                                    )
                                }}</span>
                                <span>{{
                                    t('reports.overview.accountsPage.income')
                                }}</span>
                                <span>{{
                                    t('reports.overview.accountsPage.expense')
                                }}</span>
                                <span>{{
                                    t('reports.overview.accountsPage.net')
                                }}</span>
                                <span>{{
                                    t(
                                        'reports.overview.accountsPage.assetShareShort',
                                    )
                                }}</span>
                            </div>
                            <div
                                v-for="row in props.reportAccounts
                                    .comparison_rows"
                                :key="row.uuid"
                                class="grid gap-4 rounded-3xl border border-slate-100 bg-slate-50/80 p-4 lg:grid-cols-[1.35fr_1fr_0.9fr_0.9fr_0.9fr_0.9fr] lg:items-center dark:border-slate-800 dark:bg-slate-900/50"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-2xl text-xs font-bold"
                                        :style="{
                                            backgroundColor: `${row.color}1f`,
                                            color: row.color,
                                        }"
                                    >
                                        {{ row.initials }}
                                    </span>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ row.name }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ row.type_label }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="text-xs text-slate-500 lg:hidden dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'reports.overview.accountsPage.currentBalance',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ row.current_balance }}
                                    </p>
                                    <svg
                                        class="mt-2 h-9 w-full text-[var(--row-color)] lg:max-w-[140px]"
                                        viewBox="0 0 132 34"
                                        preserveAspectRatio="none"
                                        :style="{ '--row-color': row.color }"
                                        aria-hidden="true"
                                    >
                                        <polygon
                                            :points="
                                                sparklineAreaPoints(
                                                    row.sparkline,
                                                )
                                            "
                                            fill="currentColor"
                                            opacity="0.1"
                                        />
                                        <polyline
                                            :points="
                                                sparklinePoints(row.sparkline)
                                            "
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2.4"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <p
                                        class="text-xs text-slate-500 lg:hidden dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'reports.overview.accountsPage.income',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="font-semibold text-emerald-600 dark:text-emerald-300"
                                    >
                                        {{ row.income }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs text-slate-500 lg:hidden dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'reports.overview.accountsPage.expense',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="font-semibold text-rose-600 dark:text-rose-300"
                                    >
                                        {{ row.expense }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs text-slate-500 lg:hidden dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'reports.overview.accountsPage.net',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="font-semibold"
                                        :class="
                                            row.net_raw >= 0
                                                ? 'text-emerald-600 dark:text-emerald-300'
                                                : 'text-rose-600 dark:text-rose-300'
                                        "
                                    >
                                        {{ row.net }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <p
                                            class="text-xs text-slate-500 lg:hidden dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'reports.overview.accountsPage.assetShareShort',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ row.share_label }}
                                        </p>
                                    </div>
                                    <div
                                        class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-full rounded-full"
                                            :style="{
                                                width: shareTrackWidth(
                                                    row.share_percentage,
                                                ),
                                                backgroundColor: row.color,
                                            }"
                                        />
                                    </div>
                                    <p
                                        class="text-xs font-semibold"
                                        :class="
                                            (row.delta_percentage ?? 0) >= 0
                                                ? 'text-emerald-600 dark:text-emerald-300'
                                                : 'text-rose-600 dark:text-rose-300'
                                        "
                                    >
                                        {{ row.delta_label ?? '0,0%' }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="
                                    props.reportAccounts.comparison_rows
                                        .length === 0
                                "
                                class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'reports.overview.accountsPage.emptyComparison',
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                </section>

                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm md:hidden dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader>
                        <CardTitle>
                            {{
                                t(
                                    'reports.overview.accountsPage.recentMovements',
                                )
                            }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="movement in props.reportAccounts
                                .recent_transactions"
                            :key="movement.uuid"
                            class="flex items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ movement.description }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ movement.date_label }} ·
                                    {{ movement.category_label }}
                                </p>
                            </div>
                            <p
                                class="shrink-0 text-sm font-semibold"
                                :class="
                                    movement.amount_raw >= 0
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{ movement.amount }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </ReportsLayout>
    </AppLayout>
</template>
