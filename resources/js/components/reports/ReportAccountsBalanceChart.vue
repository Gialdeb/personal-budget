<script setup lang="ts">
import type { LineSeriesOption } from 'echarts/charts';
import type {
    GridComponentOption,
    LegendComponentOption,
    TooltipComponentOption,
} from 'echarts/components';
import type { ComposeOption, ECharts } from 'echarts/core';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import type { ReportAccountsData } from '@/types';

type BalanceChartOption = ComposeOption<
    | GridComponentOption
    | LegendComponentOption
    | TooltipComponentOption
    | LineSeriesOption
>;

type ChartRuntime = {
    init: any;
    use: any;
    LineChart: any;
    GridComponent: any;
    LegendComponent: any;
    TooltipComponent: any;
    CanvasRenderer: any;
};

const props = defineProps<{
    chart: ReportAccountsData['balance_trend'];
    currency: string;
    title: string;
    description: string;
    emptyLabel: string;
}>();

const { t } = useI18n();
const chartContainer = ref<HTMLDivElement | null>(null);
const chartInstance = shallowRef<ECharts | null>(null);
const chartReady = ref(false);
const { isPrivacyModeEnabled } = usePrivacyMode();

let resizeObserver: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;
let isUnmounted = false;
let chartRuntimePromise: Promise<ChartRuntime> | null = null;
let chartRuntimeRegistered = false;

const hasChartData = computed(
    () =>
        props.chart.labels.length > 0 &&
        props.chart.series.some((series) =>
            series.values.some((value) => Number(value) !== 0),
        ),
);

async function loadChartRuntime(): Promise<ChartRuntime> {
    if (!chartRuntimePromise) {
        chartRuntimePromise = Promise.all([
            import('echarts/core'),
            import('echarts/charts'),
            import('echarts/components'),
            import('echarts/renderers'),
        ]).then(([core, charts, components, renderers]) => ({
            init: core.init,
            use: core.use,
            LineChart: charts.LineChart,
            GridComponent: components.GridComponent,
            LegendComponent: components.LegendComponent,
            TooltipComponent: components.TooltipComponent,
            CanvasRenderer: renderers.CanvasRenderer,
        }));
    }

    const runtime = await chartRuntimePromise;

    if (!chartRuntimeRegistered) {
        runtime.use([
            runtime.LineChart,
            runtime.GridComponent,
            runtime.LegendComponent,
            runtime.TooltipComponent,
            runtime.CanvasRenderer,
        ]);
        chartRuntimeRegistered = true;
    }

    return runtime;
}

function readCssVariable(name: string, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    return value || fallback;
}

function formatCurrency(value: number): string {
    if (isPrivacyModeEnabled.value) {
        return 'Importo nascosto';
    }

    return formatAppCurrency(value, props.currency);
}

