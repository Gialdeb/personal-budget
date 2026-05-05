<script setup lang="ts">
import type { BarSeriesOption } from 'echarts/charts';
import type {
    GridComponentOption,
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
import SensitiveValue from '@/components/SensitiveValue.vue';
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
import type { DashboardAccountSummaryItem } from '@/types';

type AccountsChartOption = ComposeOption<
    GridComponentOption | TooltipComponentOption | BarSeriesOption
>;

type ChartRuntime = {
    init: any;
    use: any;
    BarChart: any;
    GridComponent: any;
    TooltipComponent: any;
    CanvasRenderer: any;
};

const props = defineProps<{
    items: DashboardAccountSummaryItem[];
    currency: string;
    title: string;
    description: string;
    emptyLabel: string;
}>();

const chartContainer = ref<HTMLDivElement | null>(null);
const chartInstance = shallowRef<ECharts | null>(null);
const chartReady = ref(false);
const visibleItems = computed(() => props.items.slice(0, 6));
const { isPrivacyModeEnabled } = usePrivacyMode();

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
            BarChart: charts.BarChart,
            GridComponent: components.GridComponent,
            TooltipComponent: components.TooltipComponent,
            CanvasRenderer: renderers.CanvasRenderer,
        }));
    }

    const runtime = await chartRuntimePromise;

    if (!chartRuntimeRegistered) {
        runtime.use([
            runtime.BarChart,
            runtime.GridComponent,
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

function buildChartOption(): AccountsChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');

    return {
        animationDuration: 450,
        grid: {
            left: 12,
            right: 12,
            top: 12,
            bottom: 24,
            containLabel: true,
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
                const item = Array.isArray(params) ? params[0] : params;

                return `${item?.axisValueLabel ?? ''}<br>${formatCurrency(Number(item?.value ?? 0))}`;
            },
        },
        xAxis: {
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
        yAxis: {
            type: 'category',
            data: visibleItems.value.map((item) => item.account_name),
            axisLabel: {
                color: mutedText,
            },
            axisTick: {
                show: false,
            },
            axisLine: {
                show: false,
            },
        },
        series: [
            {
                type: 'bar',
                data: visibleItems.value.map(
                    (item) => item.current_balance_raw,
                ),
                barWidth: 18,
                itemStyle: {
                    color: '#2563eb',
                    borderRadius: [0, 12, 12, 0],
                },
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

watch([visibleItems, isPrivacyModeEnabled], () => {
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
            <div v-else class="space-y-4">
                <div
                    class="relative h-80 overflow-hidden rounded-[24px] bg-slate-50/80 dark:bg-slate-900/70"
                >
                    <div ref="chartContainer" class="h-full w-full" />
                    <div
                        v-if="!chartReady"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center px-6"
                    >
                        <Skeleton class="h-44 w-full rounded-[24px]" />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="item in visibleItems"
                        :key="item.account_id"
                        class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ item.account_name }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.bank_name ?? item.currency }}
                                </p>
                            </div>
                            <p
                                class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                            >
                                <SensitiveValue :value="item.current_balance" />
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
