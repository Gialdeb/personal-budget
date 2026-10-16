<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { computed, ref, useAttrs, watch } from 'vue';
import { useUiCopy } from '@/i18n/ui-copy';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    autocomplete?: string;
    type?: string;
    class?: HTMLAttributes['class'];
}>();

const attrs = useAttrs();
const ui = useUiCopy();
const isSearch = computed(() => props.type === 'search');

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const internalValue = ref<string | number>(
    props.modelValue ?? props.defaultValue ?? '',
);

watch(
    () => props.modelValue,
    (value) => {
        if (value !== undefined) {
            internalValue.value = value;
        }
    },
);

watch(
    () => props.defaultValue,
    (value) => {
        if (props.modelValue === undefined && value !== undefined) {
            internalValue.value = value;
        }
    },
);

const modelValue = computed<string | number>({
    get() {
        return props.modelValue ?? internalValue.value;
    },
    set(value) {
        internalValue.value = value;
        emits('update:modelValue', value);
    },
});

const inputElement = ref<HTMLInputElement | null>(null);

function focus(): void {
    inputElement.value?.focus();
}

function clearSearch(): void {
    modelValue.value = '';
    focus();
}

function inputBindings() {
    return {
        ...attrs,
        type: props.type,
        autocomplete: props.autocomplete,
        'data-slot': 'input',
        class: cn(
            'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
            'aria-invalid:ring-destructive/20 aria-invalid:border-destructive dark:aria-invalid:ring-destructive/40',
            props.class,
            isSearch.value ? 'pr-10 pl-9' : '',
        ),
    };
}

defineExpose({
    focus,
    element: inputElement,
});
</script>

<template>
    <span v-if="isSearch" class="relative block w-full min-w-0" data-slot="search-input">
        <input ref="inputElement" v-model="modelValue" v-bind="inputBindings()">
        <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <button
            v-if="String(modelValue).length > 0 && (attrs.disabled === undefined || attrs.disabled === false) && (attrs.readonly === undefined || attrs.readonly === false)"
            type="button"
            :aria-label="ui('Cancella ricerca')"
            class="absolute top-1/2 right-1.5 inline-flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
            @mousedown.prevent
            @click="clearSearch"
        >
            <X class="size-4" aria-hidden="true" />
        </button>
    </span>
    <input v-else ref="inputElement" v-model="modelValue" v-bind="inputBindings()">
</template>

<style scoped>
input[type='search']::-webkit-search-cancel-button,
input[type='search']::-webkit-search-decoration {
    -webkit-appearance: none;
    appearance: none;
}
</style>
