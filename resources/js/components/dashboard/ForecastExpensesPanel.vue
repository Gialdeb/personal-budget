<script setup lang="ts">
import { BarChart3, CalendarClock, Flame } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type { DashboardData } from '@/types/dashboard';

type Point = DashboardData['analysis']['timeline'][number];
const props = defineProps<{ points: Point[]; currency: string }>();
const emit = defineEmits<{ select: [point: Point] }>();
const { locale, t } = useI18n();
const maximum = computed(() =>
    Math.max(1, ...props.points.map((point) => point.projected_expense_raw)),
);
const average = computed(
    () =>
        props.points.reduce(
            (sum, point) => sum + point.projected_expense_raw,
            0,
        ) / Math.max(props.points.length, 1),
);
const selectedIndex = computed(() =>
    Math.max(
        0,
        props.points.findIndex((point) => point.is_selected),
    ),
);
const followingPoints = computed(() =>
    props.points.slice(selectedIndex.value + 1),
);
const futureTotal = computed(() =>
    followingPoints.value.reduce(
        (sum, point) => sum + point.projected_expense_raw,
        0,
    ),
);
const heavyCount = computed(
    () =>
        followingPoints.value.filter(
            (point) => point.projected_expense_raw > average.value,
        ).length,
);
function month(point: Point): string {
    return new Intl.DateTimeFormat(locale.value, { month: 'short' })
        .format(new Date(point.year, point.month - 1, 1))
        .replace('.', '');
}
function money(value: number): string {
    return formatCurrency(value, props.currency);
}
function barHeight(point: Point): number {
    return Math.max(4, (point.projected_expense_raw / maximum.value) * 76);
}
</script>

<template>
    <section
        class="flex h-full flex-col rounded-2xl border bg-card p-5 shadow-sm sm:p-6"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">
                    {{ t('dashboard.analysis.expenseForecast') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('dashboard.analysis.expenseForecastDescription') }}
                </p>
            </div>
            <span
                class="grid size-10 place-items-center rounded-xl bg-destructive/10 text-destructive"
                ><BarChart3 class="size-5"
            /></span>
        </div>
        <div
            class="relative mt-6 grid h-52 grid-cols-7 items-end gap-2 border-b border-dashed sm:gap-3"
        >
            <div
                class="pointer-events-none absolute inset-x-0 border-t border-dashed border-muted-foreground/45"
                :style="{
                    bottom: `${Math.min(92, (average / maximum) * 100)}%`,
                }"
            >
                <span
                    class="absolute -top-5 right-0 text-[10px] text-muted-foreground"
                    >{{ t('dashboard.analysis.average') }}</span
                >
            </div>
            <button
                v-for="point in points"
                :key="point.key"
                type="button"
                class="group relative grid h-full min-w-0 grid-rows-[minmax(0,1fr)_1.5rem] gap-2 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @click="emit('select', point)"
            >
                <span class="flex min-h-0 flex-col justify-end">
                    <span
                        class="shrink-0 text-center text-[clamp(7px,1vw,10px)] leading-none font-semibold whitespace-nowrap"
                    >
                        {{ money(point.projected_expense_raw) }}
                    </span>
                    <span
                        class="mx-auto mt-1 block w-3/5 min-w-3 shrink-0 rounded-t-md transition group-hover:opacity-80"
                        :class="
                            point.weight === 'heavy'
                                ? 'bg-destructive'
                                : point.is_selected
                                  ? 'bg-[var(--dashboard-blue,var(--chart-1))]'
                                  : point.state === 'future'
                                    ? 'bg-muted-foreground/40'
                                    : 'bg-muted-foreground/70'
                        "
                        :style="{
                            height: `${barHeight(point)}%`,
                        }"
                    />
                </span>
                <span
                    class="self-end pb-2 text-[10px] font-semibold text-muted-foreground capitalize"
                >
                    {{ month(point) }}
                </span>
            </button>
        </div>
        <dl
            class="mt-5 grid grid-cols-3 divide-x rounded-xl bg-muted/45 py-3 text-center"
        >
            <div class="px-2">
                <BarChart3
                    class="mx-auto size-4 text-[var(--dashboard-blue,var(--chart-1))]"
                />
                <dt class="mt-2 text-[10px] text-muted-foreground uppercase">
                    {{ t('dashboard.analysis.monthlyAverage') }}
                </dt>
                <dd class="mt-1 font-semibold">
                    <SensitiveValue :value="money(average)" />
                </dd>
            </div>
            <div class="px-2">
                <CalendarClock
                    class="mx-auto size-4 text-[var(--dashboard-violet,var(--chart-4))]"
                />
                <dt class="mt-2 text-[10px] text-muted-foreground uppercase">
                    {{ t('dashboard.analysis.nextMonths') }}
                </dt>
                <dd class="mt-1 font-semibold">
                    <SensitiveValue :value="money(futureTotal)" />
                </dd>
            </div>
            <div class="px-2">
                <Flame class="mx-auto size-4 text-destructive" />
                <dt class="mt-2 text-[10px] text-muted-foreground uppercase">
                    {{ t('dashboard.analysis.heavyMonths') }}
                </dt>
                <dd class="mt-1 font-semibold">{{ heavyCount }}</dd>
            </div>
        </dl>
    </section>
</template>
