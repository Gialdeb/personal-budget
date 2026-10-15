<script setup lang="ts">
import { Layers3, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useUiCopy } from '@/i18n/ui-copy';
import { formatCurrency } from '@/lib/currency';

const props = defineProps<{
    amount: string;
    total: string;
    count: string;
    amountLabel: string;
    currency: string;
    locale: string;
    income: boolean;
}>();
const ui = useUiCopy();
const money = (value: string) => {
    const number = Number(value.replace(',', '.'));

    return value !== '' && Number.isFinite(number) && number > 0
        ? formatCurrency(number, props.currency, props.locale)
        : '—';
};
const totalDisplay = computed(() => money(props.total));
</script>

<template>
    <section
        class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 sm:p-5 dark:border-slate-700/70 dark:from-slate-900 dark:to-slate-900/60"
        :aria-label="ui('Il tuo piano rateale')"
    >
        <div
            class="mb-2 flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400"
        >
            <Wallet class="size-4" aria-hidden="true" />
            {{ amountLabel }}
        </div>
        <div
            class="min-h-9 text-3xl font-semibold tracking-tight break-words text-slate-950 tabular-nums sm:text-4xl dark:text-white"
            data-testid="installment-amount"
        >
            {{ money(amount) }}
        </div>
        <div class="mt-3 grid grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] gap-2">
            <div
                class="min-w-0 rounded-2xl border p-3 sm:p-3.5"
                :class="
                    income
                        ? 'border-emerald-200/70 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
                        : 'border-rose-200/70 bg-rose-50 text-rose-900 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-100'
                "
            >
                <div class="mb-1 flex items-center gap-1.5 text-xs font-medium">
                    <span
                        class="size-1.5 shrink-0 rounded-full"
                        :class="income ? 'bg-emerald-500' : 'bg-rose-400'"
                        aria-hidden="true"
                    />
                    {{ ui('Totale piano') }}
                </div>
                <div
                    class="relative min-h-8"
                    aria-live="polite"
                    aria-atomic="true"
                    data-testid="installment-total"
                >
                    <p
                        :key="totalDisplay"
                        class="text-2xl leading-8 font-semibold tracking-tight break-words tabular-nums sm:text-2xl"
                    >
                        {{ totalDisplay }}
                    </p>
                </div>
            </div>
            <div
                class="min-w-0 rounded-2xl border border-sky-200/70 bg-sky-50 p-3 text-sky-900 sm:p-3.5 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100"
            >
                <div class="mb-1 flex items-center gap-1.5 text-xs font-medium">
                    <Layers3 class="size-3.5 shrink-0" aria-hidden="true" />
                    {{ ui('Rate previste') }}
                </div>
                <p
                    class="text-2xl leading-8 font-semibold break-words tabular-nums sm:text-2xl"
                    data-testid="installment-count"
                >
                    {{ Number(count) > 0 ? count : '—' }}
                </p>
            </div>
        </div>
        <p
            v-if="!total"
            class="mt-3 text-xs text-slate-500 dark:text-slate-400"
        >
            {{ ui('Inserisci importo e numero di rate') }}
        </p>
    </section>
</template>

<style scoped>
.plan-total-value {
    animation: plan-total-update 240ms ease-out;
}
@keyframes plan-total-update {
    from {
        opacity: 0.4;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .plan-total-value {
        animation: none;
    }
}
</style>
