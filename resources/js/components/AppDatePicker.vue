<script setup lang="ts">
import { createReusableTemplate, useMediaQuery } from '@vueuse/core';
import { CalendarDays, Check, X } from 'lucide-vue-next';
import {
    PopoverAnchor,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
} from 'reka-ui';
import type { Component, HTMLAttributes } from 'vue';
import {
    computed,
    defineAsyncComponent,
    h,
    ref,
    useAttrs,
    useId,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    datePickerValue,
    datePickerValueAllowed,
    formatDatePickerValue,
    parseDatePickerInput,
    parseDatePickerValue,
} from '@/lib/date-picker';
import { cn } from '@/lib/utils';
import 'v-calendar/style.css';

defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        modelValue?: string | number | null;
        name?: string;
        mode?: 'date' | 'dateTime';
        min?: string | null;
        max?: string | null;
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { mode: 'date' },
);
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const attrs = useAttrs();
const { locale, t } = useI18n();
const id = useId();
const open = ref(false);
const isMobile = useMediaQuery('(max-width: 767px)');
const [DefineDateField, ReuseDateField] = createReusableTemplate();
const [DefineCalendarContent, ReuseCalendarContent] = createReusableTemplate();
const inputElement = ref<HTMLInputElement | null>(null);
const value = computed(() => String(props.modelValue ?? ''));
const withTime = computed(() => props.mode === 'dateTime');
const inputText = ref('');
const editingText = ref(false);
const locked = computed(() => props.disabled || props.readonly);
const placeholder = computed(() =>
    formatDatePickerValue(
        withTime.value ? '2026-10-16T14:30' : '2026-10-16',
        locale.value,
        withTime.value,
    ),
);
const CalendarPicker = defineAsyncComponent({
    loader: async () => (await import('v-calendar')).DatePicker as Component,
    loadingComponent: {
        render: () =>
            h('div', {
                class: 'h-72 animate-pulse rounded-2xl bg-muted',
                role: 'status',
                'aria-label': t('datePicker.loading'),
            }),
    },
    delay: 100,
});
watch(
    [value, locale, withTime],
    () => {
        if (editingText.value) {
            return;
        }

        inputText.value = formatDatePickerValue(
            value.value,
            locale.value,
            withTime.value,
        );
        inputElement.value?.setCustomValidity('');
    },
    { immediate: true },
);
watch(locked, (isLocked) => {
    if (isLocked) {
        open.value = false;
    }
});

function commit(next: string): void {
    if (locked.value) {
        return;
    }

    if (next !== '' && !datePickerValueAllowed(next, props.min, props.max)) {
        return;
    }

    inputElement.value?.setCustomValidity('');
    emit('update:modelValue', next);
    inputText.value = formatDatePickerValue(next, locale.value, withTime.value);
}
const calendarValue = computed({
    get: () => value.value || null,
    set: (next: string | null) => {
        commit(next ?? '');

        if (!withTime.value) {
            open.value = false;
        }
    },
});
function editText(event: Event): void {
    inputText.value = (event.target as HTMLInputElement).value;
    const next = parseDatePickerInput(
        inputText.value,
        locale.value,
        withTime.value,
    );
    const valid =
        next !== null &&
        (next === '' || datePickerValueAllowed(next, props.min, props.max));
    inputElement.value?.setCustomValidity(valid ? '' : t('datePicker.invalid'));
    emit('update:modelValue', valid ? next! : '');
}
function finishInput(): void {
    editingText.value = false;
    const next = parseDatePickerInput(
        inputText.value,
        locale.value,
        withTime.value,
    );

    if (
        next !== null &&
        (next === '' || datePickerValueAllowed(next, props.min, props.max))
    ) {
        inputText.value = formatDatePickerValue(
            next,
            locale.value,
            withTime.value,
        );
    }
}
function showPicker(): void {
    if (!locked.value) {
        open.value = true;
    }
}
function focus(): void {
    inputElement.value?.focus();
}
const today = computed(() => datePickerValue(new Date(), withTime.value));
defineExpose({ focus, showPicker, element: inputElement });
</script>

