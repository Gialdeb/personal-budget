<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronDown,
    ChevronRight,
    TrendingDown,
    TrendingUp,
    Receipt,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { show as transactionsRoute } from '@/routes/transactions';
import type {
    BreadcrumbItem,
    MonthlyTransactionSheetData,
    MonthlyTransactionSheetPageProps,
} from '@/types';

const props = defineProps<MonthlyTransactionSheetPageProps>();
const { locale, t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('transactions.monthly.title'),
        href: transactionsRoute({ year: props.year, month: props.month }),
    },
];

const sheet = ref<MonthlyTransactionSheetData>(props.monthlySheet);
const selectedGroup = ref('all');
const collapsedSections = ref<string[]>([]);
const collapsedRows = ref<string[]>([]);

watch(
    () => props.monthlySheet,
    (value) => {
        sheet.value = value;
    },
);

const currency = computed(() => sheet.value.settings.base_currency || 'EUR');

const yearValue = computed(() => String(sheet.value.filters.year));
const monthValue = computed(() => String(sheet.value.filters.month));

const currentCalendarYear = new Date().getFullYear();
const currentCalendarMonth = new Date().getMonth() + 1;
const isCurrentPeriod = computed(
    () =>
        sheet.value.period.year === currentCalendarYear &&
        sheet.value.period.month === currentCalendarMonth,
);

const periodNotice = computed(() => {
    if (isCurrentPeriod.value) {
        return null;
    }

    return t('transactions.monthly.periodNotice', {
        selectedPeriod: `${sheet.value.period.month_label} ${sheet.value.period.year}`,
        currentPeriod: `${getMonthLabel(currentCalendarMonth)} ${currentCalendarYear}`,
    });
});

const isClosedYear = computed(() => sheet.value.meta.year_is_closed);

const visibleSections = computed(() =>
    selectedGroup.value === 'all'
        ? sheet.value.sections
        : sheet.value.sections.filter(
              (section) => section.key === selectedGroup.value,
          ),
);

function getMonthLabel(month: number): string {
    try {
        return new Intl.DateTimeFormat(locale.value, { month: 'long' }).format(
            new Date(2026, month - 1, 1),
        );
    } catch {
        return t('transactions.monthly.unknownMonth');
    }
}

