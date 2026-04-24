<script setup lang="ts">
import type { PieSeriesOption } from 'echarts/charts';
import type { TooltipComponentOption } from 'echarts/components';
import type { ComposeOption, ECharts } from 'echarts/core';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { ReportAccountsData } from '@/types';

type DistributionChartOption = ComposeOption<
    TooltipComponentOption | PieSeriesOption
>;

type ChartRuntime = {
    init: any;
    use: any;
    PieChart: any;
    TooltipComponent: any;
    CanvasRenderer: any;
};

const props = defineProps<{
    items: ReportAccountsData['distribution'];
    total: string;
    totalLabel: string;
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

const hasChartData = computed(() =>
    props.items.some((item) => Number(item.value_raw) > 0),
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
            PieChart: charts.PieChart,
            TooltipComponent: components.TooltipComponent,
            CanvasRenderer: renderers.CanvasRenderer,
        }));
    }

    const runtime = await chartRuntimePromise;

    if (!chartRuntimeRegistered) {
        runtime.use([
            runtime.PieChart,
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

function buildChartOption(): DistributionChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');
    const surfaceColor = readCssVariable('--background', 'hsl(0 0% 100%)');

    return {
        animationDuration: 600,
        tooltip: {
            trigger: 'item',
            backgroundColor: popoverColor,
            borderColor,
            padding: [10, 12],
            textStyle: {
                color: foreground,
            },
            formatter: (param: any): string => {
                const item = props.items.find(
                    (entry) => entry.name === param.name,
                );

                return `<strong>${param.name}</strong><br>${item?.value ?? ''}<br>${item?.share_label ?? ''}`;
            },
        },
        series: [
            {
                name: props.totalLabel,
                type: 'pie',
                radius: ['58%', '78%'],
                center: ['50%', '50%'],
                avoidLabelOverlap: true,
                padAngle: 3,
                label: {
                    show: false,
                },
                labelLine: {
                    show: false,
                },
                itemStyle: {
                    borderColor: surfaceColor,
                    borderRadius: 12,
                    borderWidth: 3,
                },
                emphasis: {
                    scale: true,
                    scaleSize: 4,
                },
                data: props.items.map((item) => ({
                    value: Math.max(item.value_raw, 0),
                    name: item.name,
                    itemStyle: {
                        color: item.color,
                    },
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
        console.error(
            'Failed to load report accounts distribution chart.',
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
    () => props.items,
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
    <div
        class="relative mx-auto grid size-56 place-items-center overflow-hidden rounded-[2rem] bg-slate-50 dark:bg-slate-900/60"
        data-test="reports-accounts-distribution-chart"
    >
        <div ref="chartContainer" class="h-full w-full" />

        <div
            class="pointer-events-none absolute inset-0 grid place-items-center text-center"
        >
            <span
                class="rounded-2xl bg-white/92 px-5 py-4 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-950/92 dark:ring-slate-800"
            >
                <span
                    class="block text-[10px] font-semibold tracking-[0.22em] text-slate-400 uppercase"
                >
                    {{ totalLabel }}
                </span>
                <span
                    class="block text-xl font-semibold text-slate-950 dark:text-slate-50"
                >
                    {{ total }}
                </span>
            </span>
        </div>

        <div
            v-if="!chartReady"
            class="absolute inset-0 flex flex-col gap-3 bg-background/90 p-5"
        >
            <Skeleton class="mx-auto size-40 rounded-full" />
        </div>

        <div
            v-else-if="!hasChartData"
            class="absolute inset-0 flex items-center justify-center bg-background/90 px-6 text-center text-sm text-muted-foreground"
        >
            {{ emptyLabel }}
        </div>
    </div>
</template>
