<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { transactionTones } from '@/lib/transaction-presentation.js';
defineProps<{
    modelValue: boolean;
    category: keyof typeof transactionTones;
    label: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>
<template>
    <label
        class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm transition-shadow focus-within:ring-2 focus-within:ring-ring hover:shadow-sm"
        :class="[
            transactionTones[category].badge,
            modelValue ? 'font-medium ring-1 ring-current/20' : 'opacity-70',
        ]"
    >
        <Checkbox
            :model-value="modelValue"
            :aria-label="label"
            class="border-current bg-background/70 text-current data-[state=checked]:border-current data-[state=checked]:bg-current data-[state=checked]:text-inherit [&_svg]:text-background"
            @update:model-value="emit('update:modelValue', $event === true)"
        />
        <span>{{ label }}</span>
    </label>
</template>
