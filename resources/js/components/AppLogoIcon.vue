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
            x="8"
            y="6"
            width="32"
            height="36"
            rx="12"
            fill="currentColor"
            opacity="0.18"
        />
        <path
            d="M16 18C16 15.7909 17.7909 14 20 14H28C30.2091 14 32 15.7909 32 18V18C32 20.2091 30.2091 22 28 22H20C17.7909 22 16 20.2091 16 18V18Z"
            fill="currentColor"
        />
        <path
            d="M16 30C16 27.7909 17.7909 26 20 26H25C27.2091 26 29 27.7909 29 30V30C29 32.2091 27.2091 34 25 34H20C17.7909 34 16 32.2091 16 30V30Z"
            fill="currentColor"
            opacity="0.72"
        />
        <circle cx="31.5" cy="30" r="2.5" fill="currentColor" />
        <path
            d="M24 10V38"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            opacity="0.16"
        />
    </svg>
</template>
