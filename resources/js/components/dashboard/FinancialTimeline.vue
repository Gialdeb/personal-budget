<script setup lang="ts">
import type { BarSeriesOption, LineSeriesOption } from 'echarts/charts';
import type {
    GridComponentOption,
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
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { formatCurrency } from '@/lib/currency';
import {
    resolveTimelineClickPoint,
    selectedMonthScrollLeft,
} from '@/lib/dashboard-timeline-interaction';
import type { DashboardData } from '@/types/dashboard';

type Point = DashboardData['analysis']['timeline'][number];
type TimelineOption = ComposeOption<
    | GridComponentOption
    | TooltipComponentOption
    | BarSeriesOption
    | LineSeriesOption
>;

const props = defineProps<{
    points: Point[];
    currency: string;
    mode: 'expense' | 'income' | 'availability';
    includeForecast: boolean;
    includeDebts: boolean;
    includeCredits: boolean;
}>();
const emit = defineEmits<{
    select: [point: Point];
    'update:mode': [mode: 'expense' | 'income' | 'availability'];
}>();
const { locale, t } = useI18n();
const { isPrivacyModeEnabled } = usePrivacyMode();
const chartElement = ref<HTMLDivElement | null>(null);
const chartScrollContainer = ref<HTMLDivElement | null>(null);
const chart = shallowRef<ECharts | null>(null);
const ready = ref(false);
let resizeObserver: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;
let selectedMonthScrollFrame: number | null = null;
let disposed = false;

const selectedPoint = computed(
    () => props.points.find((point) => point.is_selected) ?? props.points[0],
);
const selectedValue = computed(() =>
    selectedPoint.value ? total(selectedPoint.value) : 0,
);

function actual(point: Point): number {
    return props.mode === 'expense'
        ? point.actual_expense_raw
        : point.actual_income_raw;
}
function known(point: Point): number {
    const knownAmount =
        props.mode === 'expense'
            ? point.known_expense_raw
            : point.known_income_raw;

    if (props.mode === 'expense') {
        return round(
            (props.includeForecast ? knownAmount : 0) +
                (props.includeDebts ? point.open_debts_raw : 0),
        );
    }

    return round(
        (props.includeForecast ? knownAmount : 0) +
            (props.includeCredits ? point.open_credits_raw : 0),
    );
}
function total(point: Point): number {
    if (props.mode === 'availability') {
return availability(point);
}

    return props.mode === 'expense'
        ? round(point.actual_expense_raw + known(point))
        : round(point.actual_income_raw + known(point));
}
function availability(point: Point): number {
    return round(
        point.availability_end_raw -
            (props.includeForecast
                ? 0
                : point.known_income_raw - point.known_expense_raw) +
            (props.includeCredits ? point.open_credits_raw : 0) -
            (props.includeDebts ? point.open_debts_raw : 0),
    );
}
function displayedIncome(point: Point): number {
    return round(
        point.actual_income_raw +
            (props.includeForecast ? point.known_income_raw : 0) +
            (props.includeCredits ? point.open_credits_raw : 0),
    );
}
function displayedExpense(point: Point): number {
    return round(
        point.actual_expense_raw +
            (props.includeForecast ? point.known_expense_raw : 0) +
            (props.includeDebts ? point.open_debts_raw : 0),
    );
}
function round(value: number): number {
    return Math.round(value * 100) / 100;
}
function month(point: Point): string {
    return new Intl.DateTimeFormat(locale.value, { month: 'short' })
        .format(new Date(point.year, point.month - 1, 1))
        .replace('.', '')
        .toUpperCase();
}
function money(value: number): string {
    return isPrivacyModeEnabled.value
        ? '••••'
        : formatCurrency(value, props.currency);
}
function css(name: string, fallback: string): string {
    return (
        getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim() || fallback
    );
}

function onTimelineChartClick(params: {
    componentType?: string;
    dataIndex?: number;
}): void {
    const point = resolveTimelineClickPoint(params, props.points);

    if (point) {
        emit('select', point);
    }
}

function scrollSelectedMonthIntoView(): void {
    if (selectedMonthScrollFrame !== null) {
        cancelAnimationFrame(selectedMonthScrollFrame);
    }

    nextTick(() => {
        selectedMonthScrollFrame = requestAnimationFrame(() => {
            const container = chartScrollContainer.value;
            const chartInstance = chart.value;
            const point = selectedPoint.value;

            if (
                !container ||
                !chartInstance ||
                !point ||
                container.scrollWidth <= container.clientWidth
            ) {
                return;
            }

            const index = props.points.findIndex(
                (candidate) => candidate.key === point.key,
            );
            const monthCenter = Number(
                chartInstance.convertToPixel({ xAxisIndex: 0 }, index),
            );

            if (!Number.isFinite(monthCenter)) {
                return;
            }

            container.scrollTo({
                left: selectedMonthScrollLeft(
                    monthCenter,
                    container.clientWidth,
                    container.scrollWidth,
                ),
                top: container.scrollTop,
                behavior: 'auto',
            });
        });
    });
}

function option(): TimelineOption {
    const primary = css('--dashboard-blue', css('--chart-1', '#2563eb'));
    const forecast = css('--muted-foreground', '#737373');
    const destructive = css(
        '--dashboard-rose',
        css('--destructive', '#ef4444'),
    );
    const foreground = css('--foreground', '#171717');
    const muted = css('--muted-foreground', '#737373');
    const popover = css('--popover', '#ffffff');
    const border = css('--border', '#e5e5e5');
    const monthMarkers = props.points.flatMap((point, index) =>
        point.is_selected || point.is_current
            ? [
                  {
                      name: point.is_current
                          ? t('dashboard.analysis.current')
                          : point.key,
                      coord: [index, total(point)] as [number, number],
                      symbol: point.is_current ? 'diamond' : 'circle',
                      itemStyle: point.is_current
                          ? {
                                color: popover,
                                borderColor: primary,
                                borderWidth: 3,
                            }
                          : {
                                color: primary,
                                borderColor: popover,
                                borderWidth: 2,
                            },
                  },
              ]
            : [],
    );

    const availabilitySeries: LineSeriesOption = {
        name: t('dashboard.analysis.mode.availability'),
        type: 'line',
        smooth: 0.28,
        symbol: 'circle',
        symbolSize: (value: unknown, params: any) =>
            props.points[params.dataIndex]?.is_selected ? 13 : 8,
        lineStyle: { color: primary, width: 3 },
        itemStyle: { color: primary, borderColor: popover, borderWidth: 2 },
        areaStyle: { color: primary, opacity: 0.1 },
        markPoint: {
            silent: true,
            symbol: 'circle',
            symbolSize: 12,
            label: { show: false },
            itemStyle: { color: popover, borderColor: primary, borderWidth: 3 },
            data: monthMarkers,
        },
        label: {
            show: true,
            position: 'top',
            distance: 10,
            color: foreground,
            fontWeight: 600,
            fontSize: 10,
            formatter: (params: any) =>
                money(Number(params.value)).replace(/,00(?=\s|$)/, ''),
        },
        data: props.points.map(availability),
    };
    const cashflowSeries: BarSeriesOption[] = [
        {
            name: t('dashboard.analysis.detail.real'),
            type: 'bar',
            stack: 'total',
            barWidth: '48%',
            itemStyle: {
                color: (params: any) =>
                    props.points[params.dataIndex]?.weight === 'heavy' &&
                    props.mode === 'expense'
                        ? destructive
                        : primary,
                borderRadius: [6, 6, 2, 2],
                opacity: 0.9,
            },
            data: props.points.map(actual),
        },
        {
            name: t('dashboard.analysis.detail.forecast'),
            type: 'bar',
            stack: 'total',
            barWidth: '48%',
            itemStyle: {
                color: forecast,
                borderRadius: [6, 6, 2, 2],
                opacity: 0.28,
                borderColor: forecast,
                borderType: 'dashed',
                borderWidth: 1,
            },
            label: {
                show: true,
                position: 'top',
                distance: 8,
                color: foreground,
                fontWeight: 600,
                fontSize: 10,
                formatter: (params: any) =>
                    money(total(props.points[params.dataIndex])).replace(
                        /,00(?=\s|$)/,
                        '',
                    ),
            },
            markPoint: {
                silent: true,
                symbol: 'circle',
                symbolSize: 11,
                label: { show: false },
                itemStyle: {
                    color: popover,
                    borderColor: primary,
                    borderWidth: 3,
                },
                data: monthMarkers,
            },
            data: props.points.map(known),
        },
    ];

    return {
        animationDuration: 500,
        animationEasing: 'cubicOut',
        grid: { left: 8, right: 8, top: 38, bottom: 44 },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow',
                shadowStyle: {
                    color: css('--muted', '#f5f5f5'),
                    opacity: 0.55,
                },
            },
            backgroundColor: popover,
            borderColor: border,
            borderWidth: 1,
            padding: 12,
            textStyle: { color: foreground, fontSize: 12 },
            formatter: (params: any): string => {
                const items = Array.isArray(params) ? params : [params];
                const point = props.points[Number(items[0]?.dataIndex ?? 0)];

                if (!point) {
return '';
}

                if (props.mode === 'availability') {
                    return `<div style="min-width:190px"><strong>${month(point)} ${point.year}</strong><div style="margin-top:8px;color:${muted}">${t('dashboard.analysis.availabilityStart')}: <b style="color:${foreground}">${money(point.availability_start_raw)}</b></div><div style="margin-top:4px;color:${muted}">${t('dashboard.analysis.monthIncome')}: <b style="color:${foreground}">${money(displayedIncome(point))}</b></div><div style="margin-top:4px;color:${muted}">${t('dashboard.analysis.actualExpenses')}: <b style="color:${foreground}">${money(displayedExpense(point))}</b></div><div style="margin-top:7px">${t('dashboard.analysis.availabilityEnd')}: <b>${money(availability(point))}</b></div></div>`;
                }

                return `<div style="min-width:170px"><strong>${month(point)} ${point.year}</strong><div style="margin-top:8px;color:${muted}">${t('dashboard.analysis.detail.real')}: <b style="color:${foreground}">${money(actual(point))}</b></div><div style="margin-top:4px;color:${muted}">${t('dashboard.analysis.detail.forecast')}: <b style="color:${foreground}">${money(known(point))}</b></div><div style="margin-top:7px">${t('dashboard.analysis.total')}: <b>${money(total(point))}</b></div></div>`;
            },
        },
        xAxis: {
            type: 'category',
            data: props.points.map(month),
            axisTick: { show: false },
            axisLine: { show: false },
            axisLabel: {
                color: muted,
                fontWeight: 600,
                fontSize: 11,
                margin: 16,
            },
        },
        yAxis: {
            type: 'value',
            show: false,
            max: (value: { max: number }) => Math.max(value.max * 1.22, 1),
        },
        series:
            props.mode === 'availability'
                ? [availabilitySeries]
                : cashflowSeries,
    };
}

