<script setup lang="ts">
import { useNow } from '@vueuse/core';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    PopoverRoot,
    PopoverAnchor,
    PopoverPortal,
    PopoverContent,
} from 'reka-ui';
import { computed, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Label } from '@/components/ui/label';
import { datePickerValue } from '@/lib/date-picker';
import {
    transactionCalendarDate,
    transactionCalendarDays,
    transactionDateFromDay,
    transactionCalendarPeriod,
} from '@/lib/transaction-calendar.js';

const props = defineProps<{
    modelValue: string;
    year: number;
    month?: number;
    fixedMonth?: number;
    hideContext?: boolean;
    hideLabel?: boolean;
    disabled?: boolean;
    id?: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const { locale, t } = useI18n();
const open = ref(false);
const inputElement = ref<HTMLInputElement | null>(null);
const generatedId = useId();
const inputId = computed(() => props.id ?? `transaction-day-${generatedId}`);
const typing = ref(false);
const selected = computed(() => {
    const date = transactionCalendarDate(props.modelValue, props.year);

    return props.fixedMonth !== undefined && date?.month !== props.fixedMonth
        ? null
        : date;
});
const displayedMonth = ref(
    props.fixedMonth ?? selected.value?.month ?? props.month ?? 1,
);
const draftDay = ref(selected.value ? String(selected.value.day) : '');
const maximumDay = computed(() =>
    new Date(Date.UTC(props.year, displayedMonth.value, 0)).getUTCDate(),
);
const now = useNow({ interval: 60_000 });
const today = computed(() => datePickerValue(now.value));
const period = computed(() =>
    transactionCalendarPeriod(props.modelValue, props.year, now.value),
);
const context = computed(() =>
    t(`transactions.form.dateContext.${period.value}Month`),
);
watch(
    () => props.modelValue,
    () => {
        if (selected.value) {
            displayedMonth.value = selected.value.month;

            if (
                !typing.value ||
                Number(draftDay.value) !== selected.value.day
            ) {
                draftDay.value = String(selected.value.day);
            }
        } else if (!typing.value) {
            draftDay.value = '';
        }
    },
);
watch(
    () => [props.year, props.month, props.fixedMonth],
    () => {
        displayedMonth.value =
            props.fixedMonth ?? selected.value?.month ?? props.month ?? 1;

        if (!selected.value) {
            draftDay.value = '';
        }
    },
);
function editDay(event: Event): void {
    if (props.disabled) {
        return;
    }

    draftDay.value = (event.target as HTMLInputElement).value;
    emit(
        'update:modelValue',
        transactionDateFromDay(
            draftDay.value,
            props.year,
            props.fixedMonth ?? displayedMonth.value,
        ),
    );
}
function changeMonth(month: number): void {
    if (
        props.disabled ||
        props.fixedMonth !== undefined ||
        month < 1 ||
        month > 12
    ) {
        return;
    }

    const value = transactionDateFromDay(
        draftDay.value || '1',
        props.year,
        month,
        true,
    );

    if (!value) {
        return;
    }

    displayedMonth.value = month;
    draftDay.value = String(Number(value.slice(8, 10)));
    emit('update:modelValue', value);
}
defineExpose({ focus: () => inputElement.value?.focus() });
const days = computed(() =>
    transactionCalendarDays(props.year, displayedMonth.value),
);
const months = computed(() =>
    Array.from({ length: 12 }, (_, index) =>
        new Intl.DateTimeFormat(locale.value, {
            month: 'long',
            timeZone: 'UTC',
        }).format(new Date(Date.UTC(props.year, index, 1))),
    ),
);
const weekdays = computed(() =>
    Array.from({ length: 7 }, (_, index) =>
        new Intl.DateTimeFormat(locale.value, {
            weekday: 'short',
            timeZone: 'UTC',
        }).format(new Date(Date.UTC(2024, 0, index + 1))),
    ),
);
const label = computed(() =>
    selected.value
        ? new Intl.DateTimeFormat(locale.value, {
              dateStyle: 'full',
              timeZone: 'UTC',
          }).format(new Date(`${props.modelValue}T12:00:00Z`))
        : t('transactions.form.calendar.fixedYear', { year: props.year }),
);
function choose(value: string): void {
    const date = transactionCalendarDate(value, props.year);

    if (
        props.disabled ||
        !date ||
        (props.fixedMonth !== undefined && date.month !== props.fixedMonth)
    ) {
        return;
    }

    displayedMonth.value = Number(value.slice(5, 7));
    draftDay.value = String(Number(value.slice(8, 10)));
    emit('update:modelValue', value);
    open.value = false;
}
</script>

<template>
    <div class="grid min-w-0 gap-2">
        <Label :for="inputId" :class="hideLabel ? 'sr-only' : undefined">{{
            t('transactions.form.labels.day')
        }}</Label>
        <PopoverRoot v-model:open="open">
            <PopoverAnchor as-child>
                <input
                    :id="inputId"
                    ref="inputElement"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    :max="maximumDay"
                    :value="draftDay"
                    :disabled="disabled"
                    required
                    autocomplete="off"
                    :aria-expanded="open"
                    aria-haspopup="dialog"
                    :aria-describedby="
                        hideContext ? undefined : `${inputId}-context`
                    "
                    :aria-invalid="period === 'invalid' && draftDay !== ''"
                    class="h-11 w-full min-w-0 rounded-2xl border border-input bg-background px-3 py-2 text-center text-base text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20 disabled:opacity-50"
                    @focus="typing = true"
                    @blur="typing = false"
                    @input="editDay"
                    @click="open = true"
                />
            </PopoverAnchor>
            <PopoverPortal>
                <PopoverContent
                    align="start"
                    :side-offset="6"
                    class="z-[320] w-[min(22rem,calc(100vw-2rem))] rounded-2xl border bg-popover p-3 text-popover-foreground shadow-xl"
                    :aria-label="t('datePicker.open')"
                    @open-auto-focus.prevent
                    @close-auto-focus.prevent
                    @interact-outside="
                        (event) => {
                            if (
                                event.detail.originalEvent.target ===
                                inputElement
                            )
                                event.preventDefault();
                        }
                    "
                >
                    <div class="mb-2 flex items-center gap-2">
                        <button
                            v-if="fixedMonth === undefined"
                            type="button"
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl hover:bg-muted disabled:opacity-30"
                            :disabled="displayedMonth === 1"
                            :aria-label="
                                t('transactions.form.calendar.previousMonth')
                            "
                            @click="changeMonth(displayedMonth - 1)"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <select
                            v-if="fixedMonth === undefined"
                            :value="displayedMonth"
                            @change="
                                changeMonth(
                                    Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    ),
                                )
                            "
                            :aria-label="t('transactions.form.calendar.month')"
                            class="h-10 min-w-0 flex-1 rounded-xl border bg-background px-2 text-base font-medium"
                        >
                            <option
                                v-for="(name, index) in months"
                                :key="index"
                                :value="index + 1"
                            >
                                {{ name }}
                            </option>
                        </select>
                        <span
                            v-else
                            class="flex-1 px-2 text-base font-medium"
                            >{{ months[displayedMonth - 1] }}</span
                        >
                        <span class="text-sm font-semibold">{{ year }}</span>
                        <button
                            v-if="fixedMonth === undefined"
                            type="button"
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl hover:bg-muted disabled:opacity-30"
                            :disabled="displayedMonth === 12"
                            :aria-label="
                                t('transactions.form.calendar.nextMonth')
                            "
                            @click="changeMonth(displayedMonth + 1)"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-0.5 text-center">
                        <span
                            v-for="(weekday, index) in weekdays"
                            :key="index"
                            class="py-2 text-xs text-muted-foreground"
                            >{{ weekday }}</span
                        >
                        <template v-for="(day, index) in days" :key="index">
                            <button
                                v-if="day"
                                type="button"
                                class="relative flex min-h-11 touch-manipulation items-center justify-center rounded-xl text-base hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                                :class="
                                    day.date === modelValue
                                        ? 'bg-primary font-semibold text-primary-foreground hover:bg-primary/90'
                                        : ''
                                "
                                :aria-pressed="day.date === modelValue"
                                :aria-current="
                                    day.date === today ? 'date' : undefined
                                "
                                :aria-label="`${day.day} ${months[displayedMonth - 1]} ${year}`"
                                @click="choose(day.date)"
                            >
                                {{ day.day }}
                                <span
                                    v-if="day.date === today"
                                    aria-hidden="true"
                                    data-today-dot
                                    class="pointer-events-none absolute bottom-1 left-1/2 size-1 -translate-x-1/2 rounded-full bg-current opacity-70"
                                />
                            </button>
                            <span v-else aria-hidden="true" />
                        </template>
                    </div>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
        <div
            v-if="!hideContext"
            :id="`${inputId}-context`"
            aria-live="polite"
            class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs"
            :class="{
                'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200':
                    period === 'current',
                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200':
                    period === 'past',
                'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-200':
                    period === 'future',
                'border-border bg-muted/60 text-muted-foreground':
                    period === 'invalid',
            }"
        >
            <CalendarDays class="size-4 shrink-0" aria-hidden="true" />
            <div class="min-w-0">
                <p class="font-medium">{{ label }}</p>
                <p>{{ context }}</p>
            </div>
        </div>
    </div>
</template>
