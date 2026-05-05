<script setup lang="ts">
import type { BarSeriesOption } from 'echarts/charts';
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

type CashFlowChartOption = ComposeOption<
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
    cashFlow: ReportAccountsData['cash_flow'];
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
        props.cashFlow.has_data &&
        props.cashFlow.labels.length > 0 &&
        [
            ...props.cashFlow.income_values,
            ...props.cashFlow.expense_values,
        ].some((value) => Number(value) !== 0),
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
    if (isPrivacyModeEnabled.value) {
        return 'Importo nascosto';
    }

    return formatAppCurrency(Math.abs(value), props.currency);
}

function buildChartOption(): CashFlowChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');

    return {
        animationDuration: 600,
        color: ['#16a34a', '#ef4444'],
        grid: {
            left: 8,
            right: 8,
            top: 58,
            bottom: 18,
            containLabel: true,
        },
        legend: {
            top: 8,
            itemWidth: 10,
            itemHeight: 10,
            textStyle: {
                color: mutedText,
                fontWeight: 600,
            },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow',
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
                            `${param.marker}${param.seriesName}: <strong>${formatCurrency(Number(param.value ?? 0))}</strong>`,
                    )
                    .join('<br>');

                return `<strong>${items[0]?.axisValueLabel ?? ''}</strong><br>${rows}`;
            },
        },
        xAxis: {
            type: 'category',
            data: props.cashFlow.labels,
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
        series: [
            {
                name: t('reports.overview.accountsPage.income'),
                type: 'bar',
                stack: 'cash-flow',
                barMaxWidth: 24,
                itemStyle: {
                    borderRadius: [8, 8, 0, 0],
                },
                data: props.cashFlow.income_values,
            },
            {
                name: t('reports.overview.accountsPage.expense'),
                type: 'bar',
                stack: 'cash-flow',
                barMaxWidth: 24,
                itemStyle: {
                    borderRadius: [0, 0, 8, 8],
                },
                data: props.cashFlow.expense_values.map(
                    (value) => -Math.abs(value),
                ),
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
        console.error('Failed to load report accounts cash flow chart.', error);

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
    () => [props.cashFlow, props.currency, isPrivacyModeEnabled.value],
    () => {
        if (!hasChartData.value) {
            chartInstance.value?.dispose();
            chartInstance.value = null;
            chartReady.value = true;

            return;
        }

        if (!chartInstance.value) {
            chartReady.value = false;
            void initializeChart();

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
        class="overflow-hidden rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
        data-test="reports-accounts-cash-flow-chart"
    >
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>
        <CardContent>
            <div
                class="relative h-[290px] overflow-hidden rounded-[24px] border border-border/60 bg-white/75 dark:bg-white/3"
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
                    v-else-if="!hasChartData"
                    class="absolute inset-0 flex items-center justify-center bg-background/90 px-6 text-center text-sm text-muted-foreground"
                >
                    {{ emptyLabel }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
