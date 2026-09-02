<script setup lang="ts">
import type { BarSeriesOption, LineSeriesOption } from 'echarts/charts';
import type { GridComponentOption } from 'echarts/components';
import type { ComposeOption, ECharts } from 'echarts/core';
import {
    BarChart3,
    CalendarClock,
    Sparkles,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type { DashboardData } from '@/types/dashboard';

type Detail = DashboardData['analysis']['month_detail'];
type DetailOption = ComposeOption<
    GridComponentOption | BarSeriesOption | LineSeriesOption
>;

const props = defineProps<{ detail: Detail; currency: string }>();
const { locale, t } = useI18n();
const element = ref<HTMLDivElement | null>(null);
const chart = shallowRef<ECharts | null>(null);
let resizeObserver: ResizeObserver | null = null;

const title = computed(() =>
    props.detail.period.state === 'future'
        ? t('dashboard.analysis.detailNarrative.futureTitle')
        : props.detail.period.state === 'current'
          ? t('dashboard.analysis.detailNarrative.currentTitle')
          : t('dashboard.analysis.detailNarrative.pastTitle'),
);
const header = computed(() => {
    const params = {
        month: selectedLabel.value,
        amount: money(props.detail.narrative.selected_expense_raw),
    };

    if (props.detail.period.state === 'future') {
        return t('dashboard.analysis.detailNarrative.futureHeader', params);
    }

    if (props.detail.period.state === 'current') {
        return t('dashboard.analysis.detailNarrative.currentHeader', params);
    }

    return t('dashboard.analysis.detailNarrative.pastHeader', params);
});
const selectedLabel = computed(() =>
    monthLabel(props.detail.period.month, props.detail.period.year),
);
const comparison = computed(
    () => props.detail.narrative.difference_from_average_raw,
);
const average = computed(() => props.detail.narrative.comparison_average_raw);
const kpis = computed<Array<[string, string, number | null]>>(() => {
    const narrative = props.detail.narrative;

    if (props.detail.period.state === 'future') {
        return [
            ['average', t('dashboard.analysis.monthlyAverage'), average.value],
            [
                'calendar',
                t('dashboard.analysis.nextMonths'),
                narrative.future_total_raw,
            ],
            [
                'heavy',
                t('dashboard.analysis.heavyMonths'),
                narrative.heavy_future_months,
            ],
        ];
    }

    if (props.detail.period.state === 'current') {
        return [
            ['average', t('dashboard.analysis.monthlyAverage'), average.value],
            [
                'projection',
                t('dashboard.analysis.detailNarrative.closingProjection'),
                narrative.selected_expense_raw,
            ],
            [
                'heavy',
                t('dashboard.analysis.heavyMonths'),
                narrative.heavy_future_months,
            ],
        ];
    }

    return [
        [
            'average',
            t('dashboard.analysis.detailNarrative.periodAverage'),
            average.value,
        ],
        ['difference', t('dashboard.analysis.difference'), comparison.value],
        [
            'position',
            t('dashboard.analysis.detailNarrative.monthPosition'),
            periodRank.value,
        ],
    ];
});
const periodRank = computed(() => {
    const ordered = [...props.detail.narrative.window]
        .filter((point) => point.state !== 'future')
        .sort(
            (left, right) => right.actual_expense_raw - left.actual_expense_raw,
        );
    const index = ordered.findIndex((point) => point.is_selected);

    return index === -1 ? null : index + 1;
});
const insights = computed(() => {
    const amount = props.detail.narrative.selected_expense_raw;
    const difference = comparison.value;
    const futureLightest = props.detail.narrative.lightest_future;
    const items: Array<{
        tone: 'warning' | 'positive' | 'info';
        title: string;
        description: string;
        icon: typeof Sparkles;
    }> = [];

    if (props.detail.period.state === 'future') {
        items.push({
            tone: difference !== null && difference > 0 ? 'warning' : 'info',
            title: t('dashboard.analysis.detailNarrative.prepareTitle', {
                month: selectedLabel.value,
            }),
            description: t(
                'dashboard.analysis.detailNarrative.futureDescription',
                { month: selectedLabel.value, amount: money(amount) },
            ),
            icon: CalendarClock,
        });
    } else if (props.detail.period.state === 'current') {
        items.push({
            tone: 'info',
            title: t('dashboard.analysis.detailNarrative.currentInsightTitle'),
            description: t(
                'dashboard.analysis.detailNarrative.currentDescription',
                {
                    month: selectedLabel.value,
                    amount: money(props.detail.real.expense_raw),
                },
            ),
            icon: BarChart3,
        });
    } else {
        items.push({
            tone: difference !== null && difference > 0 ? 'warning' : 'info',
            title: t('dashboard.analysis.detailNarrative.pastInsightTitle', {
                month: selectedLabel.value,
            }),
            description: t(
                'dashboard.analysis.detailNarrative.pastDescription',
                { month: selectedLabel.value, amount: money(amount) },
            ),
            icon: BarChart3,
        });
    }

    if (difference === null) {
        items.push({
            tone: 'info',
            title: t(
                'dashboard.analysis.detailNarrative.comparisonUnavailableTitle',
            ),
            description: t(
                'dashboard.analysis.detailNarrative.comparisonUnavailableDescription',
            ),
            icon: Sparkles,
        });
    } else {
        items.push({
            tone: difference > 0 ? 'warning' : 'positive',
            title: t('dashboard.analysis.detailNarrative.vsAverageTitle'),
            description: t(
                'dashboard.analysis.detailNarrative.vsAverageDescription',
                { amount: money(Math.abs(difference)) },
            ),
            icon: difference > 0 ? TrendingUp : TrendingDown,
        });
    }

    if (futureLightest) {
        items.push({
            tone: 'positive',
            title: t('dashboard.analysis.detailNarrative.lightestTitle'),
            description: t(
                'dashboard.analysis.detailNarrative.lightestDescription',
                {
                    month: monthLabel(
                        futureLightest.month,
                        futureLightest.year,
                    ),
                    amount: money(futureLightest.projected_expense_raw),
                },
            ),
            icon: TrendingDown,
        });
    }

    return items.slice(0, 3);
});

function money(value: number | null): string {
    return value === null ? '—' : formatCurrency(value, props.currency);
}
function monthLabel(month: number, year: number): string {
    return new Intl.DateTimeFormat(locale.value, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, 1));
}
function shortMonth(month: number, year: number): string {
    return new Intl.DateTimeFormat(locale.value, { month: 'short' })
        .format(new Date(year, month - 1, 1))
        .replace('.', '');
}
function css(name: string, fallback: string): string {
    return (
        getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim() || fallback
    );
}
function chartOption(): DetailOption {
    const points = props.detail.narrative.window;
    const foreground = css('--foreground', '#171717');
    const muted = css('--muted-foreground', '#737373');
    const primary = css('--primary', '#2563eb');
    const warning = css('--destructive', '#ef4444');
    const forecast = css('--chart-4', '#a3a3a3');
    const values = points.map((point) =>
        point.state === 'past'
            ? point.actual_expense_raw
            : point.projected_expense_raw,
    );
    const averageValue = values
        .filter((value) => value > 0)
        .reduce((sum, value, _index, array) => sum + value / array.length, 0);

    return {
        animationDuration: 250,
        grid: { left: 4, right: 4, top: 36, bottom: 30, containLabel: true },
        xAxis: {
            type: 'category',
            data: points.map((point) => shortMonth(point.month, point.year)),
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: {
                color: muted,
                fontWeight: 600,
                fontSize: 10,
                margin: 12,
            },
        },
        yAxis: {
            type: 'value',
            show: false,
            max: (value: { max: number }) => Math.max(value.max * 1.24, 1),
        },
        series: [
            {
                type: 'bar',
                barWidth: '44%',
                data: values,
                itemStyle: {
                    borderRadius: [7, 7, 2, 2],
                    color: (params: { dataIndex: number }) => {
                        const point = points[params.dataIndex];

                        if (point.is_selected) {
                            return warning;
                        }

                        return point.state === 'future' ? forecast : primary;
                    },
                    opacity: 0.9,
                },
                label: {
                    show: true,
                    position: 'top',
                    distance: 8,
                    color: foreground,
                    fontSize: 10,
                    fontWeight: 600,
                    formatter: (params) =>
                        money(Number(params.value ?? 0)).replace(
                            /,00(?=\s|$)/,
                            '',
                        ),
                },
                markLine:
                    averageValue > 0
                        ? {
                              silent: true,
                              symbol: 'none',
                              lineStyle: {
                                  type: 'dashed',
                                  color: muted,
                                  opacity: 0.65,
                              },
                              label: {
                                  show: true,
                                  formatter: t('dashboard.analysis.average'),
                                  color: muted,
                                  fontSize: 10,
                              },
                              data: [{ yAxis: averageValue }],
                          }
                        : undefined,
            },
        ],
    };
}
async function mountChart(): Promise<void> {
    if (!element.value) {
        return;
    }

    const [{ init, use }, { BarChart }, { GridComponent }, { CanvasRenderer }] =
        await Promise.all([
            import('echarts/core'),
            import('echarts/charts'),
            import('echarts/components'),
            import('echarts/renderers'),
        ]);
    use([BarChart, GridComponent, CanvasRenderer]);
    chart.value?.dispose();
    chart.value = init(element.value);
    chart.value.setOption(chartOption());
    resizeObserver = new ResizeObserver(() => chart.value?.resize());
    resizeObserver.observe(element.value);
}
onMounted(() => void mountChart());
watch(
    () => props.detail,
    () => chart.value?.setOption(chartOption()),
    { deep: true },
);
onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chart.value?.dispose();
});
</script>

