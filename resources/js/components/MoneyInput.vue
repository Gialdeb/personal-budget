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
    resolveCurrencyIndicator,
    resolveCurrencyPosition,
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
    normalizeMoneyValue(
        props.modelValue,
        props.formatLocale,
        props.precision,
        props.currencyCode,
    ),
);

const currencyIndicator = computed(() =>
    props.currencyCode
        ? resolveCurrencyIndicator(props.currencyCode, props.formatLocale, {
              preferCodeWhenAmbiguous: true,
          })
        : null,
);

const currencyAdornmentPosition = computed(() =>
    props.currencyCode
        ? resolveCurrencyPosition(props.currencyCode, props.formatLocale, {
              preferCodeWhenAmbiguous: true,
          })
        : 'suffix',
);

const hasCurrencyAdornment = computed(
    () => currencyIndicator.value !== null && currencyIndicator.value !== '',
);

function syncInputValue(): void {
    const nextNormalizedValue = normalizedValue.value;

    if (isFocused.value) {
        const currentDraft = formatMoneyDraft(
            inputValue.value,
            props.formatLocale,
            props.precision,
            props.currencyCode,
        );

        if (
            inputValue.value !== '' &&
            normalizeMoneyValue(
                currentDraft,
                props.formatLocale,
                props.precision,
                props.currencyCode,
            ) === nextNormalizedValue
        ) {
            inputValue.value = currentDraft;

            return;
        }

        inputValue.value = formatMoneyEditable(
            nextNormalizedValue,
            props.formatLocale,
            props.precision,
            props.currencyCode,
        );

        return;
    }

    inputValue.value = formatMoneyDisplay(
        nextNormalizedValue,
        props.formatLocale,
        props.precision,
        props.currencyCode,
    );
}

watch(
    () =>
        [
            props.modelValue,
            props.formatLocale,
            props.precision,
            props.currencyCode,
        ] as const,
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
        props.currencyCode,
    );

    inputValue.value = nextDraft;
    emit(
        'update:modelValue',
        normalizeMoneyValue(
            nextDraft,
            props.formatLocale,
            props.precision,
            props.currencyCode,
        ),
    );
}

function handleKeydown(event: KeyboardEvent): void {
    if (
        shouldAllowMoneyKey(event.key, {
            formatLocale: props.formatLocale,
            precision: props.precision,
            currencyCode: props.currencyCode,
            currentValue: inputValue.value,
            selectionStart:
                event.currentTarget instanceof HTMLInputElement
                    ? event.currentTarget.selectionStart
                    : null,
            selectionEnd:
                event.currentTarget instanceof HTMLInputElement
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
        props.currencyCode,
    ).replace(/[.,]/g, decimal);

    const selectionStart = target.selectionStart ?? inputValue.value.length;
    const selectionEnd = target.selectionEnd ?? selectionStart;
    const nextDraft = formatMoneyDraft(
        `${inputValue.value.slice(0, selectionStart)}${sanitizedText}${inputValue.value.slice(selectionEnd)}`,
        props.formatLocale,
        props.precision,
        props.currencyCode,
    );

    inputValue.value = nextDraft;
    emit(
        'update:modelValue',
        normalizeMoneyValue(
            nextDraft,
            props.formatLocale,
            props.precision,
            props.currencyCode,
        ),
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
        props.currencyCode,
    );
    emit('focus', event);
}

function handleBlur(event: FocusEvent): void {
    isFocused.value = false;
    inputValue.value = formatMoneyDisplay(
        normalizedValue.value,
        props.formatLocale,
        props.precision,
        props.currencyCode,
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
                    'h-11 w-full rounded-2xl border bg-white px-3 text-right text-sm transition outline-none placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_3px_rgba(14,165,233,0.12)] dark:bg-slate-950/80 dark:placeholder:text-slate-500',
                    hasCurrencyAdornment &&
                    currencyAdornmentPosition === 'prefix'
                        ? 'pl-14'
                        : '',
                    hasCurrencyAdornment &&
                    currencyAdornmentPosition === 'suffix'
                        ? 'pr-14'
                        : '',
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
            />

            <input
                v-if="name"
                :name="name"
                :value="normalizedValue"
                type="hidden"
            />

            <span
                v-if="hasCurrencyAdornment"
                class="pointer-events-none absolute inset-y-0 flex items-center text-xs font-semibold tracking-[0.18em] text-slate-400 uppercase dark:text-slate-500"
                :class="
                    currencyAdornmentPosition === 'prefix'
                        ? 'left-3'
                        : 'right-3'
                "
            >
                {{ currencyIndicator }}
            </span>
        </div>

        <InputError v-if="error" :message="error" />
    </div>
</template>
