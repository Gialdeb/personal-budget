<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { OTPInput } from 'vue-input-otp';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue?: string;
    maxlength: number;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <OTPInput
        v-slot="slotProps"
        v-bind="$attrs"
        :model-value="props.modelValue"
        :maxlength="props.maxlength"
        :disabled="props.disabled"
        :container-class="
            cn('flex items-center gap-2 has-disabled:opacity-50', props.class)
        "
        data-slot="input-otp"
        class="disabled:cursor-not-allowed"
        @update:model-value="emit('update:modelValue', $event ?? '')"
    >
        <slot v-bind="slotProps" />
    </OTPInput>
</template>
