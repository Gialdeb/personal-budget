<script setup lang="ts">
import type { LocaleOption } from '@/types';

defineProps<{
    locales: LocaleOption[];
    currentLocale: string;
    completion?: Record<string, boolean>;
}>();

const emit = defineEmits<{
    'update:currentLocale': [locale: string];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="locale in locales"
            :key="locale.code"
            type="button"
            :class="[
                'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition',
                currentLocale === locale.code
                    ? 'border-slate-900 bg-slate-900 text-white'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:text-slate-950',
            ]"
            @click="emit('update:currentLocale', locale.code)"
        >
            <span>{{ locale.label }}</span>
            <span
                :class="[
                    'h-2 w-2 rounded-full',
                    (completion?.[locale.code] ?? false)
                        ? 'bg-emerald-400'
                        : 'bg-amber-400',
                ]"
            />
        </button>
    </div>
</template>
