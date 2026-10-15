<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { cn } from '@/lib/utils';
import type { RecurringMonthlyCalendarDay } from '@/types';
export type RecurringCalendarCell = {
    date: string;
    dayNumber: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    summary: RecurringMonthlyCalendarDay | null;
};
defineProps<{
    compactCounters?: boolean;
    calendarWeeks: RecurringCalendarCell[][];
    weekdayLabels: string[];
    selectedDate: string | null;
    formatMoney: (value: number) => string;
    formatDayHeading: (date: string) => string;
    filteredOccurrencesCount: (day: RecurringMonthlyCalendarDay) => number;
}>();
const emit = defineEmits<{ select: [date: string] }>();
const { t } = useI18n();
</script>
<template>
    <div
        class="space-y-4 lg:p-6"
        :class="compactCounters ? 'px-1 py-3 sm:p-4' : 'p-4'"
    >
        <div class="space-y-2 lg:hidden">
            <div
                class="grid grid-cols-7 gap-1 text-center text-[10px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
            >
                <div
                    v-for="label in weekdayLabels"
                    :key="`${label}-mobile`"
                    class="rounded-xl bg-slate-100/80 px-1 py-2 dark:bg-white/6"
                >
                    {{ label }}
                </div>
            </div>

            <div class="grid gap-1.5">
                <div
                    v-for="(week, weekIndex) in calendarWeeks"
                    :key="`mobile-week-${weekIndex}`"
                    class="grid grid-cols-7 gap-1.5"
                >
                    <button
                        v-for="cell in week"
                        :key="`mobile-${cell.date}`"
                        type="button"
                        class="min-h-[76px] border py-2 text-left align-top transition-all"
                        :style="
                            compactCounters
                                ? { alignSelf: 'stretch' }
                                : undefined
                        "
                        :class="
                            cn(
                                compactCounters
                                    ? 'rounded-xl px-1'
                                    : 'rounded-[18px] px-1.5',
                                cell.isCurrentMonth
                                    ? 'border-slate-200/80 bg-white hover:border-sky-300 hover:bg-sky-50/70 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-sky-500/40 dark:hover:bg-sky-500/8'
                                    : 'border-dashed border-slate-200/40 bg-slate-100/35 text-slate-300 dark:border-white/6 dark:bg-slate-950/30 dark:text-slate-700',
                                selectedDate === cell.date
                                    ? 'border-sky-400 ring-2 ring-sky-400/40'
                                    : '',
                                cell.isToday && selectedDate !== cell.date
                                    ? 'shadow-[inset_0_0_0_1px_rgba(14,165,233,0.45)]'
                                    : '',
                                !cell.isCurrentMonth
                                    ? 'cursor-default'
                                    : 'cursor-pointer',
                            )
                        "
                        :disabled="!cell.isCurrentMonth"
                        :aria-pressed="selectedDate === cell.date"
                        :aria-label="formatDayHeading(cell.date)"
                        @click="emit('select', cell.date)"
                    >
                        <div class="flex items-start justify-between gap-1">
                            <span
                                class="text-sm font-semibold"
                                :class="
                                    cell.isCurrentMonth
                                        ? selectedDate === cell.date
                                            ? 'text-sky-700 dark:text-sky-300'
                                            : 'text-slate-950 dark:text-white'
                                        : 'text-slate-300 dark:text-slate-700'
                                "
                            >
                                {{ cell.isCurrentMonth ? cell.dayNumber : '' }}
                            </span>
                            <span
                                v-if="cell.summary?.occurrences_count"
                                class="rounded-full bg-slate-100 px-1 py-0.5 text-[10px] leading-none font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ filteredOccurrencesCount(cell.summary) }}
                            </span>
                        </div>

                        <slot name="day-content" :cell="cell" :mobile="true">
                            <div
                                v-if="cell.summary && cell.isCurrentMonth"
                                class="mt-2 space-y-1"
                            >
                                <p
                                    v-if="cell.summary.income_total > 0"
                                    class="truncate text-[10px] leading-none font-semibold text-emerald-600 dark:text-emerald-300"
                                >
                                    +<SensitiveValue
                                        :value="
                                            formatMoney(
                                                cell.summary.income_total,
                                            )
                                        "
                                    />
                                </p>
                                <p
                                    v-if="cell.summary.expense_total > 0"
                                    class="truncate text-[10px] leading-none font-semibold text-rose-600 dark:text-rose-300"
                                >
                                    -<SensitiveValue
                                        :value="
                                            formatMoney(
                                                cell.summary.expense_total,
                                            )
                                        "
                                    />
                                </p>
                            </div>
                        </slot>
                    </button>
                </div>
            </div>
        </div>

        <div class="hidden overflow-x-auto pb-1 lg:block">
            <div class="min-w-[44rem] space-y-2 sm:min-w-0">
                <div
                    class="grid grid-cols-7 gap-2 text-center text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase sm:text-xs dark:text-slate-400"
                >
                    <div
                        v-for="label in weekdayLabels"
                        :key="label"
                        class="rounded-2xl bg-slate-100/80 px-2 py-3 dark:bg-white/6"
                    >
                        {{ label }}
                    </div>
                </div>

                <div class="grid gap-2">
                    <div
                        v-for="(week, weekIndex) in calendarWeeks"
                        :key="`week-${weekIndex}`"
                        class="grid grid-cols-7 gap-2"
                    >
                        <button
                            v-for="cell in week"
                            :key="cell.date"
                            type="button"
                            class="min-h-[110px] rounded-[22px] border p-2.5 text-left transition-all sm:min-h-[132px] sm:rounded-[24px] sm:p-3"
                            :class="
                                cn(
                                    cell.isCurrentMonth
                                        ? 'border-slate-200/80 bg-slate-50/90 hover:border-sky-300 hover:bg-sky-50/70 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-sky-500/40 dark:hover:bg-sky-500/8'
                                        : 'min-h-[92px] border-dashed border-slate-200/40 bg-slate-100/35 text-slate-300 dark:border-white/6 dark:bg-slate-950/30 dark:text-slate-700',
                                    selectedDate === cell.date
                                        ? 'ring-2 ring-sky-400/70 ring-offset-2 ring-offset-white dark:ring-offset-slate-950'
                                        : '',
                                    cell.isToday
                                        ? 'shadow-[inset_0_0_0_1px_rgba(14,165,233,0.4)]'
                                        : '',
                                    !cell.isCurrentMonth
                                        ? 'cursor-default'
                                        : 'cursor-pointer',
                                )
                            "
                            :disabled="!cell.isCurrentMonth"
                            :aria-pressed="selectedDate === cell.date"
                            :aria-label="formatDayHeading(cell.date)"
                            @click="emit('select', cell.date)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span
                                    class="text-sm font-semibold sm:text-base"
                                    :class="
                                        cell.isCurrentMonth
                                            ? 'text-slate-950 dark:text-white'
                                            : 'text-slate-300 dark:text-slate-700'
                                    "
                                >
                                    {{
                                        cell.isCurrentMonth
                                            ? cell.dayNumber
                                            : ''
                                    }}
                                </span>
                                <span
                                    v-if="cell.summary?.occurrences_count"
                                    class="rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-semibold text-slate-700 shadow-sm dark:bg-slate-950/80 dark:text-slate-200"
                                >
                                    {{ filteredOccurrencesCount(cell.summary) }}
                                </span>
                            </div>

                            <slot
                                name="day-content"
                                :cell="cell"
                                :mobile="false"
                            >
                                <div
                                    v-if="cell.summary && cell.isCurrentMonth"
                                    class="mt-3 space-y-1.5 sm:mt-4 sm:space-y-2"
                                >
                                    <div
                                        v-if="cell.summary.income_total > 0"
                                        class="rounded-2xl bg-emerald-500/10 px-2 py-1.5 text-emerald-700 sm:px-2.5 sm:py-2 dark:bg-emerald-500/12 dark:text-emerald-300"
                                    >
                                        <span
                                            class="block text-[10px] font-semibold tracking-[0.18em] uppercase sm:hidden"
                                        >
                                            +
                                        </span>
                                        <span
                                            class="hidden text-[10px] tracking-[0.18em] uppercase sm:block"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.labels.plannedIncome',
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs leading-tight font-semibold sm:mt-1 sm:text-sm"
                                        >
                                            <SensitiveValue
                                                :value="
                                                    formatMoney(
                                                        cell.summary
                                                            .income_total,
                                                    )
                                                "
                                            />
                                        </span>
                                    </div>
                                    <div
                                        v-if="cell.summary.expense_total > 0"
                                        class="rounded-2xl bg-rose-500/10 px-2 py-1.5 text-rose-700 sm:px-2.5 sm:py-2 dark:bg-rose-500/12 dark:text-rose-300"
                                    >
                                        <span
                                            class="block text-[10px] font-semibold tracking-[0.18em] uppercase sm:hidden"
                                        >
                                            -
                                        </span>
                                        <span
                                            class="hidden text-[10px] tracking-[0.18em] uppercase sm:block"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.labels.plannedExpenses',
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs leading-tight font-semibold sm:mt-1 sm:text-sm"
                                        >
                                            <SensitiveValue
                                                :value="
                                                    formatMoney(
                                                        cell.summary
                                                            .expense_total,
                                                    )
                                                "
                                            />
                                        </span>
                                    </div>
                                </div>
                            </slot>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
