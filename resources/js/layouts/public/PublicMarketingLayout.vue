<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TawkToWidget from '@/components/public/TawkToWidget.vue';

const page = usePage();
const tawkToConfig = computed(
    () => page.props.publicIntegrations?.tawkTo ?? null,
);
</script>

<template>
    <TawkToWidget :config="tawkToConfig" />
    <slot />
</template>
