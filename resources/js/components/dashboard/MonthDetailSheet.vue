<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import MonthDetailNarrative from '@/components/dashboard/MonthDetailNarrative.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatCurrency } from '@/lib/currency';
import type { DashboardData } from '@/types/dashboard';

const props = defineProps<{
    open: boolean;
    detail: DashboardData['analysis']['month_detail'];
    insights: DashboardData['analysis']['insights'];
    currency: string;
}>();
const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const { locale, t } = useI18n();
const desktop = useMediaQuery('(min-width: 768px)');
const title = computed(() =>
    new Intl.DateTimeFormat(locale.value, {
        month: 'long',
        year: 'numeric',
    }).format(
        new Date(props.detail.period.year, props.detail.period.month - 1, 1),
    ),
);
const realRows = computed(
    () =>
        [
            ['income', props.detail.real.income_raw],
            ['expense', props.detail.real.expense_raw],
            ['net', props.detail.real.net_raw],
            ['startingBalance', props.detail.real.starting_balance_raw],
            ['endingBalance', props.detail.real.ending_balance_raw],
        ] as const,
);
const forecastRows = computed(
    () =>
        [
            ['recurring', props.detail.forecast.composition.recurring_raw],
            [
                'installments',
                props.detail.forecast.composition.installments_raw,
            ],
            ['scheduled', props.detail.forecast.composition.scheduled_raw],
        ] as const,
);
const expenseRows = computed(
    () =>
        [
            ['expense', props.detail.forecast.composition.actual_raw],
            ...forecastRows.value,
        ] as const,
);
const incomeRows = computed(
    () =>
        [
            ['income', props.detail.income_composition.actual_raw],
            ['recurringIncome', props.detail.income_composition.recurring_raw],
            ['scheduledIncome', props.detail.income_composition.scheduled_raw],
        ] as const,
);
const maximumCategory = computed(() =>
    Math.max(
        1,
        ...props.detail.top_expense_categories.map(
            (category) => category.total_amount_raw,
        ),
    ),
);
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)"
        ><SheetContent
            :side="desktop ? 'right' : 'bottom'"
            class="max-h-[92dvh] overflow-y-auto pb-[max(1.5rem,env(safe-area-inset-bottom))] md:max-w-2xl"
            ><SheetHeader class="pr-8 text-left"
                ><SheetTitle class="capitalize">{{ title }}</SheetTitle
                ><SheetDescription>{{
                    t('dashboard.analysis.detail.description')
                }}</SheetDescription></SheetHeader
            >
            <div class="grid gap-5 px-4 pb-6">
                <MonthDetailNarrative :detail="detail" :currency="currency" />
                <section class="rounded-2xl bg-muted/45 p-4 sm:p-5">
                    <h3 class="font-semibold">
                        {{ t('dashboard.analysis.detail.summary') }}
                    </h3>
                    <dl class="mt-3 grid gap-2">
                        <div
                            v-for="row in realRows"
                            :key="row[0]"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">
                                {{ t(`dashboard.analysis.detail.${row[0]}`) }}
                            </dt>
                            <dd>
                                <SensitiveValue
                                    :value="formatCurrency(row[1], currency)"
                                />
                            </dd>
                        </div>
                    </dl>
                </section>
                <section class="rounded-2xl border p-4 sm:p-5">
                    <h3 class="font-semibold">
                        {{ t('dashboard.analysis.detail.expenseComposition') }}
                    </h3>
                    <dl class="mt-3 grid gap-2">
                        <div
                            v-for="row in expenseRows"
                            :key="row[0]"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">
                                {{ t(`dashboard.analysis.detail.${row[0]}`) }}
                            </dt>
                            <dd>
                                <SensitiveValue
                                    :value="formatCurrency(row[1], currency)"
                                />
                            </dd>
                        </div>
                    </dl>
                </section>
                <section class="rounded-2xl border p-4 sm:p-5">
                    <h3 class="font-semibold">
                        {{ t('dashboard.analysis.detail.incomeComposition') }}
                    </h3>
                    <dl class="mt-3 grid gap-2">
                        <div
                            v-for="row in incomeRows"
                            :key="row[0]"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">
                                {{ t(`dashboard.analysis.detail.${row[0]}`) }}
                            </dt>
                            <dd>
                                <SensitiveValue
                                    :value="formatCurrency(row[1], currency)"
                                />
                            </dd>
                        </div>
                    </dl>
                </section>
                <section class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border p-4">
                        <p class="text-sm text-muted-foreground">
                            {{ t('dashboard.metrics.openCredits') }}
                        </p>
                        <SensitiveValue
                            class="mt-1 font-semibold"
                            :value="
                                formatCurrency(
                                    detail.credits.open_raw,
                                    currency,
                                )
                            "
                        />
                    </div>
                    <div class="rounded-2xl border p-4">
                        <p class="text-sm text-muted-foreground">
                            {{ t('dashboard.metrics.openDebts') }}
                        </p>
                        <SensitiveValue
                            class="mt-1 font-semibold"
                            :value="
                                formatCurrency(detail.debts.open_raw, currency)
                            "
                        />
                    </div>
                </section>
                <section>
                    <h3 class="font-semibold">
                        {{ t('dashboard.monthlyRecap.topCategories') }}
                    </h3>
                    <div class="mt-3 grid gap-2">
                        <div
                            v-for="category in detail.top_expense_categories.slice(
                                0,
                                5,
                            )"
                            :key="String(category.category_id)"
                            class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 rounded-xl bg-muted/60 p-3"
                        >
                            <span>{{ category.category_name }}</span
                            ><SensitiveValue
                                :value="
                                    formatCurrency(
                                        category.total_amount_raw,
                                        currency,
                                    )
                                "
                            />
                            <span
                                class="col-span-2 h-1.5 overflow-hidden rounded-full bg-background"
                                ><span
                                    class="block h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${(category.total_amount_raw / maximumCategory) * 100}%`,
                                    }"
                            /></span>
                        </div>
                        <p
                            v-if="detail.top_expense_categories.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('dashboard.expenseBreakdown.empty') }}
                        </p>
                    </div>
                </section>
                <section v-if="insights.length">
                    <h3 class="font-semibold">
                        {{ t('dashboard.analysis.insights') }}
                    </h3>
                    <div class="mt-3 grid gap-2">
                        <div
                            v-for="insight in insights.slice(0, 3)"
                            :key="insight.type"
                            class="rounded-xl border bg-card p-3"
                        >
                            <p class="text-sm font-semibold">
                                {{
                                    t(
                                        `dashboard.analysis.insight.${insight.type}.title`,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('dashboard.analysis.insightFallback') }}
                            </p>
                        </div>
                    </div>
                </section>
            </div></SheetContent
        ></Sheet
    >
</template>