async function mountChart(): Promise<void> {
    if (!chartElement.value) {
return;
}

    const [core, charts, components, renderers] = await Promise.all([
        import('echarts/core'),
        import('echarts/charts'),
        import('echarts/components'),
        import('echarts/renderers'),
    ]);

    if (disposed || !chartElement.value) {
return;
}

    core.use([
        charts.BarChart,
        charts.LineChart,
        components.GridComponent,
        components.TooltipComponent,
        components.MarkPointComponent,
        renderers.CanvasRenderer,
    ]);
    chart.value = core.init(chartElement.value);
    chart.value.setOption(option(), true);
    chart.value.on('click', onTimelineChartClick);
    ready.value = true;
    scrollSelectedMonthIntoView();
    resizeObserver = new ResizeObserver(() => {
        chart.value?.resize();
        scrollSelectedMonthIntoView();
    });
    resizeObserver.observe(chartElement.value);
    themeObserver = new MutationObserver(() =>
        chart.value?.setOption(option(), true),
    );
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

watch(
    () => [
        props.points,
        props.mode,
        props.includeForecast,
        props.includeDebts,
        props.includeCredits,
        locale.value,
        isPrivacyModeEnabled.value,
    ],
    () => chart.value?.setOption(option(), true),
    { deep: true },
);
watch(
    () => selectedPoint.value?.key,
    () => scrollSelectedMonthIntoView(),
    { flush: 'post' },
);
onMounted(mountChart);
onBeforeUnmount(() => {
    disposed = true;

    if (selectedMonthScrollFrame !== null) {
        cancelAnimationFrame(selectedMonthScrollFrame);
    }

    resizeObserver?.disconnect();
    themeObserver?.disconnect();
    chart.value?.dispose();
});
</script>

<template>
    <section
        class="flex flex-col"
        :aria-label="t('dashboard.analysis.timeline')"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                >
                    {{ t('dashboard.analysis.timeline') }}
                </p>
                <div class="mt-1 flex items-baseline gap-2">
                    <SensitiveValue
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                        :value="money(selectedValue)"
                    />
                    <span class="text-sm text-muted-foreground"
                        >{{ month(selectedPoint) }}
                        {{ selectedPoint.year }}</span
                    >
                </div>
            </div>
            <div class="inline-flex rounded-full bg-muted p-1" role="group">
                <button
                    v-for="optionMode in [
                        'expense',
                        'income',
                        'availability',
                    ] as const"
                    :key="optionMode"
                    type="button"
                    class="min-h-9 rounded-full px-4 text-xs font-semibold transition"
                    :class="
                        mode === optionMode
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="emit('update:mode', optionMode)"
                >
                    {{ t(`dashboard.analysis.mode.${optionMode}`) }}
                </button>
            </div>
        </div>
        <div
            ref="chartScrollContainer"
            class="relative mt-4 min-h-64 overflow-x-auto"
        >
            <div
                v-if="!ready"
                class="absolute inset-0 animate-pulse rounded-xl bg-muted/50"
            />
            <div
                ref="chartElement"
                class="h-72 w-full min-w-[46rem] sm:h-80 sm:min-w-0"
            />
        </div>
        <div
            class="mt-1 flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted-foreground"
        >
            <span
                v-if="mode !== 'availability'"
                class="inline-flex items-center gap-2"
                ><i
                    class="size-2.5 rounded-sm bg-[var(--dashboard-blue,var(--chart-1))]"
                />{{ t('dashboard.analysis.detail.real') }}</span
            >
            <span
                v-if="mode !== 'availability'"
                class="inline-flex items-center gap-2"
                ><i class="size-2.5 rounded-sm bg-muted-foreground/30" />{{
                    t('dashboard.analysis.knownFuture')
                }}</span
            >
            <span>{{ t('dashboard.analysis.tapMonth') }}</span>
        </div>
        <div class="mt-4">
            <slot />
        </div>
    </section>
</template>
