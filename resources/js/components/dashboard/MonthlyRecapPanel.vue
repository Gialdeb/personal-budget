<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import {
    pdf as monthlyRecapPdf,
    show as monthlyRecapShow,
} from '@/routes/monthly-recap';
import type { DashboardMonthlyRecap } from '@/types/dashboard';

const props = defineProps<{ recap: DashboardMonthlyRecap }>();
const { t } = useI18n();
const expanded = ref(false);
const routeArgs = computed(() => ({
    year: props.recap.period.year,
    month: props.recap.period.month,
}));
const routeQuery = computed(() => ({
    account_scope: props.recap.scope.account_scope,
    ...(props.recap.scope.account_uuid
        ? { account_uuid: props.recap.scope.account_uuid }
        : {}),
}));
const showHref = computed(() =>
    monthlyRecapShow.url(routeArgs.value, { query: routeQuery.value }),
);
const pdfHref = computed(() =>
    monthlyRecapPdf.url(routeArgs.value, { query: routeQuery.value }),
);
const netTone = computed(() =>
    props.recap.totals.net_total_raw >= 0
        ? 'text-[var(--dashboard-emerald,var(--chart-2))]'
        : 'text-destructive',
);
</script>

<template>
    <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="min-w-0">
                <span
                    class="rounded-full bg-[var(--dashboard-emerald-soft,var(--muted))] px-2.5 py-1 text-[10px] font-semibold text-[var(--dashboard-emerald,var(--chart-2))]"
                    >{{ t('dashboard.monthlyRecap.eyebrow') }}</span
                >
                <h2 class="mt-2 text-lg font-semibold">
                    {{
                        t('dashboard.monthlyRecap.title', {
                            month: recap.period.label,
                        })
                    }}
                </h2>
                <p
                    v-if="recap.available"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    <SensitiveValue :value="recap.totals.expense_total" />
                    {{ t('dashboard.metrics.expenses').toLowerCase() }},
                    <SensitiveValue
                        :class="netTone"
                        :value="recap.totals.net_total"
                    />
                    {{ t('dashboard.monthlyRecap.net').toLowerCase() }}
                </p>
                <p v-else class="mt-1 text-sm text-muted-foreground">
                    {{ t('dashboard.monthlyRecap.empty') }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Link
                    :href="showHref"
                    class="inline-flex items-center gap-1.5 rounded-xl border bg-background px-3 py-2 text-sm font-medium transition hover:bg-muted"
                    >{{ t('dashboard.monthlyRecap.openFull')
                    }}<ChevronRight class="size-4"
                /></Link>
                <a
                    :href="pdfHref"
                    class="hidden items-center gap-1.5 rounded-xl border bg-background px-3 py-2 text-sm font-medium transition hover:bg-muted sm:inline-flex"
                    ><FileText class="size-4" />{{
                        t('dashboard.monthlyRecap.exportPdf')
                    }}</a
                >
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-xl border transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-expanded="expanded"
                    :aria-label="
                        expanded
                            ? t('dashboard.monthlyRecap.dismiss')
                            : t('dashboard.monthlyRecap.openFull')
                    "
                    @click="expanded = !expanded"
                >
                    <ChevronDown
                        class="size-4 transition-transform"
                        :class="expanded ? 'rotate-180' : ''"
                    />
                </button>
            </div>
        </div>
        <div v-if="expanded && recap.available" class="mt-5 border-t pt-5">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('dashboard.monthlyRecap.startingBalance') }}
                    </dt>
                    <dd class="mt-1 font-semibold">
                        <SensitiveValue
                            :value="recap.totals.starting_balance_total"
                        />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('dashboard.monthlyRecap.endingBalance') }}
                    </dt>
                    <dd class="mt-1 font-semibold">
                        <SensitiveValue
                            :value="recap.totals.ending_balance_total"
                        />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('dashboard.monthlyRecap.income') }}
                    </dt>
                    <dd
                        class="mt-1 font-semibold text-[var(--dashboard-emerald,var(--chart-2))]"
                    >
                        <SensitiveValue :value="recap.totals.income_total" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('dashboard.monthlyRecap.expenses') }}
                    </dt>
                    <dd class="mt-1 font-semibold text-destructive">
                        <SensitiveValue :value="recap.totals.expense_total" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('dashboard.monthlyRecap.net') }}
                    </dt>
                    <dd class="mt-1 font-semibold" :class="netTone">
                        <SensitiveValue :value="recap.totals.net_total" />
                    </dd>
                </div>
            </dl>
            <div
                class="mt-5 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
            >
                <span>{{ t('dashboard.monthlyRecap.flows') }}</span>
                <span>{{
                    t('dashboard.monthlyRecap.transactions', {
                        count: recap.totals.transactions_count,
                    })
                }}</span>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-muted">
                <span
                    class="block h-full bg-[linear-gradient(90deg,var(--dashboard-emerald,var(--chart-2))_0,var(--dashboard-emerald,var(--chart-2))_var(--income-share),var(--destructive)_var(--income-share),var(--destructive)_100%)]"
                    :style="{
                        '--income-share': `${recap.totals.income_share}%`,
                    }"
                />
            </div>
            <div
                class="mt-2 flex flex-wrap justify-between gap-2 text-xs text-muted-foreground"
            >
                <span>{{
                    t('dashboard.monthlyRecap.incomeShare', {
                        value: recap.totals.income_share,
                    })
                }}</span
                ><span>{{
                    t('dashboard.monthlyRecap.expenseShare', {
                        value: recap.totals.expense_share,
                    })
                }}</span>
            </div>
            <ul
                v-if="recap.insights.length"
                class="mt-5 grid gap-2 lg:grid-cols-2"
            >
                <li
                    v-for="insight in recap.insights"
                    :key="`${insight.type}-${insight.message}`"
                    class="rounded-xl bg-muted/45 px-3 py-2 text-sm"
                >
                    {{ insight.message }}
                </li>
            </ul>
        </div>
    </section>
</template>
