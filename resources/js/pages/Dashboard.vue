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
import DashboardPreviewChart from '@/components/DashboardPreviewChart.vue';
import { Badge } from '@/components/ui/badge';
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
import { cn } from '@/lib/utils';
import { dashboard as dashboardRoute } from '@/routes';
import type {
    Auth,
    BreadcrumbItem,
    DashboardBudgetComparisonItem,
    DashboardCategoryBreakdownItem,
    DashboardPageProps,
} from '@/types';

const props = defineProps<DashboardPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboardRoute(),
    },
];

const page = usePage();
const auth = computed(() => page.props.auth as Auth);

const dashboardTheme: CSSProperties = {
    '--dashboard-blue': '#2563eb',
    '--dashboard-blue-soft': 'rgba(37, 99, 235, 0.12)',
    '--dashboard-blue-fill': 'rgba(37, 99, 235, 0.16)',
    '--dashboard-rose': '#f43f5e',
    '--dashboard-rose-soft': 'rgba(244, 63, 94, 0.12)',
    '--dashboard-rose-fill': 'rgba(244, 63, 94, 0.16)',
    '--dashboard-mint': '#10b981',
    '--dashboard-mint-soft': 'rgba(16, 185, 129, 0.12)',
    '--dashboard-mint-fill': 'rgba(16, 185, 129, 0.16)',
    '--dashboard-gold': '#f59e0b',
    '--dashboard-violet': '#7c3aed',
};

const fullMonthLabels = [
    'gennaio',
    'febbraio',
    'marzo',
    'aprile',
    'maggio',
    'giugno',
    'luglio',
    'agosto',
    'settembre',
    'ottobre',
    'novembre',
    'dicembre',
];

const now = new Date();

const currency = computed(
    () => props.dashboard.settings.base_currency || 'EUR',
);
const currentMonth = computed(() => props.dashboard.filters.month);
const currentYear = computed(() => props.dashboard.filters.year);

const greeting = computed(() => {
    const hour = now.getHours();

    if (hour < 12) {
        return 'Buongiorno';
    }

    if (hour < 18) {
        return 'Buon pomeriggio';
    }

    return 'Buonasera';
});

const currentDateLabel = computed(() =>
    capitalize(
        new Intl.DateTimeFormat('it-IT', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(now),
    ),
);

const activePeriodLabel = computed(() => {
    if (currentMonth.value === null) {
        return `Tutto il ${currentYear.value}`;
    }

    return `${capitalize(fullMonthLabels[currentMonth.value - 1])} ${currentYear.value}`;
});

const balanceDelta = computed(
    () =>
        props.dashboard.overview.current_balance_total -
        props.dashboard.overview.previous_balance_total,
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
            label: 'Da revisionare',
            count: props.dashboard.notifications.review_needed_count,
        },
        {
            label: 'Ricorrenze scadute',
            count: props.dashboard.notifications.overdue_recurring_count,
        },
        {
            label: 'Scadenze urgenti',
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
        .sort((left, right) => right.actual_total - left.actual_total)
        .slice(0, 5),
);

const merchantHighlights = computed(() =>
    props.dashboard.merchant_breakdown.slice(0, 4),
);

const upcomingEntries = computed(() =>
    props.dashboard.scheduled_summary.upcoming.slice(0, 4),
);

const incomeSparkline = computed(() =>
    buildSparklinePaths(
        props.dashboard.monthly_trend.map((point) => point.income_total),
    ),
);

const expenseSparkline = computed(() =>
    buildSparklinePaths(
        props.dashboard.monthly_trend.map((point) => point.expense_total),
    ),
);

const balanceSparkline = computed(() =>
    buildSparklinePaths(
        props.dashboard.monthly_trend.map((point) => point.net_total),
    ),
);

const yearSelectValue = computed(() => String(currentYear.value));

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
            only: ['dashboard'],
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
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currencyCode,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
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
    return `${new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 0,
        maximumFractionDigits,
    }).format(value)}%`;
}

