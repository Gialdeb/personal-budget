<script setup lang="ts">
import {
    ArrowDownRight,
    Landmark,
    PiggyBank,
    ReceiptText,
    ShieldAlert,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { BudgetPlanningSummaryCard } from '@/types';

defineProps<{
    cards: BudgetPlanningSummaryCard[];
    currency: string;
}>();
const { t } = useI18n();

const styles = computed<Record<string, string>>(() => ({
    income: 'from-emerald-500/16 via-white to-white text-emerald-700 dark:from-emerald-500/18 dark:via-slate-950 dark:to-slate-950 dark:text-emerald-300',
    remaining:
        'from-sky-500/16 via-white to-white text-sky-700 dark:from-sky-500/18 dark:via-slate-950 dark:to-slate-950 dark:text-sky-300',
    expense:
        'from-slate-900/6 via-white to-white text-slate-700 dark:from-white/6 dark:via-slate-950 dark:to-slate-950 dark:text-slate-100',
    bill: 'from-cyan-500/16 via-white to-white text-cyan-700 dark:from-cyan-500/18 dark:via-slate-950 dark:to-slate-950 dark:text-cyan-300',
    debt: 'from-rose-500/16 via-white to-white text-rose-700 dark:from-rose-500/18 dark:via-slate-950 dark:to-slate-950 dark:text-rose-300',
    saving: 'from-violet-500/16 via-white to-white text-violet-700 dark:from-violet-500/18 dark:via-slate-950 dark:to-slate-950 dark:text-violet-300',
}));

function iconFor(key: string) {
    return {
        income: Landmark,
        remaining: WalletCards,
        expense: ArrowDownRight,
        bill: ReceiptText,
        debt: ShieldAlert,
        saving: PiggyBank,
    }[key];
}
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        <Card
            v-for="card in cards"
            :key="card.key"
            :class="
                cn(
                    'overflow-hidden border-white/70 bg-white/85 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-950/70',
                    'bg-gradient-to-br',
                    styles[card.key] ?? styles.expense,
                )
            "
        >
            <CardContent class="flex h-full flex-col gap-5 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p
                            class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{ card.label }}
                        </p>
                        <p
                            class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            <SensitiveValue
                                variant="veil"
                                :value="
                                    formatCurrency(card.amount_raw, currency)
                                "
                            />
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-current/10 bg-white/70 p-2.5 shadow-sm dark:bg-white/5"
                    >
                        <component :is="iconFor(card.key)" class="size-4" />
                    </div>
                </div>

                <div
                    class="mt-auto flex items-end justify-between gap-4 text-sm"
                >
                    <p class="text-slate-500 dark:text-slate-400">
                        {{ t('planning.summaryCards.annualPlan') }}
                    </p>
                    <p
                        v-if="card.share_of_income !== null"
                        class="rounded-full bg-black/5 px-2.5 py-1 text-xs font-semibold dark:bg-white/10"
                    >
                        {{
                            t('planning.summaryCards.incomeShare', {
                                value: card.share_of_income.toFixed(1),
                            })
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
