<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    CalendarClock,
    PiggyBank,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { CSSProperties } from 'vue';
import { useI18n } from 'vue-i18n';
import DashboardPreviewChart from '@/components/DashboardPreviewChart.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { dashboard as dashboardRoute } from '@/routes';
import { edit as editYears } from '@/routes/years';
import type {
    Auth,
    BreadcrumbItem,
    DashboardBudgetComparisonItem,
    DashboardCategoryBreakdownItem,
    DashboardParentCategoryBudgetItem,
    DashboardPageProps,
} from '@/types';

const props = defineProps<DashboardPageProps>();
const { locale, t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('dashboard.title'),
        href: dashboardRoute(),
    },
];

const page = usePage();
const auth = computed(() => page.props.auth as Auth);

const dashboardTheme: CSSProperties = {
    '--dashboard-blue': '#2563eb',
    '--dashboard-blue-soft': 'rgba(37, 99, 235, 0.12)',
    '--dashboard-blue-fill': 'rgba(37, 99, 235, 0.16)',
    '--dashboard-emerald': '#059669',
    '--dashboard-emerald-soft': 'rgba(5, 150, 105, 0.12)',
    '--dashboard-emerald-fill': 'rgba(5, 150, 105, 0.16)',
    '--dashboard-rose': '#f43f5e',
    '--dashboard-rose-soft': 'rgba(244, 63, 94, 0.12)',
    '--dashboard-rose-fill': 'rgba(244, 63, 94, 0.16)',
    '--dashboard-mint': '#10b981',
    '--dashboard-mint-soft': 'rgba(16, 185, 129, 0.12)',
    '--dashboard-mint-fill': 'rgba(16, 185, 129, 0.16)',
    '--dashboard-gold': '#f59e0b',
    '--dashboard-violet': '#7c3aed',
};

const now = new Date();
const currentCalendarYear = now.getFullYear();

const currency = computed(
    () => props.dashboard.settings.base_currency || 'EUR',
);
const currentMonth = computed(() => props.dashboard.filters.month);
const currentYear = computed(() => props.dashboard.filters.year);

const greeting = computed(() => {
    const hour = now.getHours();

    if (hour < 12) {
        return t('dashboard.greeting.morning');
    }

    if (hour < 18) {
        return t('dashboard.greeting.afternoon');
    }

    return t('dashboard.greeting.evening');
});

