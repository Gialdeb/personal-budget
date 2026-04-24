<script setup lang="ts">
import type { BarSeriesOption, LineSeriesOption } from 'echarts/charts';
import type {
    GridComponentOption,
    LegendComponentOption,
    TooltipComponentOption,
} from 'echarts/components';
import type { ComposeOption, ECharts } from 'echarts/core';
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import type { ReportOverviewChartData } from '@/types';

type ComparisonChartOption = ComposeOption<
    | GridComponentOption
    | LegendComponentOption
    | TooltipComponentOption
    | BarSeriesOption
    | LineSeriesOption
>;

type ChartRuntime = {
    init: any;
    use: any;
    BarChart: any;
    LineChart: any;
    GridComponent: any;
    LegendComponent: any;
    TooltipComponent: any;
    CanvasRenderer: any;
};

const props = defineProps<{
    chart: ReportOverviewChartData;
    currency: string;
    title: string;
    description: string;
    emptyLabel: string;
}>();

const { t } = useI18n();
const chartContainer = ref<HTMLDivElement | null>(null);
const chartInstance = shallowRef<ECharts | null>(null);
const chartReady = ref(false);
const hasChartData = () =>
    props.chart.labels.length > 0 &&
    [...props.chart.income_values, ...props.chart.expense_values, ...props.chart.net_values].some(
        (value) => Number(value) !== 0,
    );

let resizeObserver: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;
let isUnmounted = false;
let chartRuntimePromise: Promise<ChartRuntime> | null = null;
let chartRuntimeRegistered = false;

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
            BarChart: charts.BarChart,
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
            runtime.BarChart,
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
    return formatAppCurrency(value, props.currency);
}

function buildChartOption(): ComparisonChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');

    return {
        animationDuration: 500,
        color: ['#059669', '#e11d48', '#0f172a'],
        grid: {
            left: 8,
            right: 8,
            top: 56,
            bottom: 20,
            containLabel: true,
        },
        legend: {
            top: 6,
            itemWidth: 10,
            itemHeight: 10,
            textStyle: {
                color: mutedText,
            },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow',
            },
            backgroundColor: popoverColor,
            borderColor,
            textStyle: {
                color: foreground,
            },
            formatter: (params: any): string => {
                const items = Array.isArray(params) ? params : [params];
                const rows = items
                    .map(
                        (param) =>
                            `${param.seriesName}: ${formatCurrency(Number(param.value ?? 0))}`,
                    )
                    .join('<br>');

                return `<strong>${items[0]?.axisValueLabel ?? ''}</strong><br>${rows}`;
            },
        },
        xAxis: {
            type: 'category',
            data: props.chart.labels,
            axisLabel: {
                color: mutedText,
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
        series: [
            {
                name: t('reports.overview.kpis.income'),
                type: 'bar',
                barMaxWidth: 22,
                borderRadius: [8, 8, 0, 0],
                data: props.chart.income_values,
            },
            {
                name: t('reports.overview.kpis.expense'),
                type: 'bar',
                barMaxWidth: 22,
                borderRadius: [8, 8, 0, 0],
                data: props.chart.expense_values,
            },
            {
                name: t('reports.overview.kpis.net'),
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                lineStyle: {
                    width: 3,
                },
                data: props.chart.net_values,
            },
        ],
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
    if (!chartContainer.value || !hasChartData()) {
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
        console.error(
            'Failed to load report overview comparison chart.',
            error,
        );

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
    () => [props.chart, props.currency],
    () => {
        if (!hasChartData()) {
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
        class="overflow-hidden rounded-[30px] border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(249,250,252,0.94))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(18,24,39,0.98),rgba(11,18,32,0.94))]"
        data-test="reports-overview-comparison-chart"
    >
        <CardHeader class="gap-2 pb-0">
            <CardTitle class="text-xl tracking-tight">
                {{ title }}
            </CardTitle>
            <CardDescription>
                {{ description }}
            </CardDescription>
        </CardHeader>

        <CardContent class="pt-6">
            <div
                class="relative h-[280px] w-full overflow-hidden rounded-[24px] border border-border/60 bg-white/75 sm:h-[340px] dark:bg-white/3"
            >
                <div ref="chartContainer" class="h-full w-full" />

                <div
                    v-if="!chartReady"
                    class="absolute inset-0 flex flex-col gap-3 bg-background/90 p-4"
                >
                    <Skeleton class="h-4 w-40" />
                    <Skeleton class="h-full w-full" />
                </div>

                <div
                    v-else-if="!hasChartData()"
                    class="absolute inset-0 flex items-center justify-center bg-background/90 px-6 text-center text-sm text-muted-foreground"
                >
                    {{ emptyLabel }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
