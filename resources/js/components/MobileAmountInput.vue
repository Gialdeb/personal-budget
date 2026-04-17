<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import MoneyInput from '@/components/MoneyInput.vue';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    formatMoneyDisplay,
    formatMoneyEditable,
    formatMoneyValue,
    getMoneySeparators,
    normalizeMoneyValue,
    resolveCurrencySymbol,
    toStandardMoneyString,
} from '@/lib/money.js';
import { cn } from '@/lib/utils';

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
        class?: HTMLAttributes['class'];
        mobileTitle?: string;
        mobileDescription?: string;
        mobileSaveLabel?: string;
        mobileClearLabel?: string;
        editorOpen?: boolean;
        showTrigger?: boolean;
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
        class: undefined,
        mobileTitle: undefined,
        mobileDescription: undefined,
        mobileSaveLabel: undefined,
        mobileClearLabel: undefined,
        editorOpen: undefined,
        showTrigger: true,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:editorOpen': [value: boolean];
    blur: [event: FocusEvent];
    focus: [event: FocusEvent];
}>();

const { t } = useI18n();
const isMobile = useMediaQuery('(max-width: 767px)');
const uncontrolledEditorOpen = ref(false);
const draftExpression = ref('');
const operatorTokens = new Set(['+', '-', '×', '÷']);
const isEditorControlled = computed(() => props.editorOpen !== undefined);

const resolvedEditorOpen = computed({
    get(): boolean {
        return isEditorControlled.value
            ? (props.editorOpen ?? false)
            : uncontrolledEditorOpen.value;
    },
    set(value: boolean) {
        if (!isEditorControlled.value) {
            uncontrolledEditorOpen.value = value;
        }

        emit('update:editorOpen', value);
    },
});

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

const buttonValue = computed(() => {
    if (normalizedValue.value === '') {
        return props.placeholder ?? '';
    }

    return formatMoneyDisplay(
        normalizedValue.value,
        props.formatLocale,
        props.precision,
    );
});

const previewValue = computed(() => {
    if (draftExpression.value.trim() === '') {
        return formatMoneyValue(
            0,
            props.currencyCode ?? 'EUR',
            props.formatLocale,
            props.precision,
        );
    }

    const evaluatedExpression = evaluateDraftExpression(draftExpression.value);

    if (evaluatedExpression === null) {
        return formatMoneyValue(
            0,
            props.currencyCode ?? 'EUR',
            props.formatLocale,
            props.precision,
        );
    }

    return formatMoneyValue(
        evaluatedExpression,
        props.currencyCode ?? 'EUR',
        props.formatLocale,
        props.precision,
    );
});

const keypadRows = computed(() => {
    const decimal = getMoneySeparators(props.formatLocale).decimal;

    return [
        ['1', '2', '3', '÷'],
        ['4', '5', '6', '×'],
        ['7', '8', '9', '-'],
        [decimal, '0', 'backspace', '+'],
    ];
});

const expressionPreview = computed(() => {
    if (draftExpression.value.trim() === '') {
        return null;
    }

    return draftExpression.value;
});

const canSave = computed(() => {
    if (props.disabled || props.readonly) {
        return false;
    }

    if (draftExpression.value.trim() === '') {
        return true;
    }

    const evaluatedExpression = evaluateDraftExpression(draftExpression.value);

    return evaluatedExpression !== null && evaluatedExpression >= 0;
});

watch(
    () => resolvedEditorOpen.value,
    (open) => {
        if (!open) {
            return;
        }

        draftExpression.value = formatMoneyEditable(
            normalizedValue.value,
            props.formatLocale,
            props.precision,
        );
    },
    { immediate: true },
);

function openEditor(): void {
    if (props.disabled || props.readonly || !isMobile.value) {
        return;
    }

    resolvedEditorOpen.value = true;
    emit('focus', new FocusEvent('focus'));
}

function appendValue(fragment: string): void {
    if (props.disabled || props.readonly) {
        return;
    }

    const { decimal } = getMoneySeparators(props.formatLocale);

    if (fragment === decimal) {
        if (props.precision === 0) {
            return;
        }

        const currentOperand = getCurrentOperand(draftExpression.value);

        if (currentOperand.includes(decimal)) {
            return;
        }

        draftExpression.value = `${draftExpression.value}${
            currentOperand === '' ? '0' : ''
        }${decimal}`;

        return;
    }

    draftExpression.value = `${draftExpression.value}${fragment}`;
}

