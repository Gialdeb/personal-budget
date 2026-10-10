<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    title: string;
    message: string;
    codeLabel: string;
    errorCode: string;
    requestId: string;
    retryLabel: string;
    backLabel: string;
    dashboardLabel: string;
    dashboardUrl: string;
}>();

function retry() {
    window.location.reload();
}
function back() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.assign(props.dashboardUrl);
    }
}
</script>

<template>
    <main
        class="flex min-h-svh items-center justify-center bg-background p-5 text-foreground"
    >
        <Head :title="title" />
        <section
            class="w-full max-w-lg rounded-3xl border bg-card p-6 shadow-lg sm:p-8"
        >
            <div class="mb-5 flex items-center gap-3 text-primary">
                <ShieldAlert class="size-7" aria-hidden="true" /><span
                    class="font-semibold"
                    >Soamco</span
                >
            </div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p class="mt-3 text-muted-foreground">{{ message }}</p>
            <p class="mt-4 text-sm text-muted-foreground">
                {{ codeLabel }}:
                <strong class="font-mono text-foreground">{{
                    errorCode
                }}</strong>
            </p>
            <nav class="mt-6 flex flex-wrap gap-3" :aria-label="title">
                <Button class="min-h-11" @click="retry">{{
                    retryLabel
                }}</Button>
                <Button variant="outline" class="min-h-11" @click="back">{{
                    backLabel
                }}</Button>
                <Button as-child variant="ghost" class="min-h-11"
                    ><a :href="dashboardUrl">{{ dashboardLabel }}</a></Button
                >
            </nav>
        </section>
    </main>
</template>
