<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard as dashboardRoute } from '@/routes';
import { pdf as monthlyRecapPdf } from '@/routes/monthly-recap';
import type { BreadcrumbItem, DashboardMonthlyRecap } from '@/types';

const props = defineProps<{
    recap: DashboardMonthlyRecap;
}>();

const { locale, t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('dashboard.title'),
        href: dashboardRoute(),
    },
    {
        title: t('dashboard.monthlyRecap.eyebrow'),
        href: '#',
    },
];

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
const pdfHref = computed(() =>
    monthlyRecapPdf.url(routeArgs.value, {
        query: routeQuery.value,
    }),
);
const periodLabel = computed(
    () => `${props.recap.period.label} ${props.recap.period.year}`,
);
const pageTitle = computed(
    () => `${t('dashboard.monthlyRecap.eyebrow')} · ${periodLabel.value}`,
);
const primarySentence = computed(
    () =>
        props.recap.insights[0]?.message ??
        t('dashboard.monthlyRecap.description'),
);

const moneyFormatter = computed(
    () =>
        new Intl.NumberFormat(locale.value, {
            style: 'currency',
            currency: props.recap.currency,
            minimumFractionDigits: 2,
        }),
);

const keyNumbers = computed(() => [
    {
        label: t('dashboard.monthlyRecap.startingBalance'),
        value: props.recap.totals.starting_balance_total,
        tone: 'neutral',
    },
    {
        label: t('dashboard.monthlyRecap.income'),
        value: props.recap.totals.income_total,
        tone: 'positive',
    },
    {
        label: t('dashboard.monthlyRecap.expenses'),
        value: props.recap.totals.expense_total,
        tone: 'negative',
    },
    {
        label: t('dashboard.monthlyRecap.endingBalance'),
        value: props.recap.totals.ending_balance_total,
        tone: 'neutral',
    },
]);

const comparisonRows = computed(() => [
    buildComparisonRow(
        t('dashboard.monthlyRecap.rowIncome'),
        props.recap.previous_totals.income_total_raw,
        props.recap.totals.income_total_raw,
        false,
    ),
    buildComparisonRow(
        t('dashboard.monthlyRecap.rowExpenses'),
        props.recap.previous_totals.expense_total_raw,
        props.recap.totals.expense_total_raw,
        true,
    ),
    buildComparisonRow(
        t('dashboard.monthlyRecap.rowNet'),
        props.recap.previous_totals.net_total_raw,
        props.recap.totals.net_total_raw,
        false,
    ),
    buildComparisonRow(
        t('dashboard.monthlyRecap.rowEndingBalance'),
        props.recap.previous_totals.ending_balance_total_raw,
        props.recap.totals.ending_balance_total_raw,
        false,
    ),
]);
const totalExpenseMovements = computed(() =>
    props.recap.top_expense_categories.reduce(
        (total, category) => total + category.transactions_count,
        0,
    ),
);

function buildComparisonRow(
    label: string,
    previous: number,
    current: number,
    inversePositive: boolean,
) {
    const delta = current - previous;
    const percentage =
        Math.abs(previous) >= 0.01 ? (delta / Math.abs(previous)) * 100 : null;
    const isPositive = inversePositive ? delta <= 0 : delta >= 0;

    return {
        label,
        previous: moneyFormatter.value.format(previous),
        current: moneyFormatter.value.format(current),
        delta,
        deltaAmount: formatSignedCurrency(delta),
        percentage:
            percentage === null
                ? null
                : `${percentage >= 0 ? '+' : ''}${percentage.toFixed(1)}%`,
        tone: isPositive ? 'positive' : 'negative',
    };
}

