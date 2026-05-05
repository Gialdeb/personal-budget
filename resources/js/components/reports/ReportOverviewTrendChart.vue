<script setup lang="ts">
import type { PieSeriesOption } from 'echarts/charts';
import type {
    LegendComponentOption,
    TooltipComponentOption,
} from 'echarts/components';
import type { ComposeOption, ECharts } from 'echarts/core';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
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
import type { ReportOverviewKpis } from '@/types';

type TrendChartOption = ComposeOption<
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
    kpis: ReportOverviewKpis;
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

function disposeChart(): void {
    resizeObserver?.disconnect();
    resizeObserver = null;
    themeObserver?.disconnect();
    themeObserver = null;
    chartInstance.value?.dispose();
    chartInstance.value = null;
}

const chartSegments = computed(() => [
    {
        key: 'income',
        label: t('reports.overview.kpis.income'),
        value: Math.max(props.kpis.income_total_raw, 0),
        formatted: props.kpis.income_total,
        color: '#0f9f6e',
    },
    {
        key: 'expense',
        label: t('reports.overview.kpis.expense'),
        value: Math.max(props.kpis.expense_total_raw, 0),
        formatted: props.kpis.expense_total,
        color: '#f43f5e',
    },
    {
        key: 'net',
        label: t('reports.overview.kpis.net'),
        value: Math.abs(props.kpis.net_total_raw),
        formatted: props.kpis.net_total,
        color: props.kpis.net_total_raw >= 0 ? '#2563eb' : '#0f172a',
    },
]);

const totalMovement = computed(() =>
    chartSegments.value.reduce((sum, segment) => sum + segment.value, 0),
);
const hasChartData = computed(() => totalMovement.value > 0);

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
    if (isPrivacyModeEnabled.value) {
        return 'Importo nascosto';
    }

    return formatAppCurrency(value, props.currency);
}

function buildChartOption(): TrendChartOption {
    const borderColor = readCssVariable('--border', 'hsl(0 0% 92.8%)');
    const mutedText = readCssVariable('--muted-foreground', 'hsl(0 0% 45.1%)');
    const popoverColor = readCssVariable('--popover', 'hsl(0 0% 100%)');
    const foreground = readCssVariable('--foreground', 'hsl(0 0% 3.9%)');
    const surfaceColor = readCssVariable('--background', 'hsl(0 0% 100%)');

    return {
        animationDuration: 500,
        color: chartSegments.value.map((segment) => segment.color),
        legend: {
            top: '5%',
            left: 'center',
            itemWidth: 10,
            itemHeight: 10,
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
            formatter: (param: any): string => {
                const share =
                    totalMovement.value > 0
                        ? `${Math.round((Number(param.value ?? 0) / totalMovement.value) * 100)}%`
                        : '0%';

                return `<strong>${param.name}</strong><br>${formatCurrency(Number(param.value ?? 0))}<br>${t('reports.overview.distribution.legendShare')}: ${share}`;
            },
        },
        series: [
            {
                name: t('reports.overview.distribution.title'),
                type: 'pie',
                radius: ['42%', '70%'],
                center: ['50%', '56%'],
                avoidLabelOverlap: false,
                stillShowZeroSum: true,
                padAngle: 2,
                label: {
                    show: false,
                    position: 'center',
                },
                emphasis: {
                    scale: false,
                    label: {
                        show: true,
                        fontSize: 40,
                        fontWeight: 'bold',
                    },
                },
                labelLine: {
                    show: false,
                },
                itemStyle: {
                    borderRadius: 10,
                    borderColor: surfaceColor,
                    borderWidth: 2,
                },
                data: chartSegments.value.map((segment) => ({
                    value: segment.value,
                    name: segment.label,
                    itemStyle: {
                        color: segment.color,
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
            'Failed to load report overview distribution chart.',
            error,
        );
        chartReady.value = true;

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
        props.kpis.income_total_raw,
        props.kpis.expense_total_raw,
        props.kpis.net_total,
        props.kpis.net_total_raw,
        props.kpis.transactions_count,
        props.currency,
        isPrivacyModeEnabled.value,
        t('reports.overview.kpis.income'),
        t('reports.overview.kpis.expense'),
        t('reports.overview.kpis.net'),
        t('reports.overview.distribution.title'),
        t('reports.overview.distribution.legendShare'),
    ],
    async () => {
        if (!hasChartData.value) {
            disposeChart();
            chartReady.value = true;

            return;
        }

        if (chartInstance.value) {
            renderChart();

            return;
        }

        await nextTick();
        await initializeChart();
    },
);

onMounted(() => {
    void initializeChart();
});

onBeforeUnmount(() => {
    isUnmounted = true;
    disposeChart();
});
</script>

<template>
    <Card
        class="overflow-hidden rounded-[30px] border-white/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,250,255,0.94))] shadow-sm dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(18,24,39,0.98),rgba(11,18,32,0.94))]"
        data-test="reports-overview-trend-chart"
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
                class="relative overflow-hidden rounded-[28px] border border-border/60 bg-white/75 dark:bg-white/3"
            >
                <div
                    ref="chartContainer"
                    class="h-[300px] w-full sm:h-[360px] lg:h-[420px]"
                    data-test="reports-overview-trend-chart-canvas"
                />

                <div
                    class="pointer-events-none absolute top-[56%] left-1/2 w-[44%] max-w-[190px] -translate-x-1/2 -translate-y-1/2 px-2 text-center sm:w-[34%] sm:max-w-[220px]"
                >
                    <p
                        class="text-[10px] font-semibold tracking-[0.22em] text-muted-foreground uppercase sm:text-[11px] sm:tracking-[0.28em]"
                    >
                        {{ t('reports.overview.distribution.centerLabel') }}
                    </p>
                    <p
                        class="mt-2 text-[1.15rem] leading-none font-semibold tracking-tight text-foreground sm:mt-3 sm:text-[1.9rem]"
                    >
                        <SensitiveValue
                            variant="veil"
                            :value="props.kpis.net_total"
                        />
                    </p>
                    <p
                        class="mt-2 text-[10px] leading-4 text-muted-foreground sm:text-xs sm:leading-5"
                    >
                        {{ t('reports.overview.distribution.centerCaption') }}
                    </p>
                </div>

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
