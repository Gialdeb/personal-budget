<script setup lang="ts">
import { computed } from 'vue';
import { userErrorMessage } from '@/lib/notification-feedback.js';
const props = defineProps<{ message?: string }>();
const readableMessage = computed(() => userErrorMessage(props.message ?? ''));
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-red-600 dark:text-red-500">
            {{ readableMessage }}
        </p>
    </div>
</template>
