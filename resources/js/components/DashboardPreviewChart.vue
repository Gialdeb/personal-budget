<script setup lang="ts">
import type { LineSeriesOption } from 'echarts/charts';
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
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import type { DashboardTrendPoint } from '@/types/dashboard';

type TrendChartOption = ComposeOption<
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

type Props = {
    points: DashboardTrendPoint[];
    month: number | null;
    currency: string;
    title?: string;
    description?: string;
};

const props = withDefaults(defineProps<Props>(), {
    title: 'Andamento del periodo',
    description: 'Confronto tra entrate e uscite del periodo selezionato.',
});
const { locale, t } = useI18n();
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

function formatAxisCurrency(value: number): string {
    return formatCurrency(value);
}

function buildChartOption(): TrendChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');
    const incomeColor = readCssVariable('--dashboard-blue', '#2563eb');
    const expenseColor = readCssVariable('--dashboard-rose', '#f43f5e');

    const labels = props.points.map((point) =>
        props.month === null
            ? new Intl.DateTimeFormat(locale.value, { month: 'short' }).format(
                  new Date(2024, point.label - 1, 1),
              )
            : String(point.label),
    );

    return {
        animationDuration: 450,
        color: [incomeColor, expenseColor],
        grid: {
            left: 10,
            right: 10,
            top: 52,
            bottom: 24,
        },
        legend: {
            top: 4,
            itemWidth: 10,
            itemHeight: 10,
            textStyle: {
                color: mutedText,
            },
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
            boundaryGap: false,
            data: labels,
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
                formatter: (value: number) => formatAxisCurrency(value),
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
                name: t('app.enums.categoryGroups.income'),
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: {
                    width: 3,
                },
                areaStyle: {
                    opacity: 0.1,
                },
                data: props.points.map((point) => point.income_total_raw),
            },
            {
                name: t('dashboard.metrics.expensesPlural'),
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: {
                    width: 3,
                },
                areaStyle: {
                    opacity: 0.08,
                },
                data: props.points.map((point) => point.expense_total_raw),
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
    if (!chartContainer.value) {
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
        console.error('Failed to load dashboard chart.', error);

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
    () => [
        props.points,
        props.month,
        props.currency,
        isPrivacyModeEnabled.value,
    ],
    () => {
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
        class="overflow-hidden border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,251,255,0.92))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(22,28,45,0.98),rgba(11,18,32,0.94))]"
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
                class="relative h-[320px] w-full overflow-hidden rounded-[24px] border border-border/60 bg-white/75 dark:bg-white/3"
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
                    v-else-if="points.length === 0"
                    class="absolute inset-0 flex items-center justify-center bg-background/90 px-6 text-center text-sm text-muted-foreground"
                >
                    {{ t('dashboard.chart.empty') }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
