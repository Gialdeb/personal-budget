<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarClock,
    PiggyBank,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type {
    DashboardOverview,
    DashboardPendingActions,
} from '@/types/dashboard';

const props = defineProps<{
    overview: DashboardOverview;
    pendingActions: DashboardPendingActions;
    currency: string;
}>();
const { locale, t } = useI18n();
const firstPendingAction = computed(
    () => props.pendingActions.items[0] ?? null,
);
const savingsRate = computed(() =>
    Math.min(100, Math.max(0, props.overview.savings_rate)),
);
const spendingRate = computed(() => Math.max(0, 100 - savingsRate.value));
const expenseProgress = computed(() => {
    if (props.overview.expense_total_raw <= 0) {
return 0;
}

    if (props.overview.budget_total_raw <= 0) {
return 100;
}

    return Math.min(
        Math.max(
            (props.overview.expense_total_raw /
                props.overview.budget_total_raw) *
                100,
            6,
        ),
        100,
    );
});

function money(value: number): string {
    return formatCurrency(value, props.currency);
}
function signedMoney(value: number): string {
    const sign = value > 0 ? '+' : value < 0 ? '-' : '';

    return `${sign}${money(Math.abs(value))}`;
}
function percentage(value: number): string {
    return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 0 }).format(value)}%`;
}
function date(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${value}T12:00:00`));
}
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <article class="rounded-2xl border bg-card p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <span
                    class="grid size-9 place-items-center rounded-xl bg-[var(--dashboard-emerald-soft,var(--muted))] text-[var(--dashboard-emerald,var(--chart-2))]"
                    ><TrendingUp class="size-4.5"
                /></span>
                <span class="text-xs text-muted-foreground">{{
                    t('dashboard.metrics.transactions', {
                        count: overview.transactions_count,
                    })
                }}</span>
            </div>
            <p class="mt-3 text-sm text-muted-foreground">
                {{ t('dashboard.metrics.income') }}
            </p>
            <SensitiveValue
                class="mt-1 block text-2xl font-semibold tracking-tight"
                :value="money(overview.income_total_raw)"
            />
            <div
                class="mt-4 h-1.5 overflow-hidden rounded-full bg-[var(--dashboard-emerald-soft,var(--muted))]"
            >
                <span
                    class="block h-full w-full rounded-full bg-[var(--dashboard-emerald,var(--chart-2))]"
                />
            </div>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ t('dashboard.metrics.activeAccounts') }}
                <span
                    class="float-right font-medium text-[var(--dashboard-emerald,var(--chart-2))]"
                    >{{ overview.active_accounts_count }}</span
                >
            </p>
        </article>

        <article class="rounded-2xl border bg-card p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <span
                    class="grid size-9 place-items-center rounded-xl bg-destructive/10 text-destructive"
                    ><TrendingDown class="size-4.5"
                /></span>
                <span class="text-xs text-muted-foreground"
                    >{{ t('dashboard.metrics.budget') }}
                    <SensitiveValue :value="money(overview.budget_total_raw)"
                /></span>
            </div>
            <p class="mt-3 text-sm text-muted-foreground">
                {{ t('dashboard.metrics.expenses') }}
            </p>
            <SensitiveValue
                class="mt-1 block text-2xl font-semibold tracking-tight"
                :value="money(overview.expense_total_raw)"
            />
            <div
                class="mt-4 h-1.5 overflow-hidden rounded-full bg-destructive/10"
            >
                <span
                    class="block h-full rounded-full bg-destructive"
                    :style="{ width: `${expenseProgress}%` }"
                />
            </div>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ t('dashboard.metrics.remainingBudget') }}
                <SensitiveValue
                    class="float-right font-medium"
                    :class="
                        overview.actual_vs_budget_delta_raw >= 0
                            ? 'text-[var(--dashboard-emerald,var(--chart-2))]'
                            : 'text-destructive'
                    "
                    :value="signedMoney(overview.actual_vs_budget_delta_raw)"
                />
            </p>
        </article>

        <article class="rounded-2xl border bg-card p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <span
                    class="grid size-9 place-items-center rounded-xl bg-destructive/10 text-destructive"
                    ><CalendarClock class="size-4.5"
                /></span>
                <span
                    v-if="pendingActions.total_count"
                    class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive"
                    >{{ t('dashboard.metrics.open') }}</span
                >
            </div>
            <p class="mt-3 text-sm text-muted-foreground">
                {{ t('dashboard.metrics.pendingActions') }}
            </p>
            <p class="mt-1 text-2xl font-semibold tracking-tight">
                {{ pendingActions.total_count }}
            </p>
            <Link
                v-if="firstPendingAction"
                :href="firstPendingAction.action_url"
                class="mt-4 block rounded-xl bg-muted/45 px-3 py-2.5 text-sm transition hover:bg-muted"
            >
                <span class="block truncate font-medium">{{
                    firstPendingAction.title
                }}</span>
                <span class="mt-1 block text-xs text-muted-foreground"
                    >{{
                        t(
                            `dashboard.metrics.actionStatuses.${firstPendingAction.status_key}`,
                        )
                    }}
                    · {{ date(firstPendingAction.date) }}</span
                >
            </Link>
            <p
                v-else
                class="mt-4 rounded-xl bg-muted/45 px-3 py-2.5 text-sm text-muted-foreground"
            >
                {{ t('dashboard.metrics.noPendingActions') }}
            </p>
        </article>

        <article class="rounded-2xl border bg-card p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <span
                    class="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary"
                    ><PiggyBank class="size-4.5"
                /></span>
                <span class="text-xs text-muted-foreground">{{
                    t('dashboard.metrics.savingsRateHint')
                }}</span>
            </div>
            <p class="mt-3 text-sm text-muted-foreground">
                {{ t('dashboard.metrics.savingsRate') }}
            </p>
            <p class="mt-1 text-2xl font-semibold tracking-tight">
                {{ percentage(savingsRate) }}
            </p>
            <div
                class="mt-4 flex h-1.5 overflow-hidden rounded-full bg-destructive/15"
            >
                <span
                    class="bg-primary"
                    :style="{ width: `${savingsRate}%` }"
                /><span
                    class="bg-destructive"
                    :style="{ width: `${spendingRate}%` }"
                />
            </div>
            <div class="mt-2 flex justify-between text-xs">
                <span class="text-primary"
                    >{{ t('dashboard.metrics.savingsPlural') }}
                    {{ percentage(savingsRate) }}</span
                ><span class="text-destructive"
                    >{{ t('dashboard.metrics.expensesPlural') }}
                    {{ percentage(spendingRate) }}</span
                >
            </div>
        </article>
    </section>
</template>