function formatDate(value: string): string {
    return capitalize(
        new Intl.DateTimeFormat('it-IT', {
            day: 'numeric',
            month: 'short',
        }).format(new Date(value)),
    );
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function clampPercentage(value: number): number {
    return Math.min(100, Math.max(0, value));
}

function budgetProgress(item: DashboardBudgetComparisonItem): number {
    if (item.actual_total <= 0) {
        return 0;
    }

    return Math.min(Math.max(item.percentage_used, 6), 100);
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

    const total = items.reduce((sum, item) => sum + item.total_amount, 0);
    let currentPosition = 0;

    return items.map((item, index) => {
        const percentage = total > 0 ? (item.total_amount / total) * 100 : 0;
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
    <Head title="Dashboard" />

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
                                Periodo attivo
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
                                {{ option.label.toLowerCase() }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col items-start gap-4 xl:items-end">
                        <Select
                            :model-value="yearSelectValue"
                            @update:model-value="handleYearSelection"
                        >
                            <SelectTrigger
                                class="w-[148px] rounded-full border-white/70 bg-white/90 px-4 dark:border-white/10 dark:bg-white/5"
                            >
                                <SelectValue placeholder="Anno" />
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
                                Saldo attuale
                            </p>
                            <p class="text-3xl font-semibold tracking-tight">
                                {{
                                    formatCurrency(
                                        props.dashboard.overview
                                            .current_balance_total,
                                    )
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Saldo precedente
                                {{
                                    formatCurrency(
                                        props.dashboard.overview
                                            .previous_balance_total,
                                    )
                                }}
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
                                Delta periodo
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
                    class="rounded-[28px] border border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,250,255,0.94))] p-5 shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[var(--dashboard-blue-soft)] text-[var(--dashboard-blue)]"
                            >
                                <TrendingUp class="size-5" />
                            </div>
                            <p class="text-sm text-muted-foreground">Entrate</p>
                            <p class="text-2xl font-semibold tracking-tight">
                                {{
                                    formatCurrency(
                                        props.dashboard.overview.income_total,
                                    )
                                }}
                            </p>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {{ props.dashboard.overview.transactions_count }}
                            movimenti
                        </p>
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
                                    :d="incomeSparkline.area"
                                    fill="var(--dashboard-blue-fill)"
                                />
                                <path
                                    :d="incomeSparkline.line"
                                    fill="none"
                                    stroke="var(--dashboard-blue)"
                                    stroke-linecap="round"
                                    stroke-width="3"
                                />
                            </svg>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">
                                Conti attivi
                            </span>
                            <span class="text-[var(--dashboard-blue)]">
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
                            <p class="text-sm text-muted-foreground">Uscite</p>
                            <p class="text-2xl font-semibold tracking-tight">
                                {{
                                    formatCurrency(
                                        props.dashboard.overview.expense_total,
                                    )
                                }}
                            </p>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Budget
                            {{
                                formatCurrency(
                                    props.dashboard.overview.budget_total,
                                )
                            }}
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
                                Budget residuo
                            </span>
                            <span
                                :class="
                                    props.dashboard.overview
                                        .actual_vs_budget_delta >= 0
                                        ? 'text-[var(--dashboard-mint)]'
                                        : 'text-[var(--dashboard-rose)]'
                                "
                            >
                                {{
                                    formatSignedCurrency(
                                        props.dashboard.overview
                                            .actual_vs_budget_delta,
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
                                Notifiche
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
                            Attive
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
                            Nessuna notifica da evidenziare per questo periodo.
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
                                Tasso spesa e risparmio
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Basato sul periodo selezionato
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
                                    Risparmio
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
                                Risparmi
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
                                Spese
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
                    title="Andamento del periodo"
                    description="Linea pulita per leggere entrate e uscite senza cambiare schermata."
                />

                <Card
                    class="overflow-hidden border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,249,255,0.92))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <CardHeader class="gap-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle class="text-xl tracking-tight">
                                    Ripartizione spese
                                </CardTitle>
                                <CardDescription>
                                    Le categorie con il peso maggiore nel
                                    periodo selezionato.
                                </CardDescription>
                            </div>
                            <Badge variant="secondary" class="rounded-full">
                                Top 5
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
                                    Totale spese
                                </span>
                                <span
                                    class="text-lg font-semibold tracking-tight"
                                >
                                    {{
                                        formatCurrency(
                                            props.dashboard.overview
                                                .expense_total,
                                        )
                                    }}
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
                                            {{
                                                formatCurrency(
                                                    segment.total_amount,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <p
                                v-else
                                class="rounded-2xl bg-black/[0.03] px-4 py-6 text-sm text-muted-foreground dark:bg-white/[0.04]"
                            >
                                Nessuna spesa categorizzata disponibile per
                                questo periodo.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
                <Card
                    class="border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,250,255,0.92))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <CardHeader class="gap-2">
                        <CardTitle class="text-xl tracking-tight">
                            Budget vs effettivo
                        </CardTitle>
                        <CardDescription>
                            Dove stai spendendo di piu rispetto ai limiti che
                            hai impostato.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <template v-if="budgetHighlights.length > 0">
                            <div
                                v-for="item in budgetHighlights"
                                :key="`${item.category_name}-${item.scope_name}`"
                                class="rounded-[24px] bg-black/[0.03] p-4 dark:bg-white/[0.04]"
                            >
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ item.category_name }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ item.scope_name }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium">
                                            {{
                                                formatCurrency(
                                                    item.actual_total,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            su
                                            {{
                                                formatCurrency(
                                                    item.budget_total,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-4 h-2 rounded-full bg-black/5 dark:bg-white/[0.08]"
                                >
                                    <div
                                        class="h-2 rounded-full"
                                        :class="
                                            item.delta >= 0
                                                ? 'bg-[var(--dashboard-blue)]'
                                                : 'bg-[var(--dashboard-rose)]'
                                        "
                                        :style="{
                                            width: `${budgetProgress(item)}%`,
                                        }"
                                    />
                                </div>

                                <div
                                    class="mt-3 flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground">
                                        {{
                                            formatPercentage(
                                                item.percentage_used,
                                                1,
                                            )
                                        }}
                                        usato
                                    </span>
                                    <span
                                        :class="
                                            item.delta >= 0
                                                ? 'text-[var(--dashboard-mint)]'
                                                : 'text-[var(--dashboard-rose)]'
                                        "
                                    >
                                        {{
                                            item.delta >= 0
                                                ? `Residuo ${formatCurrency(item.delta)}`
                                                : `Sforato ${formatCurrency(Math.abs(item.delta))}`
                                        }}
                                    </span>
                                </div>
                            </div>
                        </template>

                        <p
                            v-else
                            class="rounded-[24px] bg-black/[0.03] px-4 py-6 text-sm text-muted-foreground dark:bg-white/[0.04]"
                        >
                            Nessun budget disponibile per il filtro selezionato.
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,250,255,0.92))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(20,28,44,0.98),rgba(11,18,32,0.94))]"
                >
                    <CardHeader class="gap-2">
                        <CardTitle class="text-xl tracking-tight">
                            Agenda finanziaria
                        </CardTitle>
                        <CardDescription>
                            Prossime scadenze e merchant principali del periodo.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-5">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                class="rounded-[22px] bg-black/[0.03] p-3 dark:bg-white/[0.04]"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    In scadenza
                                </p>
                                <p class="mt-2 text-2xl font-semibold">
                                    {{
                                        props.dashboard.notifications
                                            .due_scheduled_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-[22px] bg-black/[0.03] p-3 dark:bg-white/[0.04]"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Ricorrenze
                                </p>
                                <p class="mt-2 text-2xl font-semibold">
                                    {{
                                        props.dashboard.recurring_summary
                                            .overdue_count
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-[22px] bg-black/[0.03] p-3 dark:bg-white/[0.04]"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Da revisionare
                                </p>
                                <p class="mt-2 text-2xl font-semibold">
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
                                Prossime uscite pianificate
                            </div>

                            <template v-if="upcomingEntries.length > 0">
                                <div
                                    v-for="entry in upcomingEntries"
                                    :key="entry.id"
                                    class="flex items-center justify-between gap-3 rounded-[22px] bg-black/[0.03] px-4 py-3 dark:bg-white/[0.04]"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ entry.title }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                formatDate(entry.scheduled_date)
                                            }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium">
                                            {{
                                                formatCurrency(
                                                    entry.expected_amount,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
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
                                Nessuna scadenza imminente nel periodo.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <TrendingUp
                                    class="size-4 text-[var(--dashboard-mint)]"
                                />
                                Merchant principali
                            </div>

                            <template v-if="merchantHighlights.length > 0">
                                <div
                                    v-for="merchant in merchantHighlights"
                                    :key="merchant.merchant_name"
                                    class="flex items-center justify-between gap-3 rounded-[22px] bg-black/[0.03] px-4 py-3 dark:bg-white/[0.04]"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ merchant.merchant_name }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ merchant.transactions_count }}
                                            movimenti
                                        </p>
                                    </div>
                                    <span class="font-medium">
                                        {{
                                            formatCurrency(
                                                merchant.total_amount,
                                            )
                                        }}
                                    </span>
                                </div>
                            </template>

                            <p
                                v-else
                                class="rounded-[22px] bg-black/[0.03] px-4 py-5 text-sm text-muted-foreground dark:bg-white/[0.04]"
                            >
                                Nessun merchant rilevante da mostrare per il
                                filtro corrente.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
