<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <article
        class="rounded-[1.9rem] border border-[#efe5df] bg-white p-7 shadow-[0_18px_45px_-36px_rgba(15,23,42,0.14)] transition hover:-translate-y-0.5 hover:shadow-[0_24px_55px_-34px_rgba(15,23,42,0.16)]"
    >
        <div
            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#fff3ee] text-[#ea5a47]"
        >
            <component :is="icon" class="size-5" />
        </div>
        <h3 class="mt-5 text-lg font-semibold tracking-tight text-slate-950">
            {{ title }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            {{ description }}
        </p>
    </article>
</template>
