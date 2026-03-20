<script setup lang="ts">
import type { ImportPayloadEntry } from '@/types';

defineProps<{
    title: string;
    items: ImportPayloadEntry[];
    emptyLabel?: string;
}>();
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/60">
        <div class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ title }}
        </div>

        <div
            v-if="items.length > 0"
            class="grid gap-3 sm:grid-cols-2"
        >
            <div
                v-for="item in items"
                :key="item.key"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    {{ item.label }}
                </div>
                <div class="mt-1 break-words text-sm text-slate-900 dark:text-slate-100">
                    {{ item.value ?? 'Non valorizzato' }}
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ emptyLabel ?? 'Nessun dato disponibile.' }}
        </div>
    </div>
</template>
