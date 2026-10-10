<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { loadTawkToWidget } from '@/lib/tawk-to.js';
import type { TawkToIntegrationConfig } from '@/types';

type TawkToLoadState = {
    enabled: boolean;
    hasPropertyId: boolean;
    hasWidgetId: boolean;
    loaded: boolean;
    reason: string;
    scriptSrc: string | null;
};

const props = defineProps<{
    config?: TawkToIntegrationConfig | null;
}>();

let stopPublicNavigation: (() => void) | undefined;

onMounted(() => {
    // Third-party chat owns DOM and listeners outside Vue: leave its document
    // behind when navigating out of the public layout instead of reusing it.
    if (props.config?.enabled) {
        stopPublicNavigation = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get') {
                return;
            }

            window.location.assign(event.detail.visit.url.href);

            return false;
        });
    }

    loadTawkToWidget(props.config ?? null, undefined, {
        reporter: import.meta.env.DEV
            ? (state: TawkToLoadState) => {
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

onUnmounted(() => stopPublicNavigation?.());
</script>

<template>
    <span v-if="false" />
</template>
