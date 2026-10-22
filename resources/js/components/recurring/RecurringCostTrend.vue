<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { ECharts } from 'echarts/core';
import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import { show } from '@/actions/App/Http/Controllers/TransactionsController';
import RecurringManualCostSheet from '@/components/recurring/RecurringManualCostSheet.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { formatCurrency } from '@/lib/currency';
import {
    annualCostChartPoints,
    latestPaymentComparison,
    paymentMonthLabels,
    paymentYears,
    paymentsForYear,
    paymentStatistics,
    costChartPoints,
    realPaymentPoints,
} from '@/lib/recurring-cost-trend.js';
import type {
    CompoundRecurringCostYear,
    RecurringCostTrendPoint,
    RecurringCostChartPoint,
    RecurringManualCost,
    RecurringEntryShowPayload,
} from '@/types/transactions';

const props = withDefaults(
    defineProps<{
        points: RecurringCostTrendPoint[];
        annualSummaries?: CompoundRecurringCostYear[];
        currency: string;
        direction: string | null;
        recurringEntryUuid?: string;
        manualCosts?: RecurringManualCost[];
        priceHistory?: RecurringEntryShowPayload['amount_history'];
        fixed?: boolean;
        canEdit?: boolean;
        hideTrigger?: boolean;
        panelId?: string;
    }>(),
    {
        manualCosts: () => [],
        priceHistory: () => [],
        fixed: false,
        canEdit: false,
        recurringEntryUuid: '',
        hideTrigger: false,
        panelId: 'recurring-cost-trend-panel',
    },
);
const { t, locale } = useI18n();
const title = computed(() =>
    t(
        `recurringDetail.costTrend.title.${props.direction === 'income' ? 'income' : 'expense'}`,
    ),
);
const { isPrivacyModeEnabled } = usePrivacyMode();
const expanded = ref(false);
const panel = ref<HTMLElement | null>(null);
const chartElement = ref<HTMLDivElement | null>(null);
const chart = shallowRef<ECharts | null>(null);
let generation = 0;
let resizeObserver: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;
const costSheetOpen = ref(false);
const editingCost = ref<RecurringManualCost | null>(null);
const allPoints = computed<RecurringCostChartPoint[]>(() =>
    props.annualSummaries !== undefined
        ? annualCostChartPoints(props.annualSummaries)
        : costChartPoints(
              props.points,
              props.manualCosts,
              props.priceHistory,
              props.fixed,
          ),
);
const annualMode = computed(() => props.annualSummaries !== undefined);
const visibleAnnualSummaries = computed(() =>
    (props.annualSummaries ?? []).filter(
        (year) =>
            selectedYear.value === 'all' ||
            String(year.year) === selectedYear.value,
    ),
);
function annualChange(year: CompoundRecurringCostYear): string {
    if (!year.comparison) {
        return t('recurringDetail.costTrend.annual.unavailable');
    }

    const difference = formatCurrency(
        year.comparison.difference,
        props.currency,
    );
    const percentage =
        year.comparison.ratio === null
            ? '—'
            : new Intl.NumberFormat(locale.value, {
                  style: 'percent',
                  maximumFractionDigits: 1,
                  signDisplay: 'exceptZero',
              }).format(year.comparison.ratio);

    return `${year.comparison.difference > 0 ? '+' : ''}${difference} (${percentage}) · ${year.comparison.previous_year}`;
}
const years = computed(() => paymentYears(allPoints.value));
const selectedYear = ref(years.value.at(-1) ?? 'all');
const visiblePoints = computed<RecurringCostChartPoint[]>(() =>
    paymentsForYear(allPoints.value, selectedYear.value),
);
const visiblePayments = computed<RecurringCostTrendPoint[]>(() =>
    realPaymentPoints(visiblePoints.value),
);
const statistics = computed(() => paymentStatistics(visiblePayments.value));
const visibleManualCosts = computed(
    () =>
        paymentsForYear(
            props.manualCosts,
            selectedYear.value,
        ) as RecurringManualCost[],
);
function editCost(cost: RecurringManualCost | null): void {
    editingCost.value = cost;
    costSheetOpen.value = true;
}
const latestPayment = computed(() =>
    latestPaymentComparison(visiblePayments.value),
);
const yearIndex = computed(() => years.value.indexOf(selectedYear.value));
const periodLabel = computed(() =>
    selectedYear.value === 'all'
        ? t('recurringDetail.costTrend.allPeriod', {
              from: years.value[0] ?? '—',
              to: years.value.at(-1) ?? '—',
          })
        : selectedYear.value,
);
watch(years, (available) => {
    if (
        selectedYear.value !== 'all' &&
        !available.includes(selectedYear.value)
    ) {
        selectedYear.value = available.at(-1) ?? 'all';
    }
});
function navigateYear(direction: number): void {
    const year = years.value[yearIndex.value + direction];

    if (yearIndex.value >= 0 && year !== undefined) {
        selectedYear.value = year;
    }
}
const changeLabel = computed(() =>
    latestPayment.value.changeRatio === null
        ? '—'
        : new Intl.NumberFormat(locale.value, {
              style: 'percent',
              maximumFractionDigits: 1,
              signDisplay: 'exceptZero',
          }).format(latestPayment.value.changeRatio),
);
const monthLabels = computed(() =>
    paymentMonthLabels(
        visiblePoints.value,
        locale.value,
        selectedYear.value === 'all',
    ),
);
function money(value: number): string {
    return isPrivacyModeEnabled.value
        ? t('recurringDetail.costTrend.hidden')
        : formatCurrency(value, props.currency);
}
function date(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T12:00:00`));
}
function destination(point: RecurringCostChartPoint): string {
    const [year, month] = point.date.split('-').map(Number);

    return show.url(
        { year, month },
        {
            query: {
                highlight: point.transaction_uuid ?? '',
                source: 'recurring',
            },
        },
    );
}
function onChartClick(params: {
    componentType?: string;
    dataIndex?: number;
}): void {
    if (params.componentType !== 'series' || params.dataIndex === undefined) {
        return;
    }

    const point = visiblePoints.value[params.dataIndex];

    if (point?.source === 'real') {
        router.visit(destination(point));
    } else if (point?.source === 'manual' && props.canEdit) {
        const cost = props.manualCosts.find(
            (item) => item.uuid === point.manual_uuid,
        );

        if (cost) {
            editCost(cost);
        }
    }
}
function render(): void {
    const dark = document.documentElement.classList.contains('dark');
    const text = dark ? '#cbd5e1' : '#475569';
    chart.value?.setOption(
        {
            animation: false,
            grid: {
                left: 12,
                right: 20,
                top: 20,
                bottom: 20,
                containLabel: true,
            },
            tooltip: {
                trigger: 'axis',
                confine: true,
                renderMode: 'richText',
                valueFormatter: (value: number) => money(value),
            },
            xAxis: {
                type: 'category',
                data: visiblePoints.value.map((point) =>
                    annualMode.value
                        ? point.date.slice(0, 4)
                        : date(point.date),
                ),
                axisLabel: {
                    color: text,
                    hideOverlap: true,
                    interval: 'auto',
                    fontSize: 11,
                    formatter: (_value: string, index: number) =>
                        annualMode.value
                            ? (visiblePoints.value[index]?.date.slice(0, 4) ??
                              '')
                            : (monthLabels.value[index] ?? ''),
                },
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    color: text,
                    formatter: (value: number) => money(value),
                },
                splitLine: {
                    lineStyle: { color: dark ? '#334155' : '#e2e8f0' },
                },
            },
            series: (annualMode.value
                ? (['annual'] as const)
                : (['real', 'manual', 'automatic'] as const)
            ).map((source) => ({
                name: t(
                    source === 'annual'
                        ? 'recurringDetail.costTrend.annual.total'
                        : `recurringDetail.costTrend.origins.${source}`,
                ),
                type: 'line',
                data: visiblePoints.value.map((point) =>
                    point.source === source
                        ? isPrivacyModeEnabled.value
                            ? 0
                            : point.amount
                        : null,
                ),
                connectNulls: true,
                showSymbol: true,
                symbolSize: 14,
                triggerLineEvent: false,
                symbol:
                    source === 'manual'
                        ? 'diamond'
                        : source === 'automatic'
                          ? 'rect'
                          : 'circle',
                step: source === 'automatic' ? 'end' : false,
                lineStyle: {
                    color:
                        source === 'real' || source === 'annual'
                            ? '#8b5cf6'
                            : source === 'manual'
                              ? '#d97706'
                              : '#0ea5e9',
                    type: source === 'manual' ? 'dashed' : 'solid',
                },
                itemStyle: {
                    color:
                        source === 'real' || source === 'annual'
                            ? '#8b5cf6'
                            : source === 'manual'
                              ? '#d97706'
                              : '#0ea5e9',
                },
                emphasis: { scale: 1.5 },
            })),
        },
        true,
    );
}
function dispose(): void {
    generation++;
    resizeObserver?.disconnect();
    themeObserver?.disconnect();
    chart.value?.dispose();
    chart.value = null;
}
watch(expanded, async (value) => {
    dispose();

    if (!value) {
        return;
    }

    const current = generation;
    await nextTick();
    const [core, charts, components, renderers] = await Promise.all([
        import('echarts/core'),
        import('echarts/charts'),
        import('echarts/components'),
        import('echarts/renderers'),
    ]);

    if (current !== generation || !chartElement.value) {
        return;
    }

    core.use([
        charts.LineChart,
        components.GridComponent,
        components.TooltipComponent,
        renderers.CanvasRenderer,
    ]);
    chart.value = core.init(chartElement.value);
    chart.value.on('click', onChartClick);
    render();
    resizeObserver = new ResizeObserver(() => chart.value?.resize());
    resizeObserver.observe(chartElement.value);
    themeObserver = new MutationObserver(render);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});
watch([visiblePoints, selectedYear, locale, isPrivacyModeEnabled], render);
onBeforeUnmount(dispose);
async function open(): Promise<void> {
    expanded.value = true;
    await nextTick();
    const scrollOptions: ScrollIntoViewOptions = {
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto'
            : 'smooth',
        block: 'start',
    };

    if (props.hideTrigger && !chartElement.value) {
        const stop = watch(
            chartElement,
            (element) => {
                if (element) {
                    element.scrollIntoView(scrollOptions);
                    stop();
                }
            },
            { flush: 'post' },
        );

        return;
    }

    (props.hideTrigger ? chartElement.value : panel.value)?.scrollIntoView(
        scrollOptions,
    );
}
function toggle(): void {
    expanded.value = !expanded.value;
}
defineExpose({ open, toggle, expanded });
</script>

<template>
    <section
        ref="panel"
        :class="
            hideTrigger
                ? 'contents'
                : 'min-w-0 scroll-mt-24 rounded-2xl border bg-card shadow-sm'
        "
    >
        <Collapsible
            v-model:open="expanded"
            :class="hideTrigger ? 'contents' : undefined"
        >
            <CollapsibleTrigger
                v-if="!hideTrigger"
                class="flex min-h-12 w-full items-center justify-between gap-3 rounded-2xl px-4 py-3 text-left font-semibold focus-visible:outline-2 focus-visible:outline-ring sm:p-5"
            >
                <span
                    class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"
                >
                    <span
                        :class="
                            direction === 'income'
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : 'text-sky-700 dark:text-sky-300'
                        "
                        >{{ title }}</span
                    >
                    <span
                        class="text-xs font-normal text-muted-foreground"
                        aria-live="polite"
                        >{{ periodLabel }}</span
                    >
                </span>
                <ChevronDown
                    class="size-4 shrink-0 transition-transform"
                    :class="expanded ? 'rotate-180' : ''"
                />
            </CollapsibleTrigger>
            <CollapsibleContent
                :class="hideTrigger ? 'cost-panel-content min-w-0' : undefined"
            >
                <div
                    :id="panelId"
                    role="region"
                    :aria-label="title"
                    class="min-w-0 space-y-2 px-4 pb-3 sm:space-y-4 sm:px-5 sm:pb-5"
                    :class="
                        hideTrigger
                            ? 'rounded-2xl border bg-card pt-4 shadow-sm sm:pt-5'
                            : undefined
                    "
                >
                    <h3
                        v-if="hideTrigger"
                        class="font-semibold"
                        :class="
                            direction === 'income'
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : 'text-sky-700 dark:text-sky-300'
                        "
                    >
                        {{ title }}
                    </h3>
                    <div
                        v-if="years.length > 1"
                        class="flex items-center justify-end gap-1"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 shrink-0"
                            :disabled="yearIndex <= 0"
                            :aria-label="
                                t('recurringDetail.costTrend.previousYear')
                            "
                            @click="navigateYear(-1)"
                            ><ChevronLeft class="size-4"
                        /></Button>
                        <Select v-model="selectedYear">
                            <SelectTrigger
                                class="h-8 w-24 text-xs"
                                :aria-label="
                                    t('recurringDetail.costTrend.selectYear')
                                "
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{
                                    t('recurringDetail.costTrend.all')
                                }}</SelectItem>
                                <SelectItem
                                    v-for="year in years"
                                    :key="year"
                                    :value="year"
                                    >{{ year }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 shrink-0"
                            :disabled="
                                yearIndex < 0 || yearIndex >= years.length - 1
                            "
                            :aria-label="
                                t('recurringDetail.costTrend.nextYear')
                            "
                            @click="navigateYear(1)"
                            ><ChevronRight class="size-4"
                        /></Button>
                    </div>
                    <Button
                        v-if="canEdit"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        @click="editCost(null)"
                        >{{ t('recurringDetail.costTrend.addManual') }}</Button
                    >
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                annualMode
                                    ? 'recurringDetail.costTrend.annual.description'
                                    : 'recurringDetail.costTrend.description',
                            )
                        }}
                    </p>
                    <dl
                        v-if="visiblePayments.length"
                        class="grid grid-cols-2 gap-3"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ t('recurringDetail.costTrend.latest') }}
                            </dt>
                            <dd class="text-sm font-semibold sm:text-base">
                                <SensitiveValue
                                    :value="
                                        latestPayment.amount === null
                                            ? '—'
                                            : formatCurrency(
                                                  latestPayment.amount,
                                                  currency,
                                              )
                                    "
                                />
                            </dd>
                        </div>
                        <div class="text-right">
                            <dt class="text-xs text-muted-foreground">
                                {{ t('recurringDetail.costTrend.change') }}
                            </dt>
                            <dd class="text-sm font-semibold sm:text-base">
                                <SensitiveValue :value="changeLabel" />
                            </dd>
                        </div>
                    </dl>
                    <dl
                        v-if="visiblePayments.length"
                        class="grid grid-cols-3 gap-2 rounded-xl bg-muted/40 p-2 text-center sm:p-3"
                    >
                        <div
                            v-for="(value, key) in statistics"
                            :key="key"
                            class="min-w-0"
                        >
                            <dt class="text-xs text-muted-foreground">
                                {{ t(`recurringDetail.costTrend.${key}`) }}
                            </dt>
                            <dd class="mt-1 text-sm font-semibold sm:text-base">
                                <SensitiveValue
                                    :value="
                                        value === null
                                            ? '—'
                                            : formatCurrency(value, currency)
                                    "
                                />
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="!visiblePoints.length"
                        class="py-2 text-sm text-muted-foreground"
                    >
                        {{
                            t(
                                annualMode
                                    ? 'recurringDetail.costTrend.annual.empty'
                                    : 'recurringDetail.costTrend.empty',
                            )
                        }}
                    </p>
                    <div
                        class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            v-for="origin in (
                                ['real', 'manual', 'automatic'] as const
                            ).filter((source) =>
                                visiblePoints.some(
                                    (point) => point.source === source,
                                ),
                            )"
                            :key="origin"
                            class="inline-flex items-center gap-1"
                            ><span
                                class="size-2 rounded-full"
                                :class="
                                    origin === 'real'
                                        ? 'bg-violet-500'
                                        : origin === 'manual'
                                          ? 'bg-amber-600'
                                          : 'bg-sky-500'
                                "
                            />{{
                                t(`recurringDetail.costTrend.origins.${origin}`)
                            }}</span
                        >
                    </div>
                    <div
                        ref="chartElement"
                        class="h-48 w-full min-w-0 scroll-mt-24 sm:h-72"
                        role="img"
                        :aria-label="title"
                    />
                    <div v-if="annualMode" class="space-y-3">
                        <article
                            v-for="year in visibleAnnualSummaries"
                            :key="year.year"
                            class="rounded-xl border p-3 text-sm"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2 font-semibold"
                            >
                                <span
                                    >{{ year.year }} ·
                                    {{
                                        t(
                                            'recurringDetail.costTrend.annual.total',
                                        )
                                    }}</span
                                >
                                <SensitiveValue
                                    :value="
                                        formatCurrency(year.total, currency)
                                    "
                                />
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <SensitiveValue
                                    v-if="year.comparison"
                                    :value="annualChange(year)"
                                />
                                <span v-else>{{
                                    t(
                                        'recurringDetail.costTrend.annual.unavailable',
                                    )
                                }}</span>
                            </p>
                            <dl class="mt-2 space-y-1">
                                <div
                                    v-for="component in year.components"
                                    :key="component.uuid"
                                    class="flex items-center justify-between gap-2"
                                >
                                    <dt>
                                        {{ component.name }}
                                        <span
                                            v-if="component.archived"
                                            class="text-xs text-muted-foreground"
                                            >({{
                                                t(
                                                    'recurringDetail.costTrend.annual.archived',
                                                )
                                            }})</span
                                        >
                                    </dt>
                                    <dd>
                                        <SensitiveValue
                                            :value="
                                                formatCurrency(
                                                    component.total,
                                                    currency,
                                                )
                                            "
                                        />
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                    <div class="sr-only focus-within:not-sr-only">
                        <Link
                            v-for="point in visiblePoints.filter(
                                (item) => item.source === 'real',
                            )"
                            :key="point.key"
                            :href="destination(point)"
                            class="block rounded-md p-2 focus-visible:outline-2 focus-visible:outline-ring"
                            >{{ date(point.date) }} ·
                            {{ money(point.amount) }}</Link
                        >
                    </div>
                    <div
                        v-if="visibleManualCosts.length"
                        class="space-y-1 border-t pt-2"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            {{ t('recurringDetail.costTrend.manualList') }}
                        </p>
                        <div
                            v-for="cost in visibleManualCosts"
                            :key="cost.uuid"
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <span
                                >{{ date(cost.date) }} ·
                                <SensitiveValue
                                    :value="
                                        formatCurrency(
                                            cost.amount,
                                            cost.currency,
                                        )
                                    "
                            /></span>
                            <Button
                                v-if="canEdit"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 px-2 text-xs"
                                @click="editCost(cost)"
                                >{{
                                    t('recurringDetail.costTrend.manage')
                                }}</Button
                            >
                        </div>
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
        <RecurringManualCostSheet
            v-if="canEdit"
            v-model:open="costSheetOpen"
            :cost="editingCost"
            :recurring-entry-uuid="recurringEntryUuid"
            :currency="currency"
            @saved="selectedYear = $event.slice(0, 4)"
        />
    </section>
</template>

<style scoped>
.cost-panel-content {
    overflow: hidden;
}
.cost-panel-content[data-state='open'] {
    animation: cost-panel-open 180ms ease-out;
}
.cost-panel-content[data-state='closed'] {
    animation: cost-panel-close 140ms ease-in;
}
@keyframes cost-panel-open {
    from {
        height: 0;
        opacity: 0;
    }
    to {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
}
@keyframes cost-panel-close {
    from {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
    to {
        height: 0;
        opacity: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .cost-panel-content {
        animation: none !important;
    }
}
</style>
