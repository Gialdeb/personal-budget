<script setup lang="ts">
import { ChevronDown, CircleHelp, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type { DashboardData } from '@/types/dashboard';

const props = defineProps<{
    commitment: DashboardData['analysis']['economic_commitment'];
    currency: string;
    periodLabel: string;
}>();
const { locale, t } = useI18n();
const expanded = ref(false);
const simulatedMonthlyCost = ref('0');
const simulatedCost = computed(() =>
    Math.max(0, Number(simulatedMonthlyCost.value) || 0),
);
const isSimulating = computed(() => simulatedCost.value > 0);
const capacity = computed(() =>
    props.commitment.remaining_monthly_raw === null
        ? null
        : props.commitment.monthly_raw + props.commitment.remaining_monthly_raw,
);
const effectiveCommitmentPercentage = computed(() =>
    !isSimulating.value
        ? (props.commitment.budget_ratio ?? 0)
        : capacity.value && capacity.value > 0
          ? ((props.commitment.monthly_raw + simulatedCost.value) /
                capacity.value) *
            100
          : 0,
);
const visualCommitmentPercentage = computed(() =>
    Math.max(0, Math.min(100, effectiveCommitmentPercentage.value)),
);
const status = computed(() =>
    effectiveCommitmentPercentage.value > 80
        ? 'overexposed'
        : effectiveCommitmentPercentage.value > 50
          ? 'balanced'
          : 'sustainable',
);
const commitmentAccent = computed(() =>
    status.value === 'overexposed'
        ? 'var(--destructive)'
        : status.value === 'balanced'
          ? 'var(--dashboard-violet,var(--chart-4))'
          : 'var(--dashboard-emerald,var(--chart-2))',
);
const simulatedRemaining = computed(() =>
    props.commitment.remaining_monthly_raw === null
        ? null
        : props.commitment.remaining_monthly_raw - simulatedCost.value,
);
const maximumComposition = computed(() =>
    Math.max(1, ...Object.values(props.commitment.composition)),
);
const composition = computed(
    () =>
        [
            ['recurring', props.commitment.composition.recurring_raw],
            ['installments', props.commitment.composition.installments_raw],
            ['debts', props.commitment.composition.debts_raw],
        ] as const,
);
function money(value: number): string {
    return formatCurrency(value, props.currency);
}
</script>

<template>
    <section class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
        <button
            type="button"
            class="flex w-full items-start justify-between gap-4 text-left"
            @click="expanded = !expanded"
        >
            <span class="block">
                <p
                    class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                >
                    {{ t('dashboard.analysis.commitment.title') }}
                </p>
                <h2 class="mt-1 text-lg font-semibold">
                    {{ t(`dashboard.analysis.commitment.${status}`) }}
                </h2>
                <span
                    v-if="isSimulating"
                    class="mt-1 inline-flex rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                    >{{ t('dashboard.analysis.commitment.simulation') }}</span
                >
            </span>
            <ChevronDown
                class="mt-1 size-5 text-muted-foreground transition"
                :class="expanded ? 'rotate-180' : ''"
            />
        </button>
        <div class="mt-5 flex items-center gap-5">
            <div
                class="grid size-28 shrink-0 place-items-center rounded-full"
                :style="{
                    background: `conic-gradient(${commitmentAccent} ${visualCommitmentPercentage}%, var(--muted) 0)`,
                }"
            >
                <div
                    class="grid size-20 place-items-center rounded-full bg-card text-2xl font-semibold"
                >
                    {{ Math.round(effectiveCommitmentPercentage) }}%
                </div>
            </div>
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.analysis.commitment.remaining') }}
                </p>
                <SensitiveValue
                    class="mt-1 block text-2xl font-semibold"
                    :value="
                        simulatedRemaining === null
                            ? '—'
                            : `${money(simulatedRemaining)} / ${t('dashboard.analysis.commitment.month')}`
                    "
                />
                <p class="mt-2 text-xs text-muted-foreground">
                    {{
                        t('dashboard.analysis.commitment.basedOn', {
                            month: periodLabel,
                        })
                    }}
                </p>
            </div>
        </div>
        <div v-if="expanded" class="mt-6 border-t pt-5">
            <h3 class="font-semibold">
                {{ t('dashboard.analysis.commitment.composition') }}
            </h3>
            <div class="mt-3 grid gap-3">
                <div
                    v-for="row in composition"
                    :key="row[0]"
                    class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm"
                >
                    <span>{{ t(`dashboard.analysis.detail.${row[0]}`) }}</span
                    ><SensitiveValue
                        class="font-semibold"
                        :value="money(row[1])"
                    />
                    <span class="h-1.5 overflow-hidden rounded-full bg-muted"
                        ><span
                            class="block h-full rounded-full bg-[var(--dashboard-violet,var(--chart-4))]"
                            :style="{
                                width: `${(row[1] / maximumComposition) * 100}%`,
                            }"
                    /></span>
                </div>
            </div>
            <div class="mt-6 rounded-xl bg-muted/45 p-4">
                <h3 class="flex items-center gap-2 font-semibold">
                    <CircleHelp class="size-4 text-primary" />{{
                        t('dashboard.analysis.commitment.canIAffordIt')
                    }}
                </h3>
                <label
                    class="mt-3 block text-sm text-muted-foreground"
                    for="monthly-cost"
                    >{{ t('dashboard.analysis.commitment.monthlyCost') }}</label
                >
                <div class="relative mt-2">
                    <MobileAmountInput
                        id="monthly-cost"
                        v-model="simulatedMonthlyCost"
                        :format-locale="locale"
                        :currency-code="currency"
                        placeholder="0"
                    /><button
                        v-if="isSimulating"
                        type="button"
                        class="absolute top-1/2 right-3 grid size-9 -translate-y-1/2 place-items-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="
                            t('dashboard.analysis.commitment.clearAmount')
                        "
                        @click="simulatedMonthlyCost = '0'"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <dl class="mt-4 grid gap-2 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{
                                t('dashboard.analysis.commitment.currentMargin')
                            }}
                        </dt>
                        <dd>
                            <SensitiveValue
                                class="font-semibold"
                                :value="
                                    commitment.remaining_monthly_raw === null
                                        ? '—'
                                        : money(
                                              commitment.remaining_monthly_raw,
                                          )
                                "
                            />
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ t('dashboard.analysis.commitment.newCost') }}
                        </dt>
                        <dd>
                            <SensitiveValue
                                class="font-semibold"
                                :value="money(simulatedCost)"
                            />
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{
                                t(
                                    'dashboard.analysis.commitment.remainingMargin',
                                )
                            }}
                        </dt>
                        <dd>
                            <SensitiveValue
                                class="font-semibold"
                                :class="
                                    (simulatedRemaining ?? 0) < 0
                                        ? 'text-destructive'
                                        : 'text-foreground'
                                "
                                :value="
                                    simulatedRemaining === null
                                        ? '—'
                                        : money(simulatedRemaining)
                                "
                            />
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="capacity !== null"
                    class="mt-3 text-xs text-muted-foreground"
                >
                    {{ Math.round(effectiveCommitmentPercentage) }}%
                    {{ t('dashboard.analysis.commitment.title').toLowerCase() }}
                </p>
                <p
                    v-if="(simulatedRemaining ?? 0) < 0"
                    class="mt-3 text-sm text-destructive"
                >
                    {{
                        t('dashboard.analysis.commitment.overCapacity', {
                            amount: money(Math.abs(simulatedRemaining ?? 0)),
                        })
                    }}
                </p>
            </div>
        </div>
    </section>
</template>
