<script setup lang="ts">
import { Check, Wallet } from 'lucide-vue-next';
import { ref } from 'vue';
import { resolveCategoryIcon } from '@/lib/category-appearance';

type Choice = {
    value: string;
    label: string;
    logo_url?: string | null;
    icon?: string | null;
    color?: string | null;
};
const props = defineProps<{
    modelValue: string;
    options: Choice[];
    appearance?: 'category' | 'reference';
}>();
defineEmits<{ 'update:modelValue': [value: string] }>();
const failedLogos = ref(new Set<string>());

function categoryStyle(option: Choice) {
    const color = option.color?.trim();

    if (props.appearance !== 'category' || !color) {
        return undefined;
    }

    const selected = props.modelValue === option.value;

    return {
        borderColor: `color-mix(in srgb, ${color} ${selected ? '90%' : '35%'}, transparent)`,
        backgroundColor: `color-mix(in srgb, ${color} ${selected ? '22%' : '7%'}, var(--background))`,
        boxShadow: selected ? `0 0 0 1px ${color} inset` : undefined,
    };
}
</script>

<template>
    <div
        class="flex max-w-full min-w-0 gap-2 overflow-x-auto overscroll-x-contain py-1"
        role="group"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="modelValue === option.value"
            class="flex min-h-11 shrink-0 touch-manipulation items-center gap-2 rounded-full border px-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            :style="categoryStyle(option)"
            :class="
                appearance === 'category' && option.color
                    ? 'text-foreground'
                    : modelValue === option.value
                      ? 'border-primary bg-primary text-primary-foreground'
                      : 'border-border bg-background text-foreground'
            "
            @click="$emit('update:modelValue', option.value)"
        >
            <img
                v-if="
                    appearance !== 'category' &&
                    option.logo_url &&
                    !failedLogos.has(option.logo_url)
                "
                :src="option.logo_url"
                alt=""
                class="size-6 shrink-0 rounded-md bg-background object-contain p-0.5"
                @error="failedLogos.add(option.logo_url!)"
            />
            <component
                v-else
                :is="
                    appearance === 'category'
                        ? resolveCategoryIcon(option.icon)
                        : Wallet
                "
                class="size-4 shrink-0"
                :style="
                    appearance === 'category' && option.color
                        ? { color: option.color }
                        : undefined
                "
                aria-hidden="true"
            />
            {{ option.label }}
            <Check
                v-if="modelValue === option.value"
                class="size-4 shrink-0"
                :style="
                    appearance === 'category' && option.color
                        ? { color: option.color }
                        : undefined
                "
                aria-hidden="true"
            />
        </button>
    </div>
</template>
