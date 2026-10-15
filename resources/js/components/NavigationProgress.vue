<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNavigationFeedback } from '@/composables/useNavigationFeedback';

const { pendingUrl } = useNavigationFeedback();
const { t } = useI18n();
const visible = ref(false);
let startedAt = 0;
let hideTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    pendingUrl,
    (url) => {
        clearTimeout(hideTimer);

        if (url !== null) {
            if (!visible.value) {
                startedAt = performance.now();
            }

            visible.value = true;

            return;
        }

        // Keep cached/fast visits perceptible without delaying navigation itself.
        const remaining = Math.max(0, 180 - (performance.now() - startedAt));
        hideTimer = setTimeout(() => {
            visible.value = false;
        }, remaining);
    },
    { flush: 'sync' },
);

onUnmounted(() => clearTimeout(hideTimer));
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-x-0 top-[env(safe-area-inset-top,0px)] z-[1000]"
        >
            <div
                v-show="visible"
                data-navigation-progress
                class="navigation-progress h-[3px] w-full overflow-hidden bg-sky-600 shadow-sm dark:bg-sky-400"
                aria-hidden="true"
            >
                <div
                    class="navigation-progress-highlight h-full w-1/3 bg-white/60"
                />
            </div>
            <span role="status" aria-live="polite" class="sr-only">{{
                pendingUrl !== null ? t('app.common.loading') : ''
            }}</span>
        </div>
    </Teleport>
</template>

<style scoped>
.navigation-progress-highlight {
    animation: navigation-progress-travel 1.1s ease-in-out infinite;
}
@keyframes navigation-progress-travel {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(400%);
    }
}
@media (prefers-reduced-motion: reduce) {
    .navigation-progress-highlight {
        animation: none;
    }
}
</style>
