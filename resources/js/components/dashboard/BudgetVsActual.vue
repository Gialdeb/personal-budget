<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type { DashboardBudgetComparisonItem } from '@/types/dashboard';

const props = defineProps<{
    items: DashboardBudgetComparisonItem[];
    currency: string;
}>();
const { locale, t } = useI18n();
const highlights = computed(() =>
    [...props.items]
        .sort((left, right) => right.actual_total_raw - left.actual_total_raw)
        .slice(0, 5),
);

function money(value: number): string {
    return formatCurrency(value, props.currency);
}
function percentage(value: number): string {
    return `${new Intl.NumberFormat(locale.value, {
        maximumFractionDigits: 1,
    }).format(value)}%`;
}
function progress(item: DashboardBudgetComparisonItem): number {
    if (item.actual_total_raw <= 0) {
        return 0;
    }

    return Math.min(Math.max(item.percentage_used, 6), 100);
}
</script>

<template>
    <section class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold">
            {{ t('dashboard.budgetVsActual.title') }}
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
            {{ t('dashboard.budgetVsActual.description') }}
        </p>

        <div v-if="highlights.length" class="mt-5 grid gap-3">
            <article
                v-for="item in highlights"
                :key="`${item.category_name}-${item.scope_name}`"
                class="rounded-xl bg-muted/45 p-3.5"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-medium">
                            {{ item.category_name }}
                        </h3>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ item.scope_name }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right text-sm">
                        <SensitiveValue
                            class="font-semibold"
                            :value="money(item.actual_total_raw)"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ t('dashboard.budgetVsActual.of') }}
                            <SensitiveValue
                                :value="money(item.budget_total_raw)"
                            />
                        </p>
                    </div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full"
                        :class="
                            item.delta_raw >= 0
                                ? 'bg-[var(--dashboard-blue,var(--chart-1))]'
                                : 'bg-destructive'
                        "
                        :style="{ width: `${progress(item)}%` }"
                    />
                </div>
                <div
                    class="mt-2.5 flex items-center justify-between gap-3 text-xs"
                >
                    <span class="text-muted-foreground">
                        {{
                            t('dashboard.budgetVsActual.used', {
                                value: percentage(item.percentage_used),
                            })
                        }}
                    </span>
                    <span
                        class="text-right font-medium"
                        :class="
                            item.delta_raw >= 0
                                ? 'text-[var(--dashboard-emerald,var(--chart-2))]'
                                : 'text-destructive'
                        "
                    >
                        {{
                            item.delta_raw >= 0
                                ? t('dashboard.budgetVsActual.remaining', {
                                      value: item.delta,
                                  })
                                : t('dashboard.budgetVsActual.exceeded', {
                                      value: money(Math.abs(item.delta_raw)),
                                  })
                        }}
                    </span>
                </div>
            </article>
        </div>
        <p
            v-else
            class="mt-5 rounded-xl bg-muted/45 px-4 py-5 text-sm text-muted-foreground"
        >
            {{ t('dashboard.budgetVsActual.empty') }}
        </p>
    </section>
</template>
