<script setup lang="ts">
import type { BarSeriesOption } from 'echarts/charts';
import type {
    GridComponentOption,
    LegendComponentOption,
    TooltipComponentOption,
} from 'echarts/components';
import type { ComposeOption, ECharts } from 'echarts/core';
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import type { ReportCategoryTrendData } from '@/types';

type TrendOption = ComposeOption<
    | GridComponentOption
    | LegendComponentOption
    | TooltipComponentOption
    | BarSeriesOption
>;

type ChartRuntime = {
    init: any;
    use: any;
    BarChart: any;
    GridComponent: any;
    LegendComponent: any;
    TooltipComponent: any;
    CanvasRenderer: any;
};

const props = defineProps<{
    chart: ReportCategoryTrendData;
    currency: string;
    emptyLabel: string;
}>();

const chartContainer = ref<HTMLDivElement | null>(null);
const chartInstance = shallowRef<ECharts | null>(null);
const chartReady = ref(false);

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

function buildChartOption(): TrendOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');

    return {
        animationDuration: 500,
        grid: {
            left: 8,
            right: 8,
            top: 56,
            bottom: 12,
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
                        (item) =>
                            `${item.seriesName}: ${formatCurrency(Number(item.value ?? 0))}`,
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
        series: props.chart.series.map((series) => ({
            name: series.name,
            type: 'bar',
            stack: 'category-trend',
            barMaxWidth: 28,
            itemStyle: {
                color: series.color,
                borderRadius: [8, 8, 0, 0],
            },
            emphasis: {
                focus: 'series',
            },
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
    if (!chartContainer.value || props.chart.series.length === 0) {
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
        console.error('Failed to load report categories trend chart.', error);

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
        attributeFilter: ['class', 'style'],
    });
}

watch(
    () => props.chart,
    async () => {
        await nextTick();

        if (props.chart.series.length === 0) {
            chartInstance.value?.dispose();
            chartInstance.value = null;
            chartReady.value = true;

            return;
        }

        if (!chartInstance.value) {
            await initializeChart();

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
    <div
        v-if="chart.series.length === 0"
        class="flex h-full min-h-[260px] items-center justify-center rounded-[24px] border border-dashed border-slate-300/80 bg-slate-50/80 px-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
    >
        {{ emptyLabel }}
    </div>
    <div
        v-else
        class="relative h-[260px] overflow-hidden rounded-[24px] bg-slate-50/80 sm:h-[300px] dark:bg-slate-900/70"
    >
        <div ref="chartContainer" class="h-full w-full" />
        <div
            v-if="!chartReady"
            class="pointer-events-none absolute inset-0 flex items-center justify-center px-6"
        >
            <Skeleton class="h-44 w-full rounded-[24px]" />
        </div>
    </div>
</template>
