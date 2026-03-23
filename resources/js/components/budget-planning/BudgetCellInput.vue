<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, Check, LoaderCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MoneyInput from '@/components/MoneyInput.vue';
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

const page = usePage();
const localValue = ref(props.amountRaw === 0 ? '' : String(props.amountRaw));
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const moneyFormatLocale = computed(
    () => String(page.props.auth.user?.format_locale ?? 'it-IT'),
);

watch(
    () => props.amountRaw,
    (value) => {
        localValue.value = value === 0 ? '' : String(value);
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

function handleInput(value: string): void {
    localValue.value = value;
    scheduleSave();
}

function handleBlur(): void {
    flushSave();
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
        event.preventDefault();
        (event.target as HTMLInputElement).blur();
    }

    if (event.key === 'Escape') {
        localValue.value = props.amountRaw === 0 ? '' : String(props.amountRaw);
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
    const parsed = localValue.value === '' ? 0 : Number(localValue.value);

    if (!Number.isFinite(parsed) || parsed < 0) {
        if (forceReset) {
            localValue.value = props.amountRaw === 0 ? '' : String(props.amountRaw);
        }

        return;
    }

    if (parsed === props.amountRaw) {
        if (forceReset) {
            localValue.value = parsed === 0 ? '' : String(parsed);
        }

        return;
    }

    emit('save', parsed);

    if (forceReset) {
        localValue.value = parsed === 0 ? '' : String(parsed);
    }
}
</script>

<template>
    <div class="relative">
        <MoneyInput
            v-model="localValue"
            :format-locale="moneyFormatLocale"
            :disabled="disabled"
            :class="
                cn(
                    'h-9 w-full rounded-xl border px-3 pr-8 text-right text-sm font-medium outline-none transition',
                    dense ? 'h-8 rounded-lg px-2.5 text-[13px]' : '',
                    disabled
                        ? 'cursor-default bg-slate-100/80 text-slate-400 dark:bg-slate-900 dark:text-slate-500'
                        : stateClasses,
                )
            "
            :placeholder="dense ? '0' : '0,00'"
            @update:model-value="handleInput"
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
