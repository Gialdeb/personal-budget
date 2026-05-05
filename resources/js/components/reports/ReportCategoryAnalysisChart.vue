<script setup lang="ts">
import type { BarSeriesOption, LineSeriesOption } from 'echarts/charts';
import type {
    GridComponentOption,
    LegendComponentOption,
    TooltipComponentOption,
} from 'echarts/components';
import type { ComposeOption, ECharts } from 'echarts/core';
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import type { ReportCategoryAnalysisChartData } from '@/types/report';

type ChartOption = ComposeOption<
    | BarSeriesOption
    | GridComponentOption
    | LegendComponentOption
    | LineSeriesOption
    | TooltipComponentOption
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
    chart: ReportCategoryAnalysisChartData;
    currency: string;
    emptyLabel: string;
    heightClass?: string;
}>();

const chartContainer = ref<HTMLDivElement | null>(null);
const chartInstance = shallowRef<ECharts | null>(null);
const chartReady = ref(false);
const { isPrivacyModeEnabled } = usePrivacyMode();

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
    if (isPrivacyModeEnabled.value) {
        return 'Importo nascosto';
    }

    return formatAppCurrency(value, props.currency);
}

function hasChartData(): boolean {
    return (
        props.chart.supported &&
        props.chart.series.some((series) =>
            series.values.some((value) => Number(value) !== 0),
        )
    );
}

function buildChartOption(): ChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');

    return {
        animationDuration: 500,
        color: props.chart.series.map((series) => series.color),
        grid: {
            left: 8,
            right: 18,
            top: 30,
            bottom: 16,
            containLabel: true,
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: popoverColor,
            borderColor,
            textStyle: {
                color: foreground,
            },
            formatter: (params: any): string => {
                const items = Array.isArray(params) ? params : [params];
                const lines = items
                    .map(
                        (item: any) =>
                            `${item?.marker ?? ''}${item?.seriesName ?? ''}: ${formatCurrency(Number(item?.value ?? 0))}`,
                    )
                    .join('<br>');

                return `<strong>${items[0]?.axisValueLabel ?? ''}</strong><br>${lines}`;
            },
        },
        legend: {
            top: 0,
            right: 0,
            textStyle: {
                color: mutedText,
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
            type: series.type ?? 'line',
            stack: series.stack,
            smooth: series.type === 'line',
            symbol: series.type === 'bar' ? 'none' : 'circle',
            symbolSize: 7,
            barMaxWidth: 32,
            lineStyle: {
                width: 3,
                type: series.style === 'dashed' ? 'dashed' : 'solid',
            },
            areaStyle:
                series.type === 'line' && series.style !== 'dashed'
                    ? {
                          opacity: 0.1,
                          color: series.color,
                      }
                    : undefined,
            itemStyle: {
                color: series.color,
                borderRadius: series.stack ? 0 : [6, 6, 0, 0],
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
        renderChart();
    } catch (error) {
        console.error('Failed to load category analysis chart.', error);
        chartReady.value = true;
    }
}

onMounted(() => {
    void initializeChart();

    if (typeof ResizeObserver !== 'undefined' && chartContainer.value) {
        resizeObserver = new ResizeObserver(() =>
            chartInstance.value?.resize(),
        );
        resizeObserver.observe(chartContainer.value);
    }

    if (typeof MutationObserver !== 'undefined') {
        themeObserver = new MutationObserver(() => renderChart());
        themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class', 'style'],
        });
    }
});

onBeforeUnmount(() => {
    isUnmounted = true;
    resizeObserver?.disconnect();
    themeObserver?.disconnect();
    chartInstance.value?.dispose();
});

watch(
    () => [props.chart, isPrivacyModeEnabled.value],
    () => renderChart(),
    { deep: true },
);
</script>

<template>
    <div class="relative min-h-[300px]">
        <div
            v-show="chartReady && hasChartData()"
            ref="chartContainer"
            :class="heightClass ?? 'h-[300px]'"
            class="w-full"
        />
        <div
            v-if="chartReady && !hasChartData()"
            :class="heightClass ?? 'h-[300px]'"
            class="flex items-center justify-center rounded-2xl border border-dashed border-border/80 px-4 text-center text-sm text-muted-foreground"
        >
            {{ emptyLabel }}
        </div>
        <Skeleton
            v-if="!chartReady"
            :class="heightClass ?? 'h-[300px]'"
            class="rounded-2xl"
        />
    </div>
</template>
