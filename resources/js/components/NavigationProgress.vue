<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useNavigationFeedback } from '@/composables/useNavigationFeedback';

const { pendingUrl } = useNavigationFeedback();
const { t } = useI18n();
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-[env(safe-area-inset-top,0px)] z-[100]"
    >
        <div
            v-show="pendingUrl !== null"
            data-navigation-progress
            class="h-0.5 w-full bg-primary"
            aria-hidden="true"
        />
        <span role="status" aria-live="polite" class="sr-only">{{
            pendingUrl !== null ? t('app.common.loading') : ''
        }}</span>
    </div>
</template>