function buildChartOption(): BalanceChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');

    return {
        animationDuration: 650,
        color: props.chart.series.map((series) => series.color),
        grid: {
            left: 10,
            right: 16,
            top: 60,
            bottom: 26,
            containLabel: true,
        },
        legend: {
            top: 12,
            left: 10,
            itemWidth: 12,
            itemHeight: 8,
            textStyle: {
                color: mutedText,
                fontWeight: 600,
            },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'line',
                lineStyle: {
                    color: borderColor,
                    type: 'dashed',
                },
            },
            backgroundColor: popoverColor,
            borderColor,
            padding: [10, 12],
            textStyle: {
                color: foreground,
            },
            formatter: (params: any): string => {
                const items = Array.isArray(params) ? params : [params];
                const rows = items
                    .map(
                        (param) =>
                            `<span style="display:inline-block;width:8px;height:8px;border-radius:999px;background:${param.color};margin-right:6px"></span>${param.seriesName}: <strong>${formatCurrency(Number(param.value ?? 0))}</strong>`,
                    )
                    .join('<br>');

                return `<strong>${items[0]?.axisValueLabel ?? ''}</strong><br>${rows}`;
            },
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: props.chart.labels,
            axisTick: {
                show: false,
            },
            axisLabel: {
                color: mutedText,
                fontWeight: 600,
            },
            axisLine: {
                lineStyle: {
                    color: borderColor,
                },
            },
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                color: mutedText,
                formatter: (value: number) => formatCurrency(value),
            },
            splitLine: {
                lineStyle: {
                    color: borderColor,
                    type: 'dashed',
                },
            },
        },
        series: props.chart.series.map((series, index) => ({
            name: series.name,
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: index === 0 ? 7 : 5,
            showSymbol: false,
            emphasis: {
                focus: 'series',
                lineStyle: {
                    width: 5,
                },
            },
            lineStyle: {
                width: index === 0 ? 4 : 3,
                color: series.color,
            },
            areaStyle:
                index === 0
                    ? {
                          color: {
                              type: 'linear',
                              x: 0,
                              y: 0,
                              x2: 0,
                              y2: 1,
                              colorStops: [
                                  { offset: 0, color: `${series.color}30` },
                                  { offset: 1, color: `${series.color}00` },
                              ],
                          },
                      }
                    : undefined,
            data: series.values,
        })),
    };
}

function renderChart(): void {
    if (!chartInstance.value) {
        return;
    }

    chartInstance.value.setOption(buildChartOption(), true);
    chartInstance.value.resize();
    chartReady.value = true;
}

async function initializeChart(): Promise<void> {
    if (!chartContainer.value || !hasChartData.value) {
        chartReady.value = true;

        return;
    }

    try {
        const runtime = await loadChartRuntime();

        if (isUnmounted || !chartContainer.value) {
            return;
        }

        chartInstance.value = runtime.init(chartContainer.value, undefined, {
            renderer: 'canvas',
        });
    } catch (error) {
        console.error('Failed to load report accounts balance chart.', error);

        return;
    }

    renderChart();

    resizeObserver = new ResizeObserver(() => {
        chartInstance.value?.resize();
    });

    resizeObserver.observe(chartContainer.value);

    themeObserver = new MutationObserver(() => {
        renderChart();
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

watch(
    () => [props.chart, props.currency, isPrivacyModeEnabled.value],
    () => {
        if (!hasChartData.value) {
            chartInstance.value?.dispose();
            chartInstance.value = null;
            chartReady.value = true;

            return;
        }

        renderChart();
    },
    { deep: true },
);

onMounted(() => {
    void initializeChart();
});

onBeforeUnmount(() => {
    isUnmounted = true;
    resizeObserver?.disconnect();
    themeObserver?.disconnect();
    chartInstance.value?.dispose();
});
</script>

<template>
    <Card
        class="overflow-hidden rounded-[30px] border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.94))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.98),rgba(2,6,23,0.94))]"
        data-test="reports-accounts-balance-chart"
    >
        <CardHeader class="gap-2 pb-0">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <CardTitle class="text-xl tracking-tight">
                        {{ title }}
                    </CardTitle>
                    <CardDescription>
                        {{ description }}
                    </CardDescription>
                </div>
                <div
                    class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                >
                    {{ t('reports.overview.accountsPage.multiAccountTrend') }}
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-6">
            <div
                class="relative h-[320px] w-full overflow-hidden rounded-[24px] border border-border/60 bg-white/80 sm:h-[390px] dark:bg-white/3"
            >
                <div ref="chartContainer" class="h-full w-full" />

                <div
                    v-if="!chartReady"
                    class="absolute inset-0 flex flex-col gap-3 bg-background/90 p-4"
                >
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-full w-full" />
                </div>

                <div
                    v-else-if="!hasChartData"
                    class="absolute inset-0 flex items-center justify-center bg-background/90 px-6 text-center text-sm text-muted-foreground"
                >
                    {{ emptyLabel }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
