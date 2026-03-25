<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(6,182,212,0.16),_transparent_28%),linear-gradient(180deg,_rgba(248,250,252,1),_rgba(241,245,249,1))] p-6 md:p-10 dark:bg-[radial-gradient(circle_at_top,_rgba(6,182,212,0.14),_transparent_28%),linear-gradient(180deg,_rgba(2,6,23,1),_rgba(15,23,42,1))]"
    >
        <div
            class="absolute inset-0 bg-[linear-gradient(to_right,rgba(148,163,184,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.08)_1px,transparent_1px)] bg-[size:32px_32px] opacity-30"
        />
        <div class="w-full max-w-sm">
            <div class="relative z-10 flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="home()" class="flex items-center font-medium">
                        <AppLogo />
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
