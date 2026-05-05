<script setup lang="ts">
import { computed } from 'vue';
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        value?: string | number | null;
        variant?: 'inline' | 'veil';
        class?: string;
        maskLabel?: string;
    }>(),
    {
        value: null,
        variant: 'inline',
        class: '',
        maskLabel: 'Importo nascosto',
    },
);

const { isPrivacyModeEnabled } = usePrivacyMode();

const displayValue = computed(() =>
    props.value === null || props.value === undefined
        ? ''
        : String(props.value),
);

const maskClass = computed(() =>
    props.variant === 'veil'
        ? 'inline-flex min-h-[1.15em] min-w-[7.5ch] rounded-lg bg-[linear-gradient(135deg,rgba(15,23,42,0.16),rgba(15,23,42,0.08),rgba(15,23,42,0.18))] align-baseline text-transparent shadow-inner ring-1 ring-black/5 select-none dark:bg-[linear-gradient(135deg,rgba(255,255,255,0.18),rgba(255,255,255,0.07),rgba(255,255,255,0.14))] dark:ring-white/10'
        : 'inline-flex min-h-[1em] min-w-[5.5ch] rounded-full bg-slate-300/70 align-baseline text-transparent select-none dark:bg-slate-600/70',
);
</script>

<template>
    <span
        :class="
            cn(
                'sensitive-value transition-all duration-200',
                isPrivacyModeEnabled ? maskClass : '',
                props.class,
            )
        "
        :aria-label="isPrivacyModeEnabled ? maskLabel : undefined"
        :data-privacy-mode="isPrivacyModeEnabled ? 'masked' : 'visible'"
    >
        <span v-if="isPrivacyModeEnabled" aria-hidden="true">
            {{ variant === 'veil' ? '0000000' : '00000' }}
        </span>
        <slot v-else>
            {{ displayValue }}
        </slot>
    </span>
</template>
