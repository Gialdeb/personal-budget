<script setup lang="ts">
import { onMounted } from 'vue';
import { loadTawkToWidget } from '@/lib/tawk-to.js';
import type { TawkToIntegrationConfig } from '@/types';

const props = defineProps<{
    config?: TawkToIntegrationConfig | null;
}>();

onMounted(() => {
    loadTawkToWidget(props.config ?? null, undefined, {
        reporter: import.meta.env.DEV
            ? (state) => {
                  console.debug('[Tawk.to]', {
                      enabled: state.enabled,
                      hasPropertyId: state.hasPropertyId,
                      hasWidgetId: state.hasWidgetId,
                      loaded: state.loaded,
                      reason: state.reason,
                      scriptSrc: state.scriptSrc,
                  });
              }
            : undefined,
    });
});
</script>

<template>
    <span v-if="false" />
</template>
