<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const { t } = useI18n();
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-2xl bg-gradient-to-br from-[#ea5a47] via-[#ef6c5b] to-[#f28c6e] text-white shadow-lg shadow-[#ea5a47]/20"
    >
        <AppLogoIcon class="size-5 text-white" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span
            class="truncate leading-tight font-semibold text-slate-950 dark:text-slate-50"
        >
            {{ t('app.name') }}
        </span>
        <span class="truncate text-xs text-slate-500 dark:text-slate-400">
            {{ t('app.brand.tagline') }}
        </span>
    </div>
</template>
