<script setup lang="ts">
import { Check, ChevronDown, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type { DashboardData } from '@/types/dashboard';

const props = defineProps<{
    capacity: DashboardData['analysis']['spending_capacity'];
    currency: string;
    includeForecast: boolean;
    includeDebts: boolean;
    includeCredits: boolean;
}>();
const emit = defineEmits<{
    'update:includeForecast': [value: boolean];
    'update:includeDebts': [value: boolean];
    'update:includeCredits': [value: boolean];
}>();
const { t } = useI18n();
const expanded = ref(false);
const includeForecast = computed({
    get: () => props.includeForecast,
    set: (value: boolean) => emit('update:includeForecast', value),
});
const includeDebts = computed({
    get: () => props.includeDebts,
    set: (value: boolean) => emit('update:includeDebts', value),
});
const includeCredits = computed({
    get: () => props.includeCredits,
    set: (value: boolean) => emit('update:includeCredits', value),
});
const projection = computed(
    () =>
        props.capacity.remaining_budget_raw +
        (includeForecast.value
            ? props.capacity.simulation_impacts.forecast_expenses_raw
            : 0) +
        (includeDebts.value
            ? props.capacity.simulation_impacts.open_debts_raw
            : 0) +
        (includeCredits.value
            ? props.capacity.simulation_impacts.open_credits_raw
            : 0),
);
const progress = computed(() =>
    props.capacity.budget_total_raw > 0
        ? Math.min(
              100,
              Math.max(
                  0,
                  (props.capacity.expenses_raw /
                      props.capacity.budget_total_raw) *
                      100,
              ),
          )
        : 0,
);
const rows = computed(() => [
    {
        key: 'forecast',
        model: includeForecast,
        impact: props.capacity.simulation_impacts.forecast_expenses_raw,
    },
    {
        key: 'debts',
        model: includeDebts,
        impact: props.capacity.simulation_impacts.open_debts_raw,
    },
    {
        key: 'credits',
        model: includeCredits,
        impact: props.capacity.simulation_impacts.open_credits_raw,
    },
]);
function money(value: number): string {
    return formatCurrency(value, props.currency);
}
</script>

<template>
    <section
        class="flex h-full flex-col rounded-2xl border bg-card p-5 shadow-sm sm:p-6"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                >
                    {{
                        capacity.is_budget_exceeded
                            ? t('dashboard.analysis.budgetExceededBy')
                            : t('dashboard.analysis.spendingCapacity')
                    }}
                </p>
                <SensitiveValue
                    class="mt-2 block text-4xl font-semibold tracking-tight"
                    :class="
                        capacity.is_budget_exceeded
                            ? 'text-destructive'
                            : 'text-[var(--dashboard-emerald,var(--chart-2))]'
                    "
                    :value="
                        capacity.available
                            ? money(Math.abs(projection))
                            : t('dashboard.analysis.notAvailable')
                    "
                />
                <p
                    v-if="capacity.available"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ t('dashboard.analysis.selectedMonthHint') }}
                </p>
            </div>
            <span
                class="grid size-11 shrink-0 place-items-center rounded-full"
                :class="
                    capacity.is_budget_exceeded
                        ? 'bg-destructive/10 text-destructive'
                        : 'bg-[var(--dashboard-emerald-soft,var(--muted))] text-[var(--dashboard-emerald,var(--chart-2))]'
                "
            >
                <TriangleAlert
                    v-if="capacity.is_budget_exceeded"
                    class="size-5"
                />
                <Check v-else class="size-5" />
            </span>
        </div>

        <div v-if="capacity.available" class="mt-6">
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all"
                    :class="
                        capacity.is_budget_exceeded
                            ? 'bg-destructive'
                            : 'bg-[var(--dashboard-emerald,var(--chart-2))]'
                    "
                    :style="{ width: `${progress}%` }"
                />
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        {{ t('dashboard.analysis.spent') }}
                    </dt>
                    <dd class="mt-1 font-semibold">
                        <SensitiveValue :value="money(capacity.expenses_raw)" />
                    </dd>
                </div>
                <div class="text-right">
                    <dt
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        {{ t('dashboard.metrics.budget') }}
                    </dt>
                    <dd class="mt-1 font-semibold">
                        <SensitiveValue
                            :value="money(capacity.budget_total_raw)"
                        />
                    </dd>
                </div>
            </dl>
        </div>

        <div
            v-if="capacity.available"
            class="mt-5 rounded-xl border bg-muted/35 p-3"
        >
            <button
                type="button"
                role="switch"
                :aria-checked="includeForecast"
                class="flex min-h-10 w-full items-center justify-between gap-3 text-left"
                @click="includeForecast = !includeForecast"
            >
                <span
                    ><span class="block text-sm font-semibold">{{
                        t('dashboard.analysis.toggle.forecast')
                    }}</span
                    ><span class="text-xs text-muted-foreground">{{
                        money(capacity.simulation_impacts.forecast_expenses_raw)
                    }}</span></span
                >
                <span
                    class="relative h-6 w-11 rounded-full transition"
                    :class="
                        includeForecast
                            ? 'bg-primary'
                            : 'bg-muted-foreground/25'
                    "
                    ><span
                        class="absolute top-0.5 size-5 rounded-full bg-background shadow transition"
                        :class="includeForecast ? 'left-5' : 'left-0.5'"
                /></span>
            </button>
            <button
                type="button"
                class="mt-2 flex min-h-9 w-full items-center justify-between border-t pt-2 text-xs font-medium text-muted-foreground"
                @click="expanded = !expanded"
            >
                {{ t('dashboard.analysis.moreSimulationOptions')
                }}<ChevronDown
                    class="size-4 transition"
                    :class="expanded ? 'rotate-180' : ''"
                />
            </button>
            <div v-if="expanded" class="mt-1 grid gap-1">
                <button
                    v-for="row in rows.slice(1)"
                    :key="row.key"
                    type="button"
                    role="switch"
                    :aria-checked="row.model.value"
                    class="flex min-h-10 items-center justify-between rounded-lg px-2 text-sm hover:bg-background"
                    @click="row.model.value = !row.model.value"
                >
                    <span>{{ t(`dashboard.analysis.toggle.${row.key}`) }}</span
                    ><span class="flex items-center gap-3"
                        ><SensitiveValue
                            class="text-xs text-muted-foreground"
                            :value="money(row.impact)" /><span
                            class="relative h-5 w-9 rounded-full transition"
                            :class="
                                row.model.value
                                    ? 'bg-primary'
                                    : 'bg-muted-foreground/25'
                            "
                            ><span
                                class="absolute top-0.5 size-4 rounded-full bg-background shadow transition"
                                :class="
                                    row.model.value
                                        ? 'left-[1.125rem]'
                                        : 'left-0.5'
                                " /></span
                    ></span>
                </button>
            </div>
        </div>

        <dl
            v-if="capacity.available"
            class="mt-auto grid grid-cols-3 gap-2 border-t pt-5 text-center"
        >
            <div>
                <dt
                    class="text-[10px] font-semibold text-muted-foreground uppercase"
                >
                    {{ t('dashboard.analysis.baseValue') }}
                </dt>
                <dd class="mt-1 text-sm font-semibold">
                    <SensitiveValue
                        :value="money(capacity.remaining_budget_raw)"
                    />
                </dd>
            </div>
            <div>
                <dt
                    class="text-[10px] font-semibold text-muted-foreground uppercase"
                >
                    {{ t('dashboard.analysis.withForecast') }}
                </dt>
                <dd class="mt-1 text-sm font-semibold">
                    <SensitiveValue
                        :value="
                            money(
                                capacity.remaining_budget_raw +
                                    capacity.simulation_impacts
                                        .forecast_expenses_raw,
                            )
                        "
                    />
                </dd>
            </div>
            <div>
                <dt
                    class="text-[10px] font-semibold text-muted-foreground uppercase"
                >
                    {{ t('dashboard.analysis.difference') }}
                </dt>
                <dd class="mt-1 text-sm font-semibold">
                    <SensitiveValue
                        :value="
                            money(projection - capacity.remaining_budget_raw)
                        "
                    />
                </dd>
            </div>
        </dl>
    </section>
</template>
