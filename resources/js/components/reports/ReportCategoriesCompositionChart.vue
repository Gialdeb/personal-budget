<script setup lang="ts">
import type { SunburstSeriesOption, TreemapSeriesOption } from 'echarts/charts';
import type { TooltipComponentOption } from 'echarts/components';
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
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { formatCurrency as formatAppCurrency } from '@/lib/currency';
import type { ReportCategoryNode } from '@/types';

type CompositionOption = ComposeOption<
    TooltipComponentOption | SunburstSeriesOption | TreemapSeriesOption
>;

type ChartRuntime = {
    init: any;
    use: any;
    SunburstChart: any;
    TreemapChart: any;
    TooltipComponent: any;
    CanvasRenderer: any;
};

const props = defineProps<{
    nodes: ReportCategoryNode[];
    currency: string;
    emptyLabel: string;
    variant: 'sunburst' | 'treemap';
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
            SunburstChart: charts.SunburstChart,
            TreemapChart: charts.TreemapChart,
            TooltipComponent: components.TooltipComponent,
            CanvasRenderer: renderers.CanvasRenderer,
        }));
    }

    const runtime = await chartRuntimePromise;

    if (!chartRuntimeRegistered) {
        runtime.use([
            runtime.SunburstChart,
            runtime.TreemapChart,
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

function buildChartOption(): CompositionOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');
    const surfaceColor = readCssVariable('--background', 'hsl(0 0% 100%)');

    if (props.variant === 'treemap') {
        return {
            animationDuration: 500,
            tooltip: {
                formatter: (params: any): string =>
                    `<strong>${params.name}</strong><br>${formatCurrency(Number(params.value ?? 0))}`,
                backgroundColor: popoverColor,
                borderColor,
                textStyle: {
                    color: foreground,
                },
            },
            series: [
                {
                    type: 'treemap',
                    roam: false,
                    breadcrumb: {
                        show: false,
                    },
                    visibleMin: 1,
                    nodeClick: false,
                    label: {
                        show: true,
                        formatter: '{b}',
                        color: '#fff',
                    },
                    upperLabel: {
                        show: true,
                        height: 20,
                        color: mutedText,
                    },
                    itemStyle: {
                        borderColor: surfaceColor,
                        borderWidth: 3,
                        gapWidth: 3,
                        borderRadius: 12,
                    },
                    levels: [
                        {
                            itemStyle: {
                                borderColor: surfaceColor,
                                borderWidth: 4,
                                gapWidth: 4,
                            },
                        },
                        {
                            itemStyle: {
                                borderColor: surfaceColor,
                                borderWidth: 3,
                                gapWidth: 3,
                            },
                        },
                    ],
                    data: props.nodes,
                },
            ],
        };
    }

    return {
        animationDuration: 500,
        tooltip: {
            formatter: (params: any): string =>
                `<strong>${params.name}</strong><br>${formatCurrency(Number(params.value ?? 0))}`,
            backgroundColor: popoverColor,
            borderColor,
            textStyle: {
                color: foreground,
            },
        },
        series: [
            {
                type: 'sunburst',
                radius: ['18%', '92%'],
                sort: null,
                emphasis: {
                    focus: 'ancestor',
                },
                itemStyle: {
                    borderColor: surfaceColor,
                    borderWidth: 2,
                },
                label: {
                    rotate: 'radial',
                    color: foreground,
                    fontSize: 11,
                },
                levels: [
                    {},
                    {
                        r0: '18%',
                        r: '46%',
                        label: {
                            rotate: 0,
                            fontWeight: 700,
                            color: '#fff',
                        },
                    },
                    {
                        r0: '46%',
                        r: '72%',
                        label: {
                            rotate: 'radial',
                            color: '#fff',
                        },
                    },
                    {
                        r0: '72%',
                        r: '92%',
                        label: {
                            color: '#fff',
                            fontSize: 10,
                        },
                    },
                ],
                data: props.nodes,
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
    if (!chartContainer.value || props.nodes.length === 0) {
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
            'Failed to load report category composition chart.',
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
        attributeFilter: ['class', 'style'],
    });
}

watch(
    () => [props.nodes, props.variant, isPrivacyModeEnabled.value],
    async () => {
        await nextTick();

        if (props.nodes.length === 0) {
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
        v-if="nodes.length === 0"
        class="flex h-full min-h-[260px] items-center justify-center rounded-[24px] border border-dashed border-slate-300/80 bg-slate-50/80 px-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
    >
        {{ emptyLabel }}
    </div>
    <div
        v-else
        class="relative h-[280px] overflow-hidden rounded-[24px] bg-slate-50/80 sm:h-[320px] md:h-[420px] dark:bg-slate-900/70"
    >
        <div ref="chartContainer" class="h-full w-full" />
        <div
            v-if="!chartReady"
            class="pointer-events-none absolute inset-0 flex items-center justify-center px-6"
        >
            <Skeleton class="h-44 w-44 rounded-full md:h-64 md:w-64" />
        </div>
    </div>
</template>
