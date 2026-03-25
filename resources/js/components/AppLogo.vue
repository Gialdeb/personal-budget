<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-cyan-500 to-slate-900 text-white shadow-lg shadow-emerald-500/20"
    >
        <AppLogoIcon class="size-5 text-white" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span
            class="truncate leading-tight font-semibold text-slate-950 dark:text-slate-50"
        >
            Bilancio personale
        </span>
        <span class="truncate text-xs text-slate-500 dark:text-slate-400">
            Budget, conti e categorie
        </span>
    </div>
</template>