const currentDateLabel = computed(() =>
    capitalize(
        new Intl.DateTimeFormat(locale.value, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(now),
    ),
);

const activePeriodLabel = computed(() => {
    if (currentMonth.value === null) {
        return t('dashboard.period.allYear', { year: currentYear.value });
    }

    return capitalize(
        new Intl.DateTimeFormat(locale.value, {
            month: 'long',
        }).format(new Date(currentYear.value, currentMonth.value - 1, 1)),
    ).concat(` ${currentYear.value}`);
});

const balanceDelta = computed(
    () =>
        props.dashboard.overview.current_balance_total_raw -
        props.dashboard.overview.previous_balance_total_raw,
);

const savingsRate = computed(() =>
    clampPercentage(props.dashboard.overview.savings_rate),
);

const spendingRate = computed(() => Math.max(0, 100 - savingsRate.value));

const savingsRingStyle = computed(() => ({
    background: `conic-gradient(var(--dashboard-blue) 0 ${savingsRate.value}%, var(--dashboard-rose) ${savingsRate.value}% 100%)`,
}));

const alertItems = computed(() =>
    [
        {
            label: t('dashboard.alerts.review'),
            count: props.dashboard.notifications.review_needed_count,
        },
        {
            label: t('dashboard.alerts.overdueRecurring'),
            count: props.dashboard.notifications.overdue_recurring_count,
        },
        {
            label: t('dashboard.alerts.urgentScheduled'),
            count: props.dashboard.notifications.due_scheduled_count,
        },
    ].filter((item) => item.count > 0),
);

const totalAlerts = computed(() =>
    alertItems.value.reduce((total, item) => total + item.count, 0),
);

const expenseSegments = computed(() =>
    buildDonutSegments(props.dashboard.expense_by_category.slice(0, 5)),
);

const expenseRingStyle = computed(() => ({
    background:
        expenseSegments.value.length > 0
            ? `conic-gradient(${expenseSegments.value
                  .map(
                      (segment) =>
                          `${segment.color} ${segment.start}% ${segment.end}%`,
                  )
                  .join(', ')})`
            : 'conic-gradient(rgba(148, 163, 184, 0.18) 0 100%)',
}));

const budgetHighlights = computed(() =>
    [...props.dashboard.budget_vs_actual]
        .sort((left, right) => right.actual_total_raw - left.actual_total_raw)
        .slice(0, 5),
);

const parentCategoryBudgetRows = computed(() =>
    [...props.dashboard.parent_category_budget_status].sort((left, right) => {
        if (left.delta_raw === right.delta_raw) {
            return left.category_name.localeCompare(right.category_name, 'it');
        }

        return left.delta_raw - right.delta_raw;
    }),
);

const merchantHighlights = computed(() =>
    props.dashboard.merchant_breakdown.slice(0, 4),
);

const upcomingEntries = computed(() =>
    props.dashboard.scheduled_summary.upcoming.slice(0, 4),
);

const incomeSparkline = computed(() =>
    buildSparklinePaths(
        props.dashboard.monthly_trend.map((point) => point.income_total_raw),
    ),
);

const expenseSparkline = computed(() =>
    buildSparklinePaths(
        props.dashboard.monthly_trend.map((point) => point.expense_total_raw),
    ),
);

const balanceSparkline = computed(() =>
    buildSparklinePaths(
        props.dashboard.monthly_trend.map((point) => point.net_total_raw),
    ),
);

const yearSelectValue = computed(() => String(currentYear.value));
const isViewingCurrentCalendarYear = computed(
    () => currentYear.value === currentCalendarYear,
);
const yearContextLabel = computed(() =>
    isViewingCurrentCalendarYear.value
        ? t('dashboard.period.currentYear')
        : t('dashboard.period.viewingYear', { year: currentYear.value }),
);

function visitDashboard(year: number, month: number | null): void {
    const query: Record<string, number> = {
        year,
    };

    if (month !== null) {
        query.month = month;
    }

    router.get(
        dashboardRoute.url({ query }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['dashboard', 'transactionsNavigation'],
        },
    );
}

function handleMonthSelection(month: number | null): void {
    visitDashboard(currentYear.value, month);
}

function handleYearSelection(value: unknown): void {
    const year = Number(value);

    if (Number.isNaN(year)) {
        return;
    }

    visitDashboard(year, currentMonth.value);
}

function formatCurrency(
    value: number,
    currencyCode: string = currency.value,
): string {
    return formatAppCurrency(value, currencyCode);
}

function formatSignedCurrency(
    value: number,
    currencyCode: string = currency.value,
): string {
    const prefix = value > 0 ? '+' : value < 0 ? '-' : '';

    return `${prefix}${formatCurrency(Math.abs(value), currencyCode)}`;
}

function formatPercentage(
    value: number,
    maximumFractionDigits: number = 0,
): string {
    return `${new Intl.NumberFormat(locale.value, {
        minimumFractionDigits: 0,
        maximumFractionDigits,
    }).format(value)}%`;
}

function formatDate(value: string): string {
    return capitalize(
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'short',
        }).format(new Date(value)),
    );
}

