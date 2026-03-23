<script setup lang="ts">
import { computed, nextTick, ref, useAttrs, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import {
    formatMoneyDisplay,
    formatMoneyDraft,
    formatMoneyEditable,
    getMoneySeparators,
    normalizeMoneyValue,
    resolveCurrencySymbol,
    shouldAllowMoneyKey,
} from '@/lib/money.js';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        formatLocale: string;
        currencyCode?: string | null;
        name?: string;
        id?: string;
        label?: string;
        placeholder?: string;
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        error?: string | null;
        precision?: number;
    }>(),
    {
        currencyCode: null,
        name: undefined,
        id: undefined,
        label: undefined,
        placeholder: undefined,
        disabled: false,
        readonly: false,
        required: false,
        error: null,
        precision: 2,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [event: FocusEvent];
    focus: [event: FocusEvent];
}>();

const attrs = useAttrs();
const isFocused = ref(false);
const inputValue = ref('');

const normalizedValue = computed(() =>
    normalizeMoneyValue(props.modelValue, props.formatLocale, props.precision),
);

const currencySymbol = computed(() =>
    props.currencyCode
        ? resolveCurrencySymbol(props.currencyCode, props.formatLocale)
        : null,
);

const hasCurrencyAdornment = computed(
    () => currencySymbol.value !== null && currencySymbol.value !== '',
);

function syncInputValue(): void {
    const nextNormalizedValue = normalizedValue.value;

    if (isFocused.value) {
        const currentDraft = formatMoneyDraft(
            inputValue.value,
            props.formatLocale,
            props.precision,
        );

        if (
            inputValue.value !== '' &&
            normalizeMoneyValue(
                currentDraft,
                props.formatLocale,
                props.precision,
            ) === nextNormalizedValue
        ) {
            inputValue.value = currentDraft;

            return;
        }

        inputValue.value = formatMoneyEditable(
            nextNormalizedValue,
            props.formatLocale,
            props.precision,
        );

        return;
    }

    inputValue.value = formatMoneyDisplay(
        nextNormalizedValue,
        props.formatLocale,
        props.precision,
    );
}

watch(
    () => [props.modelValue, props.formatLocale, props.precision] as const,
    () => {
        syncInputValue();
    },
    { immediate: true },
);

function handleInput(event: Event): void {
    const nextDraft = formatMoneyDraft(
        (event.target as HTMLInputElement).value,
        props.formatLocale,
        props.precision,
    );

    inputValue.value = nextDraft;
    emit(
        'update:modelValue',
        normalizeMoneyValue(nextDraft, props.formatLocale, props.precision),
    );
}

function handleKeydown(event: KeyboardEvent): void {
    if (
        shouldAllowMoneyKey(event.key, {
            formatLocale: props.formatLocale,
            precision: props.precision,
            currentValue: inputValue.value,
            selectionStart: event.currentTarget instanceof HTMLInputElement
                ? event.currentTarget.selectionStart
                : null,
            selectionEnd: event.currentTarget instanceof HTMLInputElement
                ? event.currentTarget.selectionEnd
                : null,
            ctrlKey: event.ctrlKey,
            metaKey: event.metaKey,
        })
    ) {
        return;
    }

    event.preventDefault();
}

function handlePaste(event: ClipboardEvent): void {
    const target = event.currentTarget;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    event.preventDefault();

    const pastedText = event.clipboardData?.getData('text') ?? '';
    const { decimal } = getMoneySeparators(props.formatLocale);
    const sanitizedText = formatMoneyDraft(
        pastedText,
        props.formatLocale,
        props.precision,
    ).replace(/[.,]/g, decimal);

    const selectionStart = target.selectionStart ?? inputValue.value.length;
    const selectionEnd = target.selectionEnd ?? selectionStart;
    const nextDraft = formatMoneyDraft(
        `${inputValue.value.slice(0, selectionStart)}${sanitizedText}${inputValue.value.slice(selectionEnd)}`,
        props.formatLocale,
        props.precision,
    );

    inputValue.value = nextDraft;
    emit(
        'update:modelValue',
        normalizeMoneyValue(nextDraft, props.formatLocale, props.precision),
    );

    const nextCaretPosition = Math.min(
        selectionStart + sanitizedText.length,
        nextDraft.length,
    );

    nextTick(() => {
        target.setSelectionRange(nextCaretPosition, nextCaretPosition);
    });
}

function handleFocus(event: FocusEvent): void {
    isFocused.value = true;
    inputValue.value = formatMoneyEditable(
        normalizedValue.value,
        props.formatLocale,
        props.precision,
    );
    emit('focus', event);
}

function handleBlur(event: FocusEvent): void {
    isFocused.value = false;
    inputValue.value = formatMoneyDisplay(
        normalizedValue.value,
        props.formatLocale,
        props.precision,
    );
    emit('blur', event);
}
</script>

<template>
    <div class="grid gap-2">
        <Label v-if="label" :for="id">{{ label }}</Label>

        <div class="relative">
            <input
                :id="id"
                v-bind="attrs"
                :value="inputValue"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                :placeholder="placeholder"
                :class="[
                    'h-11 w-full rounded-2xl border bg-white px-3 text-right text-sm outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_3px_rgba(14,165,233,0.12)] dark:bg-slate-950/80 dark:placeholder:text-slate-500',
                    hasCurrencyAdornment ? 'pr-14' : '',
                    error
                        ? 'border-rose-300 dark:border-rose-500/40'
                        : 'border-slate-200 dark:border-slate-800',
                    disabled
                        ? 'cursor-not-allowed bg-slate-100/80 text-slate-400 dark:bg-slate-900 dark:text-slate-500'
                        : 'text-slate-950 dark:text-slate-50',
                ]"
                @input="handleInput"
                @keydown="handleKeydown"
                @paste="handlePaste"
                @focus="handleFocus"
                @blur="handleBlur"
            >

            <input
                v-if="name"
                :name="name"
                :value="normalizedValue"
                type="hidden"
            >

            <span
                v-if="hasCurrencyAdornment"
                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500"
            >
                {{ currencySymbol }}
            </span>
        </div>

        <InputError v-if="error" :message="error" />
    </div>
</template>