function appendOperator(operator: string): void {
    if (props.disabled || props.readonly || !operatorTokens.has(operator)) {
        return;
    }

    const decimal = getMoneySeparators(props.formatLocale).decimal;
    let nextExpression = draftExpression.value.trim();

    if (nextExpression === '') {
        return;
    }

    if (nextExpression.endsWith(decimal)) {
        nextExpression = nextExpression.slice(0, -1);
    }

    if (nextExpression === '') {
        return;
    }

    if (operatorTokens.has(nextExpression.slice(-1))) {
        draftExpression.value = `${nextExpression.slice(0, -1)}${operator}`;

        return;
    }

    draftExpression.value = `${nextExpression}${operator}`;
}

function removeLastCharacter(): void {
    if (props.disabled || props.readonly) {
        return;
    }

    draftExpression.value = draftExpression.value.slice(0, -1);
}

function clearValue(): void {
    if (props.disabled || props.readonly) {
        return;
    }

    draftExpression.value = '';
}

function saveValue(): void {
    if (props.disabled || props.readonly) {
        resolvedEditorOpen.value = false;

        return;
    }

    if (draftExpression.value.trim() === '') {
        emit('update:modelValue', '');
        resolvedEditorOpen.value = false;
        emit('blur', new FocusEvent('blur'));

        return;
    }

    const evaluatedExpression = evaluateDraftExpression(draftExpression.value);

    if (evaluatedExpression === null || evaluatedExpression < 0) {
        return;
    }

    emit(
        'update:modelValue',
        toStandardMoneyString(evaluatedExpression, props.precision),
    );
    resolvedEditorOpen.value = false;
    emit('blur', new FocusEvent('blur'));
}

function getCurrentOperand(expression: string): string {
    return expression.split(/[+\-×÷]/).at(-1) ?? '';
}

function evaluateDraftExpression(expression: string): number | null {
    const decimal = getMoneySeparators(props.formatLocale).decimal;
    let sanitizedExpression = expression.replace(/\s+/g, '');

    while (
        sanitizedExpression !== '' &&
        (operatorTokens.has(sanitizedExpression.slice(-1)) ||
            sanitizedExpression.endsWith(decimal))
    ) {
        sanitizedExpression = sanitizedExpression.slice(0, -1);
    }

    if (sanitizedExpression === '') {
        return null;
    }

    /** @type {number[]} */
    const values = [];
    /** @type {string[]} */
    const operators = [];
    let currentOperand = '';

    for (const character of sanitizedExpression) {
        if (operatorTokens.has(character)) {
            const normalizedOperand = normalizeMoneyValue(
                currentOperand,
                props.formatLocale,
                props.precision,
            );

            if (normalizedOperand === '') {
                return null;
            }

            values.push(Number(normalizedOperand));
            operators.push(character);
            currentOperand = '';
            continue;
        }

        currentOperand += character;
    }

    const normalizedOperand = normalizeMoneyValue(
        currentOperand,
        props.formatLocale,
        props.precision,
    );

    if (normalizedOperand === '') {
        return null;
    }

    values.push(Number(normalizedOperand));

    if (values.length === 0) {
        return null;
    }

    const reducedValues = [values[0] ?? 0];
    const reducedOperators = [];

    for (let index = 0; index < operators.length; index += 1) {
        const operator = operators[index];
        const nextValue = values[index + 1];

        if (operator === undefined || nextValue === undefined) {
            return null;
        }

        if (operator === '×' || operator === '÷') {
            const previousValue = reducedValues.pop() ?? 0;

            if (operator === '÷' && nextValue === 0) {
                return null;
            }

            reducedValues.push(
                operator === '×'
                    ? previousValue * nextValue
                    : previousValue / nextValue,
            );

            continue;
        }

        reducedValues.push(nextValue);
        reducedOperators.push(operator);
    }

    let result = reducedValues[0] ?? 0;

    for (let index = 0; index < reducedOperators.length; index += 1) {
        const operator = reducedOperators[index];
        const nextValue = reducedValues[index + 1];

        if (operator === undefined || nextValue === undefined) {
            return null;
        }

        if (operator === '+') {
            result += nextValue;
            continue;
        }

        result -= nextValue;
    }

    if (!Number.isFinite(result)) {
        return null;
    }

    return Number(toStandardMoneyString(result, props.precision));
}
</script>