<template>
    <DefineDateField>
        <div class="relative w-full min-w-0" data-date-picker>
            <input
                :id="String(attrs.id ?? `date-picker-${id}`)"
                ref="inputElement"
                v-bind="$attrs"
                type="text"
                :value="inputText"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                :placeholder="String(attrs.placeholder ?? placeholder)"
                :aria-expanded="open"
                aria-haspopup="dialog"
                autocomplete="off"
                data-slot="input"
                :class="
                    cn(
                        'h-11 w-full min-w-0 rounded-2xl border border-input bg-background px-3 py-2 pr-12 text-base text-foreground shadow-xs transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm',
                        props.class,
                    )
                "
                @focus="editingText = true"
                @blur="finishInput"
                @input="editText"
                @click="showPicker"
                @keydown.down.prevent="showPicker"
            />
            <input
                v-if="name"
                type="hidden"
                :name="name"
                :value="value"
                :disabled="disabled"
            />

            <button
                type="button"
                :disabled="locked"
                @click="open ? (open = false) : showPicker()"
                :aria-label="t('datePicker.open')"
                class="absolute inset-y-0 right-1 my-auto flex size-9 touch-manipulation items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50"
            >
                <CalendarDays class="size-4" aria-hidden="true" />
            </button>
        </div>
    </DefineDateField>
    <DefineCalendarContent>
        <div class="mb-2 flex items-center justify-between gap-2 px-1">
            <span class="text-xs font-semibold text-muted-foreground">{{
                t(
                    withTime
                        ? 'datePicker.chooseDateTime'
                        : 'datePicker.chooseDate',
                )
            }}</span>
            <button
                type="button"
                @click="open = false"
                v-if="!isMobile"
                :aria-label="t('datePicker.close')"
                class="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>
        <CalendarPicker
            v-if="open"
            v-model.string="calendarValue"
            class="app-date-calendar"
            :mode="mode"
            :locale="locale"
            :is-dark="{ selector: 'html', darkClass: 'dark' }"
            :masks="{
                modelValue: withTime ? 'YYYY-MM-DDTHH:mm' : 'YYYY-MM-DD',
            }"
            :min-date="min ? parseDatePickerValue(min) : undefined"
            :max-date="max ? parseDatePickerValue(max) : undefined"
            title-position="left"
            is24hr
            is-required
            expanded
            borderless
            trim-weeks
        />
        <div class="mt-2 flex items-center gap-2 border-t border-border pt-3">
            <button
                type="button"
                class="min-h-10 rounded-xl px-3 text-sm font-medium hover:bg-muted disabled:opacity-40"
                :disabled="!datePickerValueAllowed(today, min, max)"
                @click="
                    commit(today);
                    open = withTime;
                "
            >
                {{ t('datePicker.today') }}
            </button>
            <button
                v-if="!required && value"
                type="button"
                class="min-h-10 rounded-xl px-3 text-sm text-muted-foreground hover:bg-muted"
                @click="
                    commit('');
                    open = false;
                "
            >
                {{ t('datePicker.clear') }}
            </button>
            <button
                type="button"
                @click="open = false"
                class="ml-auto flex min-h-10 items-center gap-1.5 rounded-xl bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                <Check class="size-4" aria-hidden="true" />{{
                    t('datePicker.done')
                }}
            </button>
        </div>
    </DefineCalendarContent>
    <Sheet v-if="isMobile" v-model:open="open">
        <ReuseDateField />
        <SheetContent
            v-if="open"
            side="bottom"
            class="app-date-sheet inset-x-0 top-auto bottom-0 z-[320] max-h-[calc(100dvh-1rem)] overflow-y-auto rounded-t-3xl px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
            @close-auto-focus.prevent
        >
            <SheetTitle class="sr-only">{{
                t(
                    withTime
                        ? 'datePicker.chooseDateTime'
                        : 'datePicker.chooseDate',
                )
            }}</SheetTitle>
            <SheetDescription class="sr-only">{{
                t('datePicker.instructions')
            }}</SheetDescription>
            <ReuseCalendarContent />
        </SheetContent>
    </Sheet>
    <PopoverRoot v-else v-model:open="open">
        <PopoverAnchor as-child><ReuseDateField /></PopoverAnchor>
        <PopoverPortal>
            <PopoverContent
                v-if="open"
                align="start"
                side="bottom"
                :side-offset="8"
                :collision-padding="12"
                :aria-label="
                    t(
                        withTime
                            ? 'datePicker.chooseDateTime'
                            : 'datePicker.chooseDate',
                    )
                "
                class="app-date-popover z-[320] w-80 max-w-[calc(100vw-1.5rem)] rounded-3xl border border-border bg-popover p-3 text-popover-foreground shadow-[0_16px_48px_-12px_rgba(0,0,0,0.25)] outline-none data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 motion-reduce:animate-none"
                @open-auto-focus.prevent
                @close-auto-focus.prevent
            >
                <ReuseCalendarContent />
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>

<style>
.app-date-calendar.vc-container {
    --vc-font-family: var(--font-sans);
    --vc-bg: var(--popover);
    --vc-border: var(--border);
    --vc-hover-bg: var(--muted);
    --vc-header-title-color: var(--foreground);
    --vc-header-arrow-color: var(--muted-foreground);
    --vc-header-arrow-hover-bg: var(--muted);
    --vc-weekday-color: var(--muted-foreground);
    --vc-nav-title-color: var(--foreground);
    --vc-nav-hover-bg: var(--muted);
    --vc-nav-item-active-bg: var(--primary);
    --vc-nav-item-active-color: var(--primary-foreground);
    --vc-nav-item-current-color: var(--foreground);
    --vc-day-content-hover-bg: var(--muted);
    --vc-day-content-disabled-color: var(--muted-foreground);
    --vc-select-color: var(--foreground);
    --vc-select-bg: var(--muted);
    --vc-select-hover-bg: var(--accent);
    --vc-time-select-group-bg: var(--muted);
    --vc-time-select-group-border: var(--border);
    --vc-time-select-group-icon-color: var(--foreground);
    --vc-focus-ring: 0 0 0 3px color-mix(in srgb, var(--ring) 25%, transparent);
    --vc-accent-200: var(--muted);
    --vc-accent-400: var(--primary);
    --vc-accent-500: var(--primary);
    --vc-accent-600: var(--primary);
    --vc-accent-700: var(--primary);
    --vc-accent-900: var(--foreground);
    color: var(--foreground);
}
.app-date-calendar .vc-attr {
    --vc-highlight-solid-bg: var(--primary);
    --vc-highlight-solid-content-color: var(--primary-foreground);
    --vc-content-color: var(--foreground);
}
.app-date-calendar .vc-day-content {
    width: 36px;
    height: 36px;
}
.app-date-calendar .vc-header {
    margin-bottom: 8px;
}
.app-date-calendar .vc-weekday {
    font-weight: 500;
    font-size: 11px;
}
.app-date-calendar .vc-title {
    font-size: 15px;
    font-weight: 600;
}
.app-date-calendar .vc-pane {
    padding-bottom: 4px;
}
</style>
