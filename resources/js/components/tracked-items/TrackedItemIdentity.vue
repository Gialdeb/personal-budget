<script setup lang="ts">
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        logoUrl?: string | null;
        compact?: boolean;
    }>(),
    {
        logoUrl: null,
        compact: false,
    },
);

const imageFailed = ref(false);

watch(
    () => props.logoUrl,
    () => {
        imageFailed.value = false;
    },
);
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2" :title="name">
        <img
            v-if="logoUrl && !imageFailed"
            :src="logoUrl"
            :alt="name"
            :class="
                compact
                    ? 'h-6 max-w-20 sm:h-7 sm:max-w-24'
                    : 'h-9 max-w-28 sm:h-10 sm:max-w-32'
            "
            class="shrink-0 rounded-md object-contain"
            loading="lazy"
            decoding="async"
            @error="imageFailed = true"
        />
        <span v-else class="truncate">{{ name }}</span>
    </span>
</template>
