<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.18),_transparent_35%),linear-gradient(180deg,_rgba(248,250,252,1),_rgba(241,245,249,1))] p-6 md:p-10 dark:bg-[radial-gradient(circle_at_top,_rgba(34,197,94,0.14),_transparent_30%),linear-gradient(180deg,_rgba(2,6,23,1),_rgba(15,23,42,1))]"
    >
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(148,163,184,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.08)_1px,transparent_1px)] bg-[size:32px_32px] opacity-40" />
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="relative z-10 flex items-center self-center"
            >
                <AppLogo />
            </Link>

            <div class="relative z-10 flex flex-col gap-6">
                <Card
                    class="rounded-[1.75rem] border-white/70 bg-white/90 shadow-[0_32px_80px_-48px_rgba(15,23,42,0.5)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <CardHeader class="px-10 pt-8 pb-0 text-center">
                        <CardTitle class="text-xl tracking-tight">{{ title }}</CardTitle>
                        <CardDescription class="leading-6">
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