<template>
    <template v-if="!isMobile">
        <MoneyInput
            :id="id"
            :model-value="modelValue"
            :format-locale="formatLocale"
            :currency-code="currencyCode"
            :name="name"
            :label="label"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :error="error"
            :precision="precision"
            :class="props.class"
            @update:model-value="emit('update:modelValue', $event)"
            @blur="emit('blur', $event)"
            @focus="emit('focus', $event)"
        />
    </template>

    <div v-else class="grid gap-2">
        <Label v-if="label" :for="id">{{ label }}</Label>

        <button
            v-if="showTrigger"
            :id="id"
            type="button"
            :disabled="disabled"
            :class="
                cn(
                    'relative flex h-11 w-full touch-manipulation select-none items-center rounded-2xl border bg-white px-3 text-right text-base transition outline-none focus:border-sky-400 focus:shadow-[0_0_0_3px_rgba(14,165,233,0.12)] disabled:cursor-not-allowed disabled:bg-slate-100/80 disabled:text-slate-400 sm:text-sm dark:bg-slate-950/80 dark:text-slate-50 dark:disabled:bg-slate-900 dark:disabled:text-slate-500',
                    hasCurrencyAdornment ? 'pr-14' : '',
                    error
                        ? 'border-rose-300 dark:border-rose-500/40'
                        : 'border-slate-200 dark:border-slate-800',
                    props.class,
                )
            "
            @click="openEditor"
        >
            <span
                :class="
                    cn(
                        'w-full truncate text-right',
                        normalizedValue === ''
                            ? 'text-slate-400 dark:text-slate-500'
                            : 'text-slate-950 dark:text-slate-50',
                    )
                "
            >
                {{ buttonValue }}
            </span>
            <span
                v-if="hasCurrencyAdornment"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-semibold text-slate-500 dark:text-slate-400"
            >
                {{ currencySymbol }}
            </span>
        </button>

        <input
            v-if="name"
            :name="name"
            :value="normalizedValue"
            type="hidden"
        />

        <Sheet
            :open="resolvedEditorOpen"
            @update:open="resolvedEditorOpen = $event"
        >
            <SheetContent
                side="bottom"
                class="z-[180] rounded-t-[2rem] border-none bg-white px-4 pt-5 pb-[calc(env(safe-area-inset-bottom)+1rem)] text-slate-950 dark:bg-[#161616] dark:text-white"
            >
                <SheetHeader class="text-left">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-1">
                            <SheetTitle
                                class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{
                                    mobileTitle ?? label ?? t('app.common.save')
                                }}
                            </SheetTitle>
                            <SheetDescription
                                v-if="mobileDescription"
                                class="text-sm leading-5 text-slate-500 dark:text-white/60"
                            >
                                {{ mobileDescription }}
                            </SheetDescription>
                            <p
                                v-if="expressionPreview"
                                class="pt-2 text-sm font-medium tracking-wide text-slate-500 dark:text-white/55"
                            >
                                {{ expressionPreview }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p
                                class="text-4xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ previewValue }}
                            </p>
                            <button
                                type="button"
                                class="app-touch-interactive mt-2 text-sm font-medium text-slate-500 transition hover:text-slate-900 dark:text-white/55 dark:hover:text-white"
                                @click="clearValue"
                            >
                                {{
                                    mobileClearLabel ??
                                    t('planning.mobileEditor.clear')
                                }}
                            </button>
                        </div>
                    </div>
                </SheetHeader>

                <div class="mt-6 space-y-4">
                    <div
                        class="rounded-[1.75rem] bg-slate-100 p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] dark:bg-[#292929] dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]"
                    >
                        <div
                            v-for="(row, rowIndex) in keypadRows"
                            :key="`keypad-row-${rowIndex}`"
                            class="grid grid-cols-4 gap-2"
                        >
                            <button
                                v-for="key in row"
                                :key="key"
                                type="button"
                                class="app-touch-interactive flex h-14 items-center justify-center rounded-2xl text-[2rem] leading-none font-light text-slate-950 transition active:scale-[0.98] active:bg-slate-200/80 dark:text-white dark:active:bg-white/6"
                                @click="
                                    key === 'backspace'
                                        ? removeLastCharacter()
                                        : operatorTokens.has(key)
                                          ? appendOperator(key)
                                          : appendValue(key)
                                "
                            >
                                <span
                                    v-if="key === 'backspace'"
                                    class="text-[1.7rem]"
                                >
                                    ⌫
                                </span>
                                <span v-else>
                                    {{ key }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="app-touch-interactive flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-sm font-semibold text-slate-700 transition active:scale-[0.99] active:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-white/80 dark:active:bg-white/10"
                            @click="clearValue"
                        >
                            {{
                                mobileClearLabel ??
                                t('planning.mobileEditor.clear')
                            }}
                        </button>
                        <button
                            type="button"
                            class="app-touch-interactive flex h-14 items-center justify-center rounded-2xl bg-slate-950 text-sm font-semibold text-white transition active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 dark:bg-white dark:text-slate-950 dark:disabled:bg-white/30 dark:disabled:text-white/50"
                            :disabled="!canSave"
                            @click="saveValue"
                        >
                            {{ mobileSaveLabel ?? t('app.common.save') }}
                        </button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
