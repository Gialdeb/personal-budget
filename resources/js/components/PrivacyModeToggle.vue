<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        compact?: boolean;
        class?: string;
    }>(),
    {
        compact: false,
        class: '',
    },
);

const { isPrivacyModeEnabled, privacyModeLabel, togglePrivacyMode } =
    usePrivacyMode();
</script>

<template>
    <button
        type="button"
        :aria-label="privacyModeLabel"
        :title="privacyModeLabel"
        :aria-pressed="isPrivacyModeEnabled"
        data-test="privacy-mode-toggle"
        :data-state="isPrivacyModeEnabled ? 'active' : 'inactive'"
        :class="
            cn(
                'inline-flex items-center justify-center gap-2 rounded-full border text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-offset-slate-950',
                compact ? 'h-10 w-10' : 'h-10 px-3',
                isPrivacyModeEnabled
                    ? 'border-sky-300 bg-sky-100 text-sky-800 shadow-sm ring-1 ring-sky-300/70 hover:bg-sky-200 dark:border-sky-400/40 dark:bg-sky-500/20 dark:text-sky-100 dark:ring-sky-400/30 dark:hover:bg-sky-500/25'
                    : 'border-slate-200/80 bg-white/90 text-slate-600 shadow-sm hover:bg-white hover:text-slate-950 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white',
                props.class,
            )
        "
        @click="togglePrivacyMode"
    >
        <EyeOff v-if="isPrivacyModeEnabled" class="size-4" />
        <Eye v-else class="size-4" />
        <span v-if="!compact" class="hidden sm:inline">
            {{ privacyModeLabel }}
        </span>
    </button>
</template>