function monthOptionLabel(value: number | null): string {
    if (value === null) {
        return t('dashboard.period.all');
    }

    return capitalize(
        new Intl.DateTimeFormat(locale.value, {
            month: 'short',
        }).format(new Date(currentYear.value, value - 1, 1)),
    );
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function clampPercentage(value: number): number {
    return Math.min(100, Math.max(0, value));
}

function budgetProgress(item: DashboardBudgetComparisonItem): number {
    if (item.actual_total_raw <= 0) {
        return 0;
    }

    return Math.min(Math.max(item.percentage_used, 6), 100);
}

function parentCategoryBudgetProgress(
    item: DashboardParentCategoryBudgetItem,
): number {
    if (item.actual_total_raw <= 0) {
        return 0;
    }

    if (item.budget_total_raw <= 0) {
        return 100;
    }

    return Math.min(Math.max(item.percentage_used, 8), 100);
}

function parentCategoryUsageTone(
    item: DashboardParentCategoryBudgetItem,
): string {
    if (item.budget_total_raw <= 0 && item.actual_total_raw > 0) {
        return 'bg-[var(--dashboard-rose-soft)] text-[var(--dashboard-rose)] ring-1 ring-[var(--dashboard-rose)]/20';
    }

    if (item.percentage_used >= 90) {
        return 'bg-[var(--dashboard-rose-soft)] text-[var(--dashboard-rose)] ring-1 ring-[var(--dashboard-rose)]/20';
    }

    if (item.percentage_used >= 60) {
        return 'bg-amber-100/80 text-amber-800 ring-1 ring-amber-200/80 dark:bg-amber-400/10 dark:text-amber-100 dark:ring-amber-300/20';
    }

    return 'bg-[var(--dashboard-mint-soft)] text-[var(--dashboard-mint)] ring-1 ring-[var(--dashboard-mint)]/20';
}

function parentCategoryDifferenceTone(
    item: DashboardParentCategoryBudgetItem,
): string {
    return item.delta_raw >= 0
        ? 'bg-[var(--dashboard-mint-soft)] text-[var(--dashboard-mint)] ring-1 ring-[var(--dashboard-mint)]/20'
        : 'bg-[var(--dashboard-rose-soft)] text-[var(--dashboard-rose)] ring-1 ring-[var(--dashboard-rose)]/20';
}

function buildSparklinePaths(values: number[]): { line: string; area: string } {
    const points =
        values.length > 1 ? values : [values[0] ?? 0, values[0] ?? 0];
    const width = 120;
    const height = 42;
    const padding = 4;
    const minValue = Math.min(...points);
    const maxValue = Math.max(...points);
    const range = maxValue - minValue || 1;

    const line = points
        .map((value, index) => {
            const x =
                points.length === 1
                    ? width / 2
                    : (index / (points.length - 1)) * width;
            const y =
                height -
                padding -
                ((value - minValue) / range) * (height - padding * 2);

            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
        })
        .join(' ');

    return {
        line,
        area: `${line} L ${width} ${height} L 0 ${height} Z`,
    };
}

function buildDonutSegments(items: DashboardCategoryBreakdownItem[]) {
    const palette = [
        'var(--dashboard-blue)',
        'var(--dashboard-rose)',
        'var(--dashboard-mint)',
        'var(--dashboard-gold)',
        'var(--dashboard-violet)',
    ];

    const total = items.reduce((sum, item) => sum + item.total_amount_raw, 0);
    let currentPosition = 0;

    return items.map((item, index) => {
        const percentage =
            total > 0 ? (item.total_amount_raw / total) * 100 : 0;
        const start = currentPosition;
        currentPosition += percentage;

        return {
            ...item,
            color: palette[index % palette.length],
            percentage,
            start,
            end: currentPosition,
        };
    });
}
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            :style="dashboardTheme"
            class="flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-[32px] p-4 md:p-6"
        >
            <section
                class="rounded-[32px] border border-white/70 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.12),transparent_34%),linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,249,255,0.92))] p-4 shadow-sm md:p-5 dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.2),transparent_34%),linear-gradient(180deg,rgba(19,27,43,0.98),rgba(11,18,32,0.94))]"
            >
                <div
                    class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between"
                >
                    <div class="flex flex-1 flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <Badge
                                variant="secondary"
                                class="rounded-full bg-[var(--dashboard-blue-soft)] px-3 py-1 text-[var(--dashboard-blue)] dark:bg-[var(--dashboard-blue-soft)]"
                            >
                                {{ t('dashboard.period.active') }}
                            </Badge>
                            <p class="text-sm text-muted-foreground">
                                {{ activePeriodLabel }}
                            </p>
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="option in props.dashboard.filters
                                    .month_options"
                                :key="option.label"
                                type="button"
                                :class="
                                    cn(
                                        'min-w-max rounded-full px-4 py-2 text-sm font-medium transition-colors',
                                        option.value === currentMonth
                                            ? 'bg-[var(--dashboard-blue)] text-white shadow-sm'
                                            : 'bg-white/70 text-muted-foreground hover:bg-white hover:text-foreground dark:bg-white/5 dark:hover:bg-white/10',
                                    )
                                "
                                @click="handleMonthSelection(option.value)"
                            >
                                {{ monthOptionLabel(option.value).toLowerCase() }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col items-start gap-4 xl:items-end">
                        <Select
                            :model-value="yearSelectValue"
                            @update:model-value="handleYearSelection"
                        >
                            <SelectTrigger
                                :class="
                                    cn(
                                        'h-11 w-[168px] rounded-full border px-4 text-sm font-medium shadow-sm backdrop-blur-sm transition-all duration-200 ease-out',
                                        isViewingCurrentCalendarYear
                                            ? 'border-white/70 bg-white/90 text-foreground hover:border-[var(--dashboard-blue)]/35 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-[var(--dashboard-blue)]/45 dark:hover:bg-white/10'
                                            : 'border-amber-200/80 bg-[linear-gradient(135deg,rgba(255,251,235,0.96),rgba(255,255,255,0.98))] text-amber-950 shadow-[0_12px_30px_-18px_rgba(245,158,11,0.75)] ring-1 ring-amber-300/60 dark:border-amber-400/25 dark:bg-[linear-gradient(135deg,rgba(120,53,15,0.24),rgba(17,24,39,0.92))] dark:text-amber-100 dark:ring-amber-300/25',
                                    )
                                "
                            >
                                <SelectValue :placeholder="t('dashboard.filters.yearPlaceholder')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in props.dashboard.filters
                                        .available_years"
                                    :key="option.value"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <div
                            :class="
                                cn(
                                    'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition-all duration-200',
                                    isViewingCurrentCalendarYear
                                        ? 'bg-white/70 text-muted-foreground dark:bg-white/5'
                                        : 'bg-amber-100/90 text-amber-900 ring-1 ring-amber-200/80 dark:bg-amber-400/10 dark:text-amber-100 dark:ring-amber-300/20',
                                )
                            "
                        >
                            <span
                                :class="
                                    cn(
                                        'size-2 rounded-full',
                                        isViewingCurrentCalendarYear
                                            ? 'bg-[var(--dashboard-mint)]'
                                            : 'animate-pulse bg-[var(--dashboard-gold)]',
                                    )
                                "
                            />
                            {{ yearContextLabel }}
                        </div>

                        <div class="text-left xl:text-right">
                            <p class="text-lg font-semibold tracking-tight">
                                {{ greeting }}, {{ auth.user.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ currentDateLabel }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <Alert
                v-if="props.dashboard.year_suggestion"
                class="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <CalendarClock class="h-4 w-4" />
                <AlertTitle>
                    {{ props.dashboard.year_suggestion.title }}
                </AlertTitle>
                <AlertDescription
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span>
                        {{ props.dashboard.year_suggestion.message }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-2xl border-amber-300/80 bg-white/80 dark:border-amber-300/20 dark:bg-slate-950/60"
                        @click="router.get(editYears())"
                    >
                        {{ t('dashboard.actions.createYear') }}
                    </Button>
                </AlertDescription>
            </Alert>

            <section
                class="grid gap-4 xl:grid-cols-[1.35fr_1fr_1fr_.95fr_1.15fr]"
            >
                <article
                    class="rounded-[28px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,250,255,0.94))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[var(--dashboard-blue-soft)] text-[var(--dashboard-blue)]"
                            >
                                <Wallet class="size-5" />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.metrics.currentBalance') }}
                            </p>
                            <p class="text-3xl font-semibold tracking-tight">
                                {{ formatCurrency(props.dashboard.overview.current_balance_total_raw) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.metrics.previousBalance') }}
                                {{ formatCurrency(props.dashboard.overview.previous_balance_total_raw) }}
                            </p>
                        </div>

                        <Badge
                            variant="secondary"
                            class="rounded-full bg-[var(--dashboard-blue-soft)] px-3 py-1 text-[var(--dashboard-blue)]"
                        >
                            {{ activePeriodLabel }}
                        </Badge>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div
                            class="h-12 rounded-2xl bg-[var(--dashboard-blue-soft)] p-2"
                        >
                            <svg
                                viewBox="0 0 120 42"
                                class="h-full w-full"
                                preserveAspectRatio="none"
                            >
                                <path
                                    :d="balanceSparkline.area"
                                    fill="var(--dashboard-blue-fill)"
                                />
                                <path
                                    :d="balanceSparkline.line"
                                    fill="none"
                                    stroke="var(--dashboard-blue)"
                                    stroke-linecap="round"
                                    stroke-width="3"
                                />
                            </svg>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">
                                {{ t('dashboard.metrics.periodDelta') }}
                            </span>
                            <span
                                :class="
                                    balanceDelta >= 0
                                        ? 'text-[var(--dashboard-mint)]'
                                        : 'text-[var(--dashboard-rose)]'
                                "
                            >
                                {{ formatSignedCurrency(balanceDelta) }}
                            </span>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-[28px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(244,253,249,0.96))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(12,35,29,0.98),rgba(8,23,20,0.94))]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[var(--dashboard-emerald-soft)] text-[var(--dashboard-emerald)]"
                            >
                                <TrendingUp class="size-5" />
                            </div>
                            <p class="text-sm text-muted-foreground">{{ t('dashboard.metrics.income') }}</p>
                            <p class="text-2xl font-semibold tracking-tight">
                                {{ formatCurrency(props.dashboard.overview.income_total_raw) }}
                            </p>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {{
                                t('dashboard.metrics.transactions', {
                                    count: props.dashboard.overview.transactions_count,
                                })
                            }}
                        </p>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div
                            class="h-12 rounded-2xl bg-[var(--dashboard-emerald-soft)] p-2"
                        >
                            <svg
                                viewBox="0 0 120 42"
                                class="h-full w-full"
                                preserveAspectRatio="none"
                            >
                                <path
                                    :d="incomeSparkline.area"
                                    fill="var(--dashboard-emerald-fill)"
                                />
                                <path
                                    :d="incomeSparkline.line"
                                    fill="none"
                                    stroke="var(--dashboard-emerald)"
                                    stroke-linecap="round"
                                    stroke-width="3"
                                />
                            </svg>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">
                                {{ t('dashboard.metrics.activeAccounts') }}
                            </span>
                            <span class="text-[var(--dashboard-emerald)]">
                                {{
                                    props.dashboard.overview
                                        .active_accounts_count
                                }}
                            </span>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-[28px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(255,248,250,0.94))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(30,21,32,0.98),rgba(18,11,20,0.94))]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[var(--dashboard-rose-soft)] text-[var(--dashboard-rose)]"
                            >
                                <TrendingDown class="size-5" />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.metrics.expenses') }}
                            </p>
                            <p class="text-2xl font-semibold tracking-tight">
                                {{ formatCurrency(props.dashboard.overview.expense_total_raw) }}
                            </p>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {{ t('dashboard.metrics.budget') }}
                            {{ formatCurrency(props.dashboard.overview.budget_total_raw) }}
                        </p>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div
                            class="h-12 rounded-2xl bg-[var(--dashboard-rose-soft)] p-2"
                        >
                            <svg
                                viewBox="0 0 120 42"
                                class="h-full w-full"
                                preserveAspectRatio="none"
                            >
                                <path
                                    :d="expenseSparkline.area"
                                    fill="var(--dashboard-rose-fill)"
                                />
                                <path
                                    :d="expenseSparkline.line"
                                    fill="none"
                                    stroke="var(--dashboard-rose)"
                                    stroke-linecap="round"
                                    stroke-width="3"
                                />
                            </svg>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">
                                {{ t('dashboard.metrics.remainingBudget') }}
                            </span>
                            <span
                                :class="
                                    props.dashboard.overview
                                        .actual_vs_budget_delta_raw >= 0
                                        ? 'text-[var(--dashboard-mint)]'
                                        : 'text-[var(--dashboard-rose)]'
                                "
                            >
                                {{
                                    formatSignedCurrency(
                                        props.dashboard.overview
                                            .actual_vs_budget_delta_raw,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-[28px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,255,0.94))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[var(--dashboard-rose-soft)] text-[var(--dashboard-rose)]"
                            >
                                <BellRing class="size-5" />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.metrics.notifications') }}
                            </p>
                            <p class="text-2xl font-semibold tracking-tight">
                                {{ totalAlerts }}
                            </p>
                        </div>

                        <Badge
                            v-if="totalAlerts > 0"
                            variant="secondary"
                            class="rounded-full bg-[var(--dashboard-rose-soft)] px-2.5 py-1 text-[var(--dashboard-rose)]"
                        >
                            {{ t('dashboard.metrics.active') }}
                        </Badge>
                    </div>

                    <div class="mt-5 space-y-3">
                        <template v-if="alertItems.length > 0">
                            <div
                                v-for="item in alertItems"
                                :key="item.label"
                                class="flex items-center justify-between rounded-2xl bg-black/[0.03] px-3 py-2 text-sm dark:bg-white/[0.04]"
                            >
                                <span class="text-muted-foreground">
                                    {{ item.label }}
                                </span>
                                <span class="font-medium">
                                    {{ item.count }}
                                </span>
                            </div>
                        </template>

                        <p
                            v-else
                            class="rounded-2xl bg-black/[0.03] px-3 py-3 text-sm text-muted-foreground dark:bg-white/[0.04]"
                        >
                            {{ t('dashboard.metrics.noNotifications') }}
                        </p>
                    </div>
                </article>

                <article
                    class="rounded-[28px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,249,255,0.94))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[var(--dashboard-blue-soft)] text-[var(--dashboard-blue)]"
                            >
                                <PiggyBank class="size-5" />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.metrics.savingsRate') }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('dashboard.metrics.savingsRateHint') }}
                            </p>
                        </div>

                        <div
                            class="relative flex size-28 items-center justify-center rounded-full p-3"
                            :style="savingsRingStyle"
                        >
                            <div
                                class="flex size-full flex-col items-center justify-center rounded-full bg-white text-center dark:bg-[#0e1627]"
                            >
                                <span class="text-xs text-muted-foreground">
                                    {{ t('dashboard.metrics.savings') }}
                                </span>
                                <span class="text-xl font-semibold">
                                    {{ formatPercentage(savingsRate) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full bg-[var(--dashboard-blue)]"
                                />
                                {{ t('dashboard.metrics.savingsPlural') }}
                            </span>
                            <span class="font-medium">
                                {{ formatPercentage(savingsRate) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full bg-[var(--dashboard-rose)]"
                                />
                                {{ t('dashboard.metrics.expensesPlural') }}
                            </span>
                            <span class="font-medium">
                                {{ formatPercentage(spendingRate) }}
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                <DashboardPreviewChart
                    :points="props.dashboard.monthly_trend"
                    :month="currentMonth"
                    :currency="currency"
                    :title="t('dashboard.trend.title')"
                    :description="t('dashboard.trend.description')"
                />

                <Card
                    class="overflow-hidden border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,249,255,0.92))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <CardHeader class="gap-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle class="text-xl tracking-tight">
                                    {{ t('dashboard.expenseBreakdown.title') }}
                                </CardTitle>
                                <CardDescription>
                                    {{
                                        t(
                                            'dashboard.expenseBreakdown.description',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                            <Badge variant="secondary" class="rounded-full">
                                {{ t('dashboard.expenseBreakdown.topFive') }}
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent class="grid gap-6 lg:grid-cols-[172px_1fr]">
                        <div
                            class="mx-auto flex size-44 items-center justify-center rounded-full p-4"
                            :style="expenseRingStyle"
                        >
                            <div
                                class="flex size-full flex-col items-center justify-center rounded-full bg-white px-4 text-center dark:bg-[#0e1627]"
                            >
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'dashboard.expenseBreakdown.totalExpenses',
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-lg font-semibold tracking-tight"
                                >
                                    {{ formatCurrency(props.dashboard.overview.expense_total_raw) }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <template v-if="expenseSegments.length > 0">
                                <div
                                    v-for="segment in expenseSegments"
                                    :key="segment.category_name"
                                    class="rounded-2xl bg-black/[0.03] p-3 dark:bg-white/[0.04]"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="size-3 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        segment.color,
                                                }"
                                            />
                                            <div>
                                                <p class="font-medium">
                                                    {{ segment.category_name }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        formatPercentage(
                                                            segment.percentage,
                                                            1,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <span class="font-medium">
                                            {{ formatCurrency(segment.total_amount_raw) }}
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <p
                                v-else
                                class="rounded-2xl bg-black/[0.03] px-4 py-6 text-sm text-muted-foreground dark:bg-white/[0.04]"
                            >
                                {{ t('dashboard.expenseBreakdown.empty') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section class="grid gap-4 xl:grid-cols-[0.88fr_1.46fr_0.96fr]">
                <Card
                    class="border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,250,255,0.92))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <CardHeader class="gap-1.5 pb-4">
                        <CardTitle class="text-lg tracking-tight">
                            {{ t('dashboard.budgetVsActual.title') }}
                        </CardTitle>
                        <CardDescription class="text-sm">
                            {{ t('dashboard.budgetVsActual.description') }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-3">
                        <template v-if="budgetHighlights.length > 0">
                            <div
                                v-for="item in budgetHighlights"
                                :key="`${item.category_name}-${item.scope_name}`"
                                class="rounded-[22px] bg-black/[0.03] p-3.5 dark:bg-white/[0.04]"
                            >
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ item.category_name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.scope_name }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium">
                                            {{ formatCurrency(item.actual_total_raw) }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t('dashboard.budgetVsActual.of') }}
                                            {{ formatCurrency(item.budget_total_raw) }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-3 h-2 rounded-full bg-black/5 dark:bg-white/[0.08]"
                                >
                                    <div
                                        class="h-2 rounded-full"
                                        :class="
                                            item.delta_raw >= 0
                                                ? 'bg-[var(--dashboard-blue)]'
                                                : 'bg-[var(--dashboard-rose)]'
                                        "
                                        :style="{
                                            width: `${budgetProgress(item)}%`,
                                        }"
                                    />
                                </div>

                                <div
                                    class="mt-2.5 flex items-center justify-between text-xs"
                                >
                                    <span class="text-muted-foreground">
                                        {{
                                            t('dashboard.budgetVsActual.used', {
                                                value: formatPercentage(
                                                    item.percentage_used,
                                                    1,
                                                ),
                                            })
                                        }}
                                    </span>
                                    <span
                                        :class="
                                            item.delta_raw >= 0
                                                ? 'text-[var(--dashboard-mint)]'
                                                : 'text-[var(--dashboard-rose)]'
                                        "
                                    >
                                        {{
                                            item.delta_raw >= 0
                                                ? t(
                                                      'dashboard.budgetVsActual.remaining',
                                                      { value: item.delta },
                                                  )
                                                : t(
                                                      'dashboard.budgetVsActual.exceeded',
                                                      {
                                                          value: formatCurrency(
                                                              Math.abs(
                                                                  item.delta_raw,
                                                              ),
                                                          ),
                                                      },
                                                  )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </template>

                        <p
                            v-else
                            class="rounded-[22px] bg-black/[0.03] px-4 py-5 text-sm text-muted-foreground dark:bg-white/[0.04]"
                        >
                            {{ t('dashboard.budgetVsActual.empty') }}
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="overflow-hidden border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.99),rgba(244,250,255,0.95))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.99),rgba(11,18,32,0.95))]"
                >
                    <CardHeader class="gap-2 pb-4">
                        <div
                            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                        >
                            <div>
                                <CardTitle class="text-xl tracking-tight">
                                    {{ t('dashboard.categoryTargets.title') }}
                                </CardTitle>
                                <CardDescription>
                                    {{
                                        t(
                                            'dashboard.categoryTargets.description',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                            <Badge
                                variant="secondary"
                                class="rounded-full bg-[var(--dashboard-blue-soft)] px-3 py-1 text-[var(--dashboard-blue)]"
                            >
                                {{
                                    t('dashboard.categoryTargets.groups', {
                                        count: parentCategoryBudgetRows.length,
                                    })
                                }}
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <template v-if="parentCategoryBudgetRows.length > 0">
                            <div
                                class="hidden grid-cols-[1.5fr_1fr_1fr_1fr_0.9fr] items-center gap-3 rounded-[22px] bg-black/[0.035] px-4 py-3 text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase md:grid dark:bg-white/[0.04]"
                            >
                                <span>{{
                                    t('dashboard.categoryTargets.headers.category')
                                }}</span>
                                <span class="text-right">{{
                                    t('dashboard.categoryTargets.headers.target')
                                }}</span>
                                <span class="text-right">{{
                                    t('dashboard.categoryTargets.headers.actual')
                                }}</span>
                                <span class="text-right">{{
                                    t('dashboard.categoryTargets.headers.difference')
                                }}</span>
                                <span class="text-right">{{
                                    t('dashboard.categoryTargets.headers.budgetPercent')
                                }}</span>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="item in parentCategoryBudgetRows"
                                    :key="item.category_id"
                                    class="rounded-[24px] border border-black/5 bg-white/80 p-4 shadow-[0_18px_45px_-30px_rgba(15,23,42,0.35)] dark:border-white/8 dark:bg-white/[0.04]"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3 md:hidden"
                                    >
                                        <div>
                                            <p class="font-medium">
                                                {{ item.category_name }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    item.delta_raw >= 0
                                                        ? t('dashboard.categoryTargets.mobile.marginAvailable')
                                                        : t('dashboard.categoryTargets.mobile.watchCategory')
                                                }}
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="
                                                parentCategoryUsageTone(item)
                                            "
                                        >
                                            {{
                                                formatPercentage(
                                                    item.percentage_used,
                                                    0,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="hidden grid-cols-[1.5fr_1fr_1fr_1fr_0.9fr] items-center gap-3 md:grid"
                                    >
                                        <div>
                                            <p class="font-medium">
                                                {{ item.category_name }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    item.delta_raw >= 0
                                                        ? t('dashboard.categoryTargets.mobile.inControl')
                                                        : t('dashboard.categoryTargets.mobile.needsAttention')
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="text-right text-sm font-medium"
                                        >
                                            {{ formatCurrency(item.budget_total_raw) }}
                                        </div>
                                        <div
                                            class="text-right text-sm font-medium"
                                        >
                                            {{ formatCurrency(item.actual_total_raw) }}
                                        </div>
                                        <div class="flex justify-end">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="
                                                    parentCategoryDifferenceTone(
                                                        item,
                                                    )
                                                "
                                            >
                                                {{
                                                    item.delta_raw >= 0
                                                        ? `+${item.delta}`
                                                        : `-${formatCurrency(Math.abs(item.delta_raw))}`
                                                }}
                                            </span>
                                        </div>
                                        <div class="flex justify-end">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="
                                                    parentCategoryUsageTone(
                                                        item,
                                                    )
                                                "
                                            >
                                                {{
                                                    formatPercentage(
                                                        item.percentage_used,
                                                        0,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-3 md:hidden">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div
                                                class="rounded-2xl bg-black/[0.03] px-3 py-2.5 dark:bg-white/[0.04]"
                                            >
                                                <p
                                                    class="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                >
                                                    {{ t('dashboard.categoryTargets.headers.target') }}
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-medium"
                                                >
                                                    {{ formatCurrency(item.budget_total_raw) }}
                                                </p>
                                            </div>
                                            <div
                                                class="rounded-2xl bg-black/[0.03] px-3 py-2.5 dark:bg-white/[0.04]"
                                            >
                                                <p
                                                    class="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                                >
                                                    {{ t('dashboard.categoryTargets.headers.actual') }}
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-medium"
                                                >
                                                    {{ formatCurrency(item.actual_total_raw) }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="
                                                    parentCategoryDifferenceTone(
                                                        item,
                                                    )
                                                "
                                            >
                                                {{
                                                    item.delta_raw >= 0
                                                        ? t('dashboard.categoryTargets.mobile.differencePositive', { value: item.delta })
                                                        : t('dashboard.categoryTargets.mobile.differenceNegative', { value: formatCurrency(Math.abs(item.delta_raw)) })
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <div
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span class="text-muted-foreground">
                                                {{ t('dashboard.categoryTargets.trend.label') }}
                                            </span>
                                            <span
                                                class="font-semibold"
                                                :class="
                                                    item.delta_raw >= 0
                                                        ? 'text-[var(--dashboard-mint)]'
                                                        : 'text-[var(--dashboard-rose)]'
                                                "
                                            >
                                                {{
                                                    item.delta_raw >= 0
                                                        ? t('dashboard.categoryTargets.trend.within')
                                                        : t('dashboard.categoryTargets.trend.over')
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            class="mt-2 h-2.5 rounded-full bg-black/5 dark:bg-white/[0.08]"
                                        >
                                            <div
                                                class="h-2.5 rounded-full transition-all duration-300"
                                                :class="
                                                    item.delta_raw >= 0
                                                        ? 'bg-[var(--dashboard-mint)]'
                                                        : 'bg-[var(--dashboard-rose)]'
                                                "
                                                :style="{
                                                    width: `${parentCategoryBudgetProgress(item)}%`,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <p
                            v-else
                            class="rounded-[24px] bg-black/[0.03] px-4 py-6 text-sm text-muted-foreground dark:bg-white/[0.04]"
                        >
                            {{ t('dashboard.categoryTargets.empty') }}
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,250,255,0.92))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <CardHeader class="gap-1.5 pb-4">
                        <CardTitle class="text-lg tracking-tight">
                            {{ t('dashboard.agenda.title') }}
                        </CardTitle>
                        <CardDescription class="text-sm">
                            {{ t('dashboard.agenda.description') }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                class="rounded-[20px] bg-black/[0.03] p-3 dark:bg-white/[0.04]"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t('dashboard.agenda.dueSoon') }}
                                </p>
                                <p class="mt-1.5 text-xl font-semibold">
                                    {{
                                        props.dashboard.notifications
                                            .due_scheduled_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-[20px] bg-black/[0.03] p-3 dark:bg-white/[0.04]"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t('dashboard.agenda.recurring') }}
                                </p>
                                <p class="mt-1.5 text-xl font-semibold">
                                    {{
                                        props.dashboard.recurring_summary
                                            .overdue_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-[20px] bg-black/[0.03] p-3 dark:bg-white/[0.04]"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t('dashboard.agenda.review') }}
                                </p>
                                <p class="mt-1.5 text-xl font-semibold">
                                    {{
                                        props.dashboard.notifications
                                            .review_needed_count
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <CalendarClock
                                    class="size-4 text-[var(--dashboard-blue)]"
                                />
                                {{ t('dashboard.agenda.upcomingPlanned') }}
                            </div>

                            <template v-if="upcomingEntries.length > 0">
                                <div
                                    v-for="entry in upcomingEntries"
                                    :key="entry.id"
                                    class="flex items-center justify-between gap-3 rounded-[20px] bg-black/[0.03] px-3.5 py-3 dark:bg-white/[0.04]"
                                >
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ entry.title }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDate(entry.scheduled_date)
                                            }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium">
                                            {{ formatCurrency(entry.expected_amount_raw) }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ entry.status }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <p
                                v-else
                                class="rounded-[22px] bg-black/[0.03] px-4 py-5 text-sm text-muted-foreground dark:bg-white/[0.04]"
                            >
                                {{ t('dashboard.agenda.upcomingEmpty') }}
                            </p>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <TrendingUp
                                    class="size-4 text-[var(--dashboard-mint)]"
                                />
                                {{ t('dashboard.agenda.topMerchants') }}
                            </div>

                            <template v-if="merchantHighlights.length > 0">
                                <div
                                    v-for="merchant in merchantHighlights"
                                    :key="merchant.merchant_name"
                                    class="flex items-center justify-between gap-3 rounded-[20px] bg-black/[0.03] px-3.5 py-3 dark:bg-white/[0.04]"
                                >
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ merchant.merchant_name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ merchant.transactions_count }}
                                            {{
                                                t(
                                                    'dashboard.agenda.transactions',
                                                    {
                                                        count: merchant.transactions_count,
                                                    },
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span class="text-sm font-medium">
                                        {{ formatCurrency(merchant.total_amount_raw) }}
                                    </span>
                                </div>
                            </template>

                            <p
                                v-else
                                class="rounded-[22px] bg-black/[0.03] px-4 py-5 text-sm text-muted-foreground dark:bg-white/[0.04]"
                            >
                                {{ t('dashboard.agenda.merchantsEmpty') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