<template>
    <section class="grid gap-5">
        <header
            class="rounded-2xl border border-primary/15 bg-primary/5 p-4 sm:p-5"
        >
            <p
                class="text-xs font-semibold tracking-[0.14em] text-primary uppercase"
            >
                {{ title }}
            </p>
            <p class="mt-2 text-lg leading-7 font-semibold">
                {{ header }}
            </p>
        </header>
        <section class="rounded-2xl border bg-card p-4 sm:p-5">
            <p
                class="flex items-center gap-2 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
            >
                <BarChart3 class="size-4" />{{
                    t('dashboard.analysis.detailNarrative.projection')
                }}
            </p>
            <div ref="element" class="mt-2 h-52 w-full" />
            <div class="mt-4 grid grid-cols-3 divide-x rounded-xl bg-muted/45">
                <div
                    v-for="kpi in kpis"
                    :key="kpi[0]"
                    class="min-w-0 p-3 text-center"
                >
                    <p
                        class="truncate text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ kpi[1] }}
                    </p>
                    <SensitiveValue
                        class="mt-1 block text-sm font-semibold sm:text-base"
                        :value="
                            kpi[0] === 'heavy' || kpi[0] === 'position'
                                ? kpi[2] === null
                                    ? '—'
                                    : String(kpi[2])
                                : money(kpi[2] as number | null)
                        "
                    />
                </div>
            </div>
        </section>
        <section>
            <p
                class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
            >
                {{ t('dashboard.analysis.insights') }}
            </p>
            <div class="mt-3 grid gap-3">
                <article
                    v-for="insight in insights"
                    :key="insight.title"
                    class="flex gap-3 rounded-2xl border p-4"
                    :class="
                        insight.tone === 'warning'
                            ? 'border-destructive/20 bg-destructive/5'
                            : insight.tone === 'positive'
                              ? 'border-[var(--dashboard-emerald,var(--chart-2))]/20 bg-[var(--dashboard-emerald-soft,var(--muted))]'
                              : 'border-primary/15 bg-primary/5'
                    "
                >
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-full"
                        :class="
                            insight.tone === 'warning'
                                ? 'bg-destructive/10 text-destructive'
                                : insight.tone === 'positive'
                                  ? 'bg-[var(--dashboard-emerald,var(--chart-2))]/10 text-[var(--dashboard-emerald,var(--chart-2))]'
                                  : 'bg-primary/10 text-primary'
                        "
                        ><component :is="insight.icon" class="size-4"
                    /></span>
                    <div>
                        <h3 class="font-semibold">{{ insight.title }}</h3>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">
                            {{ insight.description }}
                        </p>
                    </div>
                </article>
            </div>
        </section>
    </section>
</template>
