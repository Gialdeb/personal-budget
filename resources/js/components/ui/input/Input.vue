<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = computed<string | number>({
    get() {
        return props.modelValue ?? props.defaultValue ?? '';
    },
    set(value) {
        emits('update:modelValue', value);
    },
});

const inputElement = ref<HTMLInputElement | null>(null);

function focus(): void {
    inputElement.value?.focus();
}

defineExpose({
    focus,
    element: inputElement,
});
</script>

<template>
    <input
        ref="inputElement"
        v-model="modelValue"
        v-bind="$attrs"
        data-slot="input"
        :class="cn(
            'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
            'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
            props.class,
        )"
    >
</template>
