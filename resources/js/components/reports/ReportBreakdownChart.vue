<script setup lang="ts">
import type { PieSeriesOption } from 'echarts/charts';
import type {
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import type { DashboardCategoryBreakdownItem } from '@/types';

type BreakdownChartOption = ComposeOption<
    LegendComponentOption | TooltipComponentOption | PieSeriesOption
>;

type ChartRuntime = {
    init: any;
    use: any;
    PieChart: any;
    LegendComponent: any;
    TooltipComponent: any;
    CanvasRenderer: any;
};

const props = defineProps<{
    items: DashboardCategoryBreakdownItem[];
    currency: string;
    title: string;
    description: string;
    emptyLabel: string;
}>();

const chartContainer = ref<HTMLDivElement | null>(null);
const chartInstance = shallowRef<ECharts | null>(null);
const chartReady = ref(false);
const visibleItems = computed(() => props.items.slice(0, 6));

let resizeObserver: ResizeObserver | null = null;
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
            PieChart: charts.PieChart,
            LegendComponent: components.LegendComponent,
            TooltipComponent: components.TooltipComponent,
            CanvasRenderer: renderers.CanvasRenderer,
        }));
    }

    const runtime = await chartRuntimePromise;

    if (!chartRuntimeRegistered) {
        runtime.use([
            runtime.PieChart,
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

function buildChartOption(): BreakdownChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');
    const palette = [
        '#2563eb',
        '#f43f5e',
        '#10b981',
        '#f59e0b',
        '#7c3aed',
        '#14b8a6',
    ];

    return {
        animationDuration: 450,
        color: palette,
        legend: {
            bottom: 0,
            icon: 'circle',
            textStyle: {
                color: mutedText,
            },
        },
        tooltip: {
            trigger: 'item',
            backgroundColor: popoverColor,
            borderColor,
            textStyle: {
                color: foreground,
            },
            formatter: (params: any): string =>
                `${params.name}<br>${formatCurrency(Number(params.value ?? 0))}`,
        },
        series: [
            {
                type: 'pie',
                radius: ['54%', '76%'],
                center: ['50%', '44%'],
                avoidLabelOverlap: true,
                label: {
                    show: false,
                },
                emphasis: {
                    scale: true,
                    itemStyle: {
                        shadowBlur: 14,
                        shadowOffsetY: 4,
                        shadowColor: 'rgba(15, 23, 42, 0.18)',
                    },
                },
                data: visibleItems.value.map((item) => ({
                    name: item.category_name,
                    value: item.total_amount_raw,
                })),
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

async function mountChart(): Promise<void> {
    if (!chartContainer.value || visibleItems.value.length === 0) {
        chartReady.value = true;

        return;
    }

    const runtime = await loadChartRuntime();

    if (isUnmounted || !chartContainer.value) {
        return;
    }

    chartInstance.value = runtime.init(chartContainer.value, undefined, {
        renderer: 'canvas',
    });

    renderChart();

    resizeObserver = new ResizeObserver(() => {
        chartInstance.value?.resize();
    });
    resizeObserver.observe(chartContainer.value);
}

watch(visibleItems, () => {
    if (visibleItems.value.length === 0) {
        chartReady.value = true;
        chartInstance.value?.dispose();
        chartInstance.value = null;

        return;
    }

    if (chartInstance.value) {
        renderChart();
    }
});

onMounted(() => {
    void mountChart();
});

onBeforeUnmount(() => {
    isUnmounted = true;
    resizeObserver?.disconnect();
    chartInstance.value?.dispose();
});
</script>

<template>
    <Card
        class="overflow-hidden rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
    >
        <CardHeader class="space-y-2">
            <CardTitle
                class="text-base font-semibold text-slate-950 dark:text-slate-50"
            >
                {{ title }}
            </CardTitle>
            <CardDescription>
                {{ description }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div
                v-if="visibleItems.length === 0"
                class="rounded-3xl border border-dashed border-slate-300/80 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
            >
                {{ emptyLabel }}
            </div>
            <div
                v-else
                class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
            >
                <div
                    class="relative h-72 overflow-hidden rounded-[24px] bg-slate-50/80 dark:bg-slate-900/70"
                >
                    <div ref="chartContainer" class="h-full w-full" />
                    <div
                        v-if="!chartReady"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center px-6"
                    >
                        <Skeleton class="h-40 w-40 rounded-full" />
                    </div>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="item in visibleItems"
                        :key="`${title}-${item.category_name}`"
                        class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ item.category_name }}
                            </p>
                            <p
                                class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                            >
                                {{ item.total_amount }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
