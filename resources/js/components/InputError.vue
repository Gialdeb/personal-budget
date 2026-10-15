<script setup lang="ts">
import { watch } from 'vue';
import { notifyValidation } from '@/lib/notifications';
const props = defineProps<{
    message?: string;
}>();
watch(
    () => props.message,
    (message) => {
        if (message) {
            notifyValidation(message);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
