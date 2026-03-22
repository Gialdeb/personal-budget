<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();
const { t } = useI18n();
const appearanceValues = [
    { value: 'light', Icon: Sun },
    { value: 'dark', Icon: Moon },
    { value: 'system', Icon: Monitor },
] as const;

const tabs = computed(() => [
    { ...appearanceValues[0], label: t('app.appearance.light') },
    { ...appearanceValues[1], label: t('app.appearance.dark') },
    { ...appearanceValues[2], label: t('app.appearance.system') },
]);
</script>

<template>
    <div
        class="inline-flex flex-wrap gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-2 dark:border-slate-800 dark:bg-slate-900/80"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-xl px-4 py-2.5 text-sm font-medium transition-all',
                appearance === value
                    ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-100 dark:text-slate-900'
                    : 'text-slate-500 hover:bg-white hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
            ]"
        >
            <component :is="Icon" class="h-4 w-4" />
            <span class="ml-2">{{ label }}</span>
        </button>
    </div>
</template>