function formatSignedCurrency(value: number): string {
    return `${value >= 0 ? '+' : '-'}${moneyFormatter.value.format(Math.abs(value))}`;
}
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="flex min-h-full flex-1 justify-center bg-slate-50 p-3 sm:p-4 md:p-6 xl:p-8 dark:bg-[#080806]"
        >
            <article
                class="min-h-[1120px] w-full max-w-[960px] bg-[#fbfaf6] px-5 py-8 text-[#191817] shadow-sm ring-1 ring-black/10 sm:px-8 sm:py-10 md:px-14 md:py-16 xl:max-w-[1120px] xl:px-18 2xl:max-w-[min(1520px,calc(100vw-5rem))] 2xl:px-24 dark:bg-[#151411] dark:text-[#f2eee4] dark:ring-white/10"
            >
                <header class="space-y-5">
                    <div
                        class="flex items-end justify-between gap-4 border-b-4 border-double border-[#191817] pb-2 dark:border-[#efe7d8]"
                    >
                        <p
                            class="font-mono text-[10px] font-semibold tracking-[0.34em] text-[#5f5d57] uppercase dark:text-[#b8b1a4]"
                        >
                            {{ t('dashboard.monthlyRecap.reportKicker') }}
                        </p>
                        <p
                            class="font-mono text-[10px] tracking-[0.24em] text-[#5f5d57] uppercase dark:text-[#b8b1a4]"
                        >
                            {{
                                t('dashboard.monthlyRecap.reportNumber', {
                                    period: recap.period.key,
                                })
                            }}
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-[1fr_auto]">
                        <div>
                            <h1
                                class="text-4xl font-black tracking-tight md:text-5xl"
                            >
                                {{ t('dashboard.monthlyRecap.eyebrow') }}
                            </h1>
                            <p
                                class="mt-1 text-2xl text-[#5a5751] md:text-3xl dark:text-[#c8c2b6]"
                            >
                                {{ periodLabel }}
                            </p>
                        </div>
                        <div
                            class="font-mono text-xs leading-6 text-[#5a5751] md:text-right dark:text-[#c8c2b6]"
                        >
                            <p>{{ recap.period.starts_at }}</p>
                            <p>→ {{ recap.period.ends_at }}</p>
                            <p>
                                {{
                                    t('dashboard.monthlyRecap.transactions', {
                                        count: recap.totals.transactions_count,
                                    })
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 print:hidden">
                        <Button as-child variant="outline" class="rounded-none">
                            <Link :href="dashboardRoute()">
                                <ArrowLeft class="size-4" />
                                {{ t('dashboard.title') }}
                            </Link>
                        </Button>
                        <Button as-child class="rounded-none">
                            <a :href="pdfHref">
                                <FileText class="size-4" />
                                {{ t('dashboard.monthlyRecap.exportPdf') }}
                            </a>
                        </Button>
                    </div>
                </header>

                <section v-if="!recap.available" class="mt-14">
                    <p class="text-lg text-[#5a5751] dark:text-[#c8c2b6]">
                        {{ t('dashboard.monthlyRecap.empty') }}
                    </p>
                </section>

                <template v-else>
                    <section class="mt-14 space-y-3">
                        <p
                            class="font-mono text-[10px] font-semibold tracking-[0.34em] text-[#7a766d] uppercase dark:text-[#a9a194]"
                        >
                            {{ t('dashboard.monthlyRecap.inOnePhrase') }}
                        </p>
                        <p class="max-w-3xl text-2xl leading-snug font-bold">
                            {{ primarySentence }}
                        </p>
                    </section>

                    <section class="mt-14">
                        <div class="flex items-center gap-4">
                            <span
                                class="font-mono text-sm text-[#7a766d] dark:text-[#a9a194]"
                            >
                                I.
                            </span>
                            <h2
                                class="font-mono text-sm font-bold tracking-[0.16em] uppercase"
                            >
                                {{ t('dashboard.monthlyRecap.keyNumbers') }}
                            </h2>
                            <span
                                class="h-px flex-1 bg-[#191817] dark:bg-[#efe7d8]"
                            />
                        </div>

                        <div
                            class="mt-5 grid border-y border-[#191817] md:grid-cols-4 dark:border-[#efe7d8]"
                        >
                            <div
                                v-for="metric in keyNumbers"
                                :key="metric.label"
                                class="border-[#dedbd2] py-5 md:border-r md:px-4 first:md:pl-0 last:md:border-r-0 last:md:pr-0 dark:border-[#39352e]"
                            >
                                <p
                                    class="font-mono text-[10px] font-bold tracking-[0.28em] text-[#918d83] uppercase dark:text-[#9f9688]"
                                >
                                    {{ metric.label }}
                                </p>
                                <p
                                    :class="[
                                        'mt-3 text-3xl leading-tight font-bold',
                                        metric.tone === 'positive'
                                            ? 'text-[#3d704c] dark:text-[#76b58a]'
                                            : metric.tone === 'negative'
                                              ? 'text-[#a33833] dark:text-[#e47b73]'
                                              : 'text-[#191817] dark:text-[#f2eee4]',
                                    ]"
                                >
                                    <SensitiveValue
                                        variant="veil"
                                        :value="metric.value"
                                    />
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-8 flex flex-col gap-3 border-b border-[#dedbd2] pb-8 md:flex-row md:items-end md:justify-between dark:border-[#39352e]"
                        >
                            <div
                                class="font-mono text-[11px] font-semibold tracking-[0.28em] text-[#918d83] uppercase dark:text-[#9f9688]"
                            >
                                {{ t('dashboard.monthlyRecap.net') }}
                                <span class="ml-2 tracking-normal normal-case">
                                    {{ t('dashboard.monthlyRecap.income') }} -
                                    {{ t('dashboard.monthlyRecap.expenses') }}
                                </span>
                            </div>
                            <p
                                :class="[
                                    'text-5xl font-black tracking-tight',
                                    recap.totals.net_total_raw >= 0
                                        ? 'text-[#3d704c] dark:text-[#76b58a]'
                                        : 'text-[#a33833] dark:text-[#e47b73]',
                                ]"
                            >
                                <SensitiveValue
                                    variant="veil"
                                    :value="recap.totals.net_total"
                                />
                            </p>
                        </div>
                    </section>

                    <section class="mt-14">
                        <div class="flex items-center gap-4">
                            <span
                                class="font-mono text-sm text-[#7a766d] dark:text-[#a9a194]"
                            >
                                II.
                            </span>
                            <h2
                                class="font-mono text-sm font-bold tracking-[0.16em] uppercase"
                            >
                                {{ t('dashboard.monthlyRecap.comparison') }}
                            </h2>
                            <span
                                class="h-px flex-1 bg-[#191817] dark:bg-[#efe7d8]"
                            />
                            <span
                                class="text-sm text-[#5a5751] italic dark:text-[#c8c2b6]"
                            >
                                {{ recap.previous_period.label }}
                                {{ recap.previous_period.year }}
                            </span>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[640px] text-sm">
                                <thead
                                    class="border-y border-[#191817] font-mono text-[10px] tracking-[0.24em] text-[#5f5d57] uppercase dark:border-[#efe7d8] dark:text-[#b8b1a4]"
                                >
                                    <tr>
                                        <th class="py-3 text-left">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.comparisonItem',
                                                )
                                            }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.previous',
                                                )
                                            }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.month',
                                                )
                                            }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.variation',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in comparisonRows"
                                        :key="row.label"
                                        class="border-b border-[#e4e0d6] even:bg-[#f4f1ea] dark:border-[#302c26] dark:even:bg-[#1f1d19]"
                                    >
                                        <td class="py-4 font-semibold">
                                            {{ row.label }}
                                        </td>
                                        <td class="py-4 text-right font-mono">
                                            <SensitiveValue
                                                :value="row.previous"
                                            />
                                        </td>
                                        <td
                                            class="py-4 text-right font-mono font-bold"
                                        >
                                            <SensitiveValue
                                                :value="row.current"
                                            />
                                        </td>
                                        <td class="py-4 text-right">
                                            <p
                                                :class="[
                                                    'font-mono font-bold',
                                                    row.tone === 'positive'
                                                        ? 'text-[#3d704c] dark:text-[#76b58a]'
                                                        : 'text-[#a33833] dark:text-[#e47b73]',
                                                ]"
                                            >
                                                {{ row.percentage ?? '—' }}
                                            </p>
                                            <p
                                                class="font-mono text-xs text-[#918d83] dark:text-[#9f9688]"
                                            >
                                                <SensitiveValue
                                                    :value="row.deltaAmount"
                                                />
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="mt-14">
                        <div class="flex items-center gap-4">
                            <span
                                class="font-mono text-sm text-[#7a766d] dark:text-[#a9a194]"
                            >
                                III.
                            </span>
                            <h2
                                class="font-mono text-sm font-bold tracking-[0.16em] uppercase"
                            >
                                {{ t('dashboard.monthlyRecap.topCategories') }}
                            </h2>
                            <span
                                class="h-px flex-1 bg-[#191817] dark:bg-[#efe7d8]"
                            />
                            <span
                                class="text-sm text-[#5a5751] italic dark:text-[#c8c2b6]"
                            >
                                {{ t('dashboard.monthlyRecap.categoryHint') }}
                            </span>
                        </div>

                        <div
                            v-if="recap.top_expense_categories.length === 0"
                            class="mt-4 text-sm text-[#5a5751] dark:text-[#c8c2b6]"
                        >
                            {{ t('dashboard.expenseBreakdown.empty') }}
                        </div>

                        <div v-else class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[640px] text-sm">
                                <thead
                                    class="border-y border-[#191817] font-mono text-[10px] tracking-[0.24em] text-[#5f5d57] uppercase dark:border-[#efe7d8] dark:text-[#b8b1a4]"
                                >
                                    <tr>
                                        <th class="w-12 py-3 text-left">#</th>
                                        <th class="py-3 text-left">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.category',
                                                )
                                            }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.movementCountShort',
                                                )
                                            }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.share',
                                                )
                                            }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.amount',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            category, index
                                        ) in recap.top_expense_categories"
                                        :key="`${category.category_id}-${category.category_name}`"
                                        class="border-b border-[#e4e0d6] even:bg-[#f4f1ea] dark:border-[#302c26] dark:even:bg-[#1f1d19]"
                                    >
                                        <td
                                            class="py-4 font-mono text-xs text-[#918d83] dark:text-[#9f9688]"
                                        >
                                            {{
                                                String(index + 1).padStart(
                                                    2,
                                                    '0',
                                                )
                                            }}
                                        </td>
                                        <td class="py-4 font-semibold">
                                            {{ category.category_name }}
                                        </td>
                                        <td class="py-4 text-right font-mono">
                                            {{ category.transactions_count }}
                                        </td>
                                        <td class="py-4 text-right font-mono">
                                            {{ category.share }}%
                                        </td>
                                        <td
                                            class="py-4 text-right font-mono font-bold"
                                        >
                                            <SensitiveValue
                                                :value="category.total_amount"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr
                                        class="border-b-2 border-[#191817] font-mono font-bold tracking-[0.16em] uppercase dark:border-[#efe7d8]"
                                    >
                                        <td colspan="2" class="py-5">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.totalExpenses',
                                                )
                                            }}
                                        </td>
                                        <td class="py-5 text-right">
                                            {{ totalExpenseMovements }}
                                        </td>
                                        <td class="py-5 text-right">100%</td>
                                        <td
                                            class="py-5 text-right text-[#a33833] dark:text-[#e47b73]"
                                        >
                                            <SensitiveValue
                                                :value="
                                                    recap.totals.expense_total
                                                "
                                            />
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <section class="mt-14">
                        <div class="flex items-center gap-4">
                            <span
                                class="font-mono text-sm text-[#7a766d] dark:text-[#a9a194]"
                            >
                                IV.
                            </span>
                            <h2
                                class="font-mono text-sm font-bold tracking-[0.16em] uppercase"
                            >
                                {{ t('dashboard.monthlyRecap.mainMovements') }}
                            </h2>
                            <span
                                class="h-px flex-1 bg-[#191817] dark:bg-[#efe7d8]"
                            />
                            <span
                                class="text-sm text-[#5a5751] italic dark:text-[#c8c2b6]"
                            >
                                {{ t('dashboard.monthlyRecap.movementsHint') }}
                            </span>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[560px] text-sm">
                                <thead
                                    class="border-y border-[#191817] font-mono text-[10px] tracking-[0.24em] text-[#5f5d57] uppercase dark:border-[#efe7d8] dark:text-[#b8b1a4]"
                                >
                                    <tr>
                                        <th class="w-24 py-3 text-left">
                                            {{
                                                t('dashboard.monthlyRecap.date')
                                            }}
                                        </th>
                                        <th class="py-3 text-left">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.descriptionLabel',
                                                )
                                            }}
                                        </th>
                                        <th class="py-3 text-right">
                                            {{
                                                t(
                                                    'dashboard.monthlyRecap.amount',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="movement in recap.top_movements"
                                        :key="`${movement.date}-${movement.description}-${movement.amount_raw}`"
                                        class="border-b border-[#e4e0d6] even:bg-[#f4f1ea] dark:border-[#302c26] dark:even:bg-[#1f1d19]"
                                    >
                                        <td
                                            class="py-4 font-mono text-[#5f5d57] dark:text-[#b8b1a4]"
                                        >
                                            {{ movement.date }}
                                        </td>
                                        <td class="py-4 font-semibold">
                                            {{ movement.description }}
                                        </td>
                                        <td
                                            :class="[
                                                'py-4 text-right font-mono font-bold',
                                                movement.amount_raw >= 0
                                                    ? 'text-[#3d704c] dark:text-[#76b58a]'
                                                    : 'text-[#191817] dark:text-[#f2eee4]',
                                            ]"
                                        >
                                            <SensitiveValue
                                                :value="movement.amount"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </template>
            </article>
        </main>
    </AppLayout>
</template>