function handleYearSelection(value: unknown): void {
    const year = Number(value);

    if (!Number.isInteger(year)) {
        return;
    }

    router.get(
        transactionsRoute.url({
            year,
            month: sheet.value.filters.month,
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function handleMonthSelection(value: unknown): void {
    const month = Number(value);

    if (!Number.isInteger(month) || month < 1 || month > 12) {
        return;
    }

    router.get(
        transactionsRoute.url({
            year: sheet.value.filters.year,
            month,
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function handleGroupSelection(value: unknown): void {
    selectedGroup.value = String(value);
}

function toggleSection(sectionKey: string): void {
    collapsedSections.value = collapsedSections.value.includes(sectionKey)
        ? collapsedSections.value.filter((value) => value !== sectionKey)
        : [...collapsedSections.value, sectionKey];
}

function toggleRow(rowUuid: string): void {
    collapsedRows.value = collapsedRows.value.includes(rowUuid)
        ? collapsedRows.value.filter((value) => value !== rowUuid)
        : [...collapsedRows.value, rowUuid];
}

function getVarianceColor(variance: number): string {
    if (Math.abs(variance) < 0.01) {
        return 'text-slate-600 dark:text-slate-300';
    }

    return variance > 0
        ? 'text-emerald-700 dark:text-emerald-400'
        : 'text-rose-700 dark:text-rose-400';
}

function getVarianceIcon(variance: number) {
    if (Math.abs(variance) < 0.01) {
        return Receipt;
    }

    return variance > 0 ? TrendingUp : TrendingDown;
}
</script>

<template>
    <Head
        :title="
            t('transactions.monthly.heading', {
                month: sheet.period.month_label,
                year: sheet.period.year,
            })
        "
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-[28px] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(34,197,94,0.14),_transparent_38%),linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(34,197,94,0.16),_transparent_38%),linear-gradient(135deg,rgba(2,6,23,0.95),rgba(15,23,42,0.9))]"
            >
                <div
                    class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:p-7"
                >
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Badge
                                class="rounded-full bg-emerald-500/12 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                            >
                                <Receipt class="mr-1 size-3.5" />
                                {{ t('transactions.monthly.title') }}
                            </Badge>
                            <Badge
                                v-if="sheet.meta.has_budget_data"
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                <Calendar class="mr-1 size-3.5" />
                                {{ t('transactions.monthly.compareBudget') }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{
                                    t('transactions.monthly.heading', {
                                        month: sheet.period.month_label,
                                        year: sheet.period.year,
                                    })
                                }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    t('transactions.monthly.description', {
                                        count: sheet.meta.transactions_count,
                                    })
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[450px]">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('transactions.monthly.labels.year') }}
                            </p>
                            <Select
                                :model-value="yearValue"
                                @update:model-value="handleYearSelection"
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in sheet.filters
                                            .available_years"
                                        :key="option.value"
                                        :value="String(option.value)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('transactions.monthly.labels.month') }}
                            </p>
                            <Select
                                :model-value="monthValue"
                                @update:model-value="handleMonthSelection"
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="month in 12"
                                        :key="month"
                                        :value="String(month)"
                                    >
                                        {{ getMonthLabel(month) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t('transactions.monthly.labels.macrogroup')
                                }}
                            </p>
                            <Select
                                :model-value="selectedGroup"
                                @update:model-value="handleGroupSelection"
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in sheet.filters
                                            .group_options"
                                        :key="option.value"
                                        :value="String(option.value)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
            </section>

            <Alert
                v-if="periodNotice"
                class="border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
            >
                <Calendar class="size-4" />
                <AlertTitle>{{
                    t('transactions.monthly.alerts.periodNotCurrent')
                }}</AlertTitle>
                <AlertDescription>
                    {{ periodNotice }}
                </AlertDescription>
            </Alert>

            <Alert
                v-if="isClosedYear"
                class="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <Calendar class="size-4" />
                <AlertTitle>{{
                    t('transactions.monthly.alerts.closedYear')
                }}</AlertTitle>
                <AlertDescription>
                    {{ sheet.meta.closed_year_message }}
                </AlertDescription>
            </Alert>

            <!-- Summary Cards -->
            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <Card
                    v-for="card in sheet.summary_cards"
                    :key="card.key"
                    class="overflow-hidden border-white/70 bg-white/85 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardContent class="space-y-2 p-4">
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ card.label }}
                        </p>
                        <div class="space-y-1">
                            <p
                                class="text-lg font-semibold text-slate-950 dark:text-white"
                            >
                                {{ formatCurrency(card.actual_raw, currency) }}
                            </p>
                            <div
                                v-if="card.budgeted_raw !== 0"
                                class="flex items-center justify-between text-xs"
                            >
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                >
                                    Budget:
                                    {{
                                        formatCurrency(
                                            card.budgeted_raw,
                                            currency,
                                        )
                                    }}
                                </span>
                                <span
                                    :class="getVarianceColor(card.variance_raw)"
                                >
                                    <component
                                        :is="getVarianceIcon(card.variance_raw)"
                                        class="mr-1 inline size-3"
                                    />
                                    {{
                                        formatCurrency(
                                            Math.abs(card.variance_raw),
                                            currency,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sections -->
            <div class="space-y-4">
                <div
                    v-for="section in visibleSections"
                    :key="section.key"
                    class="overflow-hidden rounded-[24px] border border-white/70 bg-white/85 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <div
                        class="border-b border-slate-200/50 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-slate-900/70"
                    >
                        <div
                            role="button"
                            tabindex="0"
                            class="flex w-full items-center justify-between"
                            @click="toggleSection(section.key)"
                            @keydown.enter.prevent="toggleSection(section.key)"
                            @keydown.space.prevent="toggleSection(section.key)"
                        >
                            <div class="space-y-1 text-left">
                                <h3
                                    class="text-lg font-semibold text-slate-950 dark:text-white"
                                >
                                    {{ section.label }}
                                </h3>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    {{ section.description }}
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-white"
                                    >
                                        {{
                                            formatCurrency(
                                                section.totals.net,
                                                currency,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'transactions.monthly.section.transactionsCount',
                                                { count: section.totals.count },
                                            )
                                        }}
                                    </p>
                                </div>
                                <ChevronDown
                                    :class="
                                        cn(
                                            'size-5 text-slate-400 transition-transform',
                                            collapsedSections.includes(
                                                section.key,
                                            )
                                                ? '-rotate-90'
                                                : '',
                                        )
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="!collapsedSections.includes(section.key)"
                        class="divide-y divide-slate-200/50 dark:divide-white/10"
                    >
                        <div
                            v-for="row in section.rows"
                            :key="row.uuid"
                            class="p-4"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <button
                                        v-if="row.has_children"
                                        type="button"
                                        class="flex size-6 items-center justify-center rounded border border-slate-300 text-slate-500 hover:bg-slate-100 dark:border-white/20 dark:text-slate-400 dark:hover:bg-slate-800"
                                        @click="toggleRow(row.uuid)"
                                    >
                                        <ChevronRight
                                            :class="
                                                cn(
                                                    'size-3 transition-transform',
                                                    collapsedRows.includes(
                                                        row.uuid,
                                                    )
                                                        ? ''
                                                        : 'rotate-90',
                                                )
                                            "
                                        />
                                    </button>
                                    <div class="space-y-1">
                                        <p
                                            class="font-medium text-slate-950 dark:text-white"
                                        >
                                            {{ row.name }}
                                        </p>
                                        <p
                                            v-if="row.transaction_count > 0"
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.monthly.section.transactionsCount',
                                                    {
                                                        count: row.transaction_count,
                                                    },
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="grid grid-cols-2 gap-4 text-right sm:grid-cols-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.monthly.section.actual',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-medium text-slate-950 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(
                                                    row.actual_net_raw,
                                                    currency,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="row.budgeted_amount_raw !== 0"
                                        class="hidden sm:block"
                                    >
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.monthly.section.budget',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                formatCurrency(
                                                    row.budgeted_amount_raw,
                                                    currency,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div v-if="row.budgeted_amount_raw !== 0">
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.monthly.section.difference',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-medium"
                                            :class="
                                                getVarianceColor(
                                                    row.variance_raw,
                                                )
                                            "
                                        >
                                            <component
                                                :is="
                                                    getVarianceIcon(
                                                        row.variance_raw,
                                                    )
                                                "
                                                class="mr-1 inline size-3"
                                            />
                                            {{
                                                formatCurrency(
                                                    Math.abs(row.variance_raw),
                                                    currency,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Children rows -->
                            <div
                                v-if="
                                    row.has_children &&
                                    !collapsedRows.includes(row.uuid)
                                "
                                class="mt-3 ml-9 space-y-3 border-l-2 border-slate-200 pl-4 dark:border-white/10"
                            >
                                <div
                                    v-for="child in row.children"
                                    :key="child.uuid"
                                    class="flex items-center justify-between"
                                >
                                    <div class="space-y-1">
                                        <p
                                            class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                        >
                                            {{ child.name }}
                                        </p>
                                        <p
                                            v-if="child.transaction_count > 0"
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.monthly.section.transactionsCount',
                                                    {
                                                        count: child.transaction_count,
                                                    },
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="grid grid-cols-2 gap-4 text-right sm:grid-cols-3"
                                    >
                                        <div>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.monthly.section.actual',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                            >
                                                {{
                                                    formatCurrency(
                                                        child.actual_net_raw,
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            v-if="
                                                child.budgeted_amount_raw !== 0
                                            "
                                            class="hidden sm:block"
                                        >
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.monthly.section.budget',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-sm font-medium text-slate-600 dark:text-slate-300"
                                            >
                                                {{
                                                    formatCurrency(
                                                        child.budgeted_amount_raw,
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            v-if="
                                                child.budgeted_amount_raw !== 0
                                            "
                                        >
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.monthly.section.difference',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-sm font-medium"
                                                :class="
                                                    getVarianceColor(
                                                        child.variance_raw,
                                                    )
                                                "
                                            >
                                                <component
                                                    :is="
                                                        getVarianceIcon(
                                                            child.variance_raw,
                                                        )
                                                    "
                                                    class="mr-1 inline size-3"
                                                />
                                                {{
                                                    formatCurrency(
                                                        Math.abs(
                                                            child.variance_raw,
                                                        ),
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overall Totals -->
            <Card
                class="overflow-hidden border-white/70 bg-white/85 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
            >
                <CardContent class="space-y-4 p-5">
                    <h2
                        class="text-lg font-semibold text-slate-950 dark:text-white"
                    >
                        {{ t('transactions.monthly.totals.title') }}
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1">
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'transactions.monthly.totals.actualIncome',
                                    )
                                }}
                            </p>
                            <p
                                class="text-lg font-semibold text-emerald-700 dark:text-emerald-400"
                            >
                                {{
                                    formatCurrency(
                                        sheet.totals.actual_income_raw,
                                        currency,
                                    )
                                }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'transactions.monthly.totals.actualExpenses',
                                    )
                                }}
                            </p>
                            <p
                                class="text-lg font-semibold text-rose-700 dark:text-rose-400"
                            >
                                {{
                                    formatCurrency(
                                        sheet.totals.actual_expense_raw,
                                        currency,
                                    )
                                }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t('transactions.monthly.totals.netBalance')
                                }}
                            </p>
                            <p
                                class="text-lg font-semibold"
                                :class="
                                    getVarianceColor(
                                        sheet.totals.net_actual_raw,
                                    )
                                "
                            >
                                {{
                                    formatCurrency(
                                        sheet.totals.net_actual_raw,
                                        currency,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            v-if="sheet.meta.has_budget_data"
                            class="space-y-1"
                        >
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'transactions.monthly.totals.budgetDifference',
                                    )
                                }}
                            </p>
                            <p
                                class="text-lg font-semibold"
                                :class="
                                    getVarianceColor(
                                        sheet.totals.net_actual_raw -
                                            sheet.totals.net_budgeted_raw,
                                    )
                                "
                            >
                                {{
                                    formatCurrency(
                                        Math.abs(
                                            sheet.totals.net_actual_raw -
                                                sheet.totals.net_budgeted_raw,
                                        ),
                                        currency,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
