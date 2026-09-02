<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type { DashboardParentCategoryBudgetItem } from '@/types/dashboard';

const props = defineProps<{
    items: DashboardParentCategoryBudgetItem[];
    currency: string;
}>();
const { locale, t } = useI18n();
const rows = computed(() =>
    [...props.items].sort((left, right) => {
        if (left.delta_raw === right.delta_raw) {
            return left.category_name.localeCompare(
                right.category_name,
                locale.value,
            );
        }

        return left.delta_raw - right.delta_raw;
    }),
);

function money(value: number): string {
    return formatCurrency(value, props.currency);
}
function percentage(value: number): string {
    return `${new Intl.NumberFormat(locale.value, {
        maximumFractionDigits: 0,
    }).format(value)}%`;
}
function progress(item: DashboardParentCategoryBudgetItem): number {
    if (item.actual_total_raw <= 0) {
        return 0;
    }

    if (item.budget_total_raw <= 0) {
        return 100;
    }

    return Math.min(Math.max(item.percentage_used, 8), 100);
}
</script>

<template>
    <section class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">
                    {{ t('dashboard.categoryTargets.title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('dashboard.categoryTargets.description') }}
                </p>
            </div>
            <span
                class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
            >
                {{
                    t('dashboard.categoryTargets.groups', {
                        count: rows.length,
                    })
                }}
            </span>
        </div>

        <div v-if="rows.length" class="mt-5 grid gap-3">
            <div
                class="hidden grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,0.8fr))] gap-3 rounded-xl bg-muted/50 px-4 py-3 text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase lg:grid"
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
            <article
                v-for="item in rows"
                :key="item.category_id"
                class="rounded-xl border bg-background p-4"
            >
                <div class="flex items-start justify-between gap-3 lg:hidden">
                    <div class="min-w-0">
                        <h3 class="truncate font-medium">
                            {{ item.category_name }}
                        </h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                item.delta_raw >= 0
                                    ? t(
                                          'dashboard.categoryTargets.mobile.inControl',
                                      )
                                    : t(
                                          'dashboard.categoryTargets.mobile.needsAttention',
                                      )
                            }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            item.delta_raw >= 0
                                ? 'bg-[var(--dashboard-emerald-soft,var(--muted))] text-[var(--dashboard-emerald,var(--chart-2))]'
                                : 'bg-destructive/10 text-destructive'
                        "
                        >{{ percentage(item.percentage_used) }}</span
                    >
                </div>
                <div
                    class="hidden grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,0.8fr))] items-center gap-3 lg:grid"
                >
                    <div class="min-w-0">
                        <h3 class="truncate font-medium">
                            {{ item.category_name }}
                        </h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                item.delta_raw >= 0
                                    ? t(
                                          'dashboard.categoryTargets.mobile.inControl',
                                      )
                                    : t(
                                          'dashboard.categoryTargets.mobile.needsAttention',
                                      )
                            }}
                        </p>
                    </div>
                    <SensitiveValue
                        class="text-right text-sm font-medium"
                        :value="money(item.budget_total_raw)"
                    />
                    <SensitiveValue
                        class="text-right text-sm font-medium"
                        :value="money(item.actual_total_raw)"
                    />
                    <span
                        class="text-right text-sm font-medium"
                        :class="
                            item.delta_raw >= 0
                                ? 'text-[var(--dashboard-emerald,var(--chart-2))]'
                                : 'text-destructive'
                        "
                    >
                        {{
                            item.delta_raw >= 0
                                ? `+${item.delta}`
                                : `-${money(Math.abs(item.delta_raw))}`
                        }}
                    </span>
                    <span
                        class="justify-self-end rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            item.delta_raw >= 0
                                ? 'bg-[var(--dashboard-emerald-soft,var(--muted))] text-[var(--dashboard-emerald,var(--chart-2))]'
                                : 'bg-destructive/10 text-destructive'
                        "
                    >
                        {{ percentage(item.percentage_used) }}
                    </span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 lg:hidden">
                    <div class="rounded-lg bg-muted/45 p-3">
                        <dt
                            class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                        >
                            {{ t('dashboard.categoryTargets.headers.target') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium">
                            <SensitiveValue
                                :value="money(item.budget_total_raw)"
                            />
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/45 p-3">
                        <dt
                            class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                        >
                            {{ t('dashboard.categoryTargets.headers.actual') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium">
                            <SensitiveValue
                                :value="money(item.actual_total_raw)"
                            />
                        </dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <div
                        class="flex items-center justify-between gap-3 text-xs"
                    >
                        <span class="text-muted-foreground">{{
                            t('dashboard.categoryTargets.trend.label')
                        }}</span>
                        <span
                            class="font-semibold"
                            :class="
                                item.delta_raw >= 0
                                    ? 'text-[var(--dashboard-emerald,var(--chart-2))]'
                                    : 'text-destructive'
                            "
                        >
                            {{
                                item.delta_raw >= 0
                                    ? t(
                                          'dashboard.categoryTargets.trend.within',
                                      )
                                    : t('dashboard.categoryTargets.trend.over')
                            }}
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full"
                            :class="
                                item.delta_raw >= 0
                                    ? 'bg-[var(--dashboard-emerald,var(--chart-2))]'
                                    : 'bg-destructive'
                            "
                            :style="{ width: `${progress(item)}%` }"
                        />
                    </div>
                </div>
            </article>
        </div>
        <p
            v-else
            class="mt-5 rounded-xl bg-muted/45 px-4 py-5 text-sm text-muted-foreground"
        >
            {{ t('dashboard.categoryTargets.empty') }}
        </p>
    </section>
</template>
