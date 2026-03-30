<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        fill="none"
    >
        <rect
            x="12"
            y="24"
            width="5"
            height="13"
            rx="2.5"
            fill="currentColor"
        />
        <rect
            x="21.5"
            y="17"
            width="5"
            height="20"
            rx="2.5"
            fill="currentColor"
        />
        <rect
            x="31"
            y="11"
            width="5"
            height="26"
            rx="2.5"
            fill="currentColor"
        />
        <path
            d="M13 19L21.5 13L28.5 18L36 8.5"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle cx="36" cy="8.5" r="2.5" fill="currentColor" />
    </svg>
</template>
