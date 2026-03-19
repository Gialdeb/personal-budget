<script setup lang="ts">
import { AlertCircle, Check, LoaderCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type { BudgetCellSaveState } from '@/types';

const props = withDefaults(
    defineProps<{
        amountRaw: number;
        state?: BudgetCellSaveState;
        disabled?: boolean;
        dense?: boolean;
        currency?: string;
    }>(),
    {
        state: 'idle',
        disabled: false,
        dense: false,
        currency: 'EUR',
    },
);

const emit = defineEmits<{
    save: [amount: number];
}>();

const isFocused = ref(false);
const localValue = ref(formatEditableValue(props.amountRaw));
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const displayValue = computed(() =>
    isFocused.value
        ? localValue.value
        : formatDisplayValue(props.amountRaw),
);

watch(
    () => props.amountRaw,
    (value) => {
        if (!isFocused.value) {
            localValue.value = formatEditableValue(value);
        }
    },
);

onBeforeUnmount(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});

const stateClasses = computed(() => {
    if (props.state === 'saving') {
        return 'border-sky-300/80 bg-sky-50/80 text-slate-950 shadow-[0_0_0_1px_rgba(14,165,233,0.18)] dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-white';
    }

    if (props.state === 'saved') {
        return 'border-emerald-300/80 bg-emerald-50/80 text-slate-950 shadow-[0_0_0_1px_rgba(16,185,129,0.18)] dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-white';
    }

    if (props.state === 'error') {
        return 'border-rose-300/80 bg-rose-50/80 text-slate-950 shadow-[0_0_0_1px_rgba(244,63,94,0.18)] dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-white';
    }

    return 'border-transparent bg-white/80 text-slate-700 hover:border-slate-200 hover:bg-white dark:bg-slate-950/40 dark:text-slate-200 dark:hover:border-white/10 dark:hover:bg-slate-950/70';
});

function handleFocus(event: Event): void {
    isFocused.value = true;
    localValue.value = formatEditableValue(props.amountRaw);

    const target = event.target as HTMLInputElement;

    target.select();
}

function handleInput(value: string): void {
    localValue.value = value;
    scheduleSave();
}

function handleBlur(): void {
    flushSave();
    isFocused.value = false;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
        event.preventDefault();
        (event.target as HTMLInputElement).blur();
    }

    if (event.key === 'Escape') {
        localValue.value = formatEditableValue(props.amountRaw);
        (event.target as HTMLInputElement).blur();
    }
}

function scheduleSave(): void {
    if (props.disabled) {
        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        emitIfChanged();
    }, 650);
}

function flushSave(): void {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    emitIfChanged(true);
}

function emitIfChanged(forceReset = false): void {
    const parsed = parseEditableValue(localValue.value);

    if (parsed === null || parsed < 0) {
        if (forceReset) {
            localValue.value = formatEditableValue(props.amountRaw);
        }

        return;
    }

    if (parsed === props.amountRaw) {
        if (forceReset) {
            localValue.value = formatEditableValue(parsed);
        }

        return;
    }

    emit('save', parsed);

    if (forceReset) {
        localValue.value = formatEditableValue(parsed);
    }
}

function formatEditableValue(value: number): string {
    if (value === 0) {
        return '';
    }

    return new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: value % 1 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDisplayValue(value: number): string {
    if (value === 0) {
        return '';
    }

    return formatCurrency(value, props.currency);
}

function parseEditableValue(value: string): number | null {
    const normalized = value
        .replace(/[^\d,.-]/g, '')
        .replace(/\./g, '')
        .replace(',', '.');

    if (normalized === '') {
        return 0;
    }

    const numericValue = Number(normalized);

    if (!Number.isFinite(numericValue)) {
        return null;
    }

    return Math.round(numericValue * 100) / 100;
}
</script>

<template>
    <div class="relative">
        <input
            :value="displayValue"
            type="text"
            inputmode="decimal"
            :disabled="disabled"
            :class="
                cn(
                    'h-9 w-full rounded-xl border px-3 pr-8 text-right text-sm font-medium outline-none transition',
                    'focus:border-sky-400 focus:bg-white focus:shadow-[0_0_0_3px_rgba(14,165,233,0.12)] dark:focus:border-sky-400 dark:focus:bg-slate-950',
                    dense ? 'h-8 rounded-lg px-2.5 text-[13px]' : '',
                    disabled
                        ? 'cursor-default bg-slate-100/80 text-slate-400 dark:bg-slate-900 dark:text-slate-500'
                        : stateClasses,
                )
            "
            placeholder="0"
            @focus="handleFocus"
            @input="handleInput(($event.target as HTMLInputElement).value)"
            @blur="handleBlur"
            @keydown="handleKeydown"
        />

        <span
            class="pointer-events-none absolute inset-y-0 right-2 flex items-center justify-center"
        >
            <LoaderCircle
                v-if="state === 'saving'"
                class="size-3.5 animate-spin text-sky-500"
            />
            <Check
                v-else-if="state === 'saved'"
                class="size-3.5 text-emerald-500"
            />
            <AlertCircle
                v-else-if="state === 'error'"
                class="size-3.5 text-rose-500"
            />
        </span>
    </div>
</template>
