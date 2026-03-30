<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
    highlights: string[];
    imageSrc: string;
    imageAlt: string;
    reversed?: boolean;
}>();
</script>

<template>
    <article
        class="grid gap-8 rounded-[2rem] border border-[#ece4dc] bg-white p-5 shadow-[0_26px_70px_-46px_rgba(15,23,42,0.22)] sm:p-7 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)] lg:items-center lg:gap-10"
        :class="
            reversed
                ? 'lg:[&>*:first-child]:order-2 lg:[&>*:last-child]:order-1'
                : ''
        "
    >
        <div class="space-y-5">
            <div class="space-y-3">
                <h3
                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-[2rem]"
                >
                    {{ title }}
                </h3>
                <p class="text-sm leading-7 text-slate-600 sm:text-base">
                    {{ description }}
                </p>
            </div>

            <ul class="grid gap-3">
                <li
                    v-for="highlight in highlights"
                    :key="highlight"
                    class="flex items-start gap-3 rounded-2xl border border-[#f1e6de] bg-[#fffaf6] px-4 py-3 text-sm leading-6 text-slate-700"
                >
                    <span
                        class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-[#ea5a47]"
                    />
                    <span>{{ highlight }}</span>
                </li>
            </ul>
        </div>

        <div
            class="overflow-hidden rounded-[1.75rem] border border-[#efe4db] bg-[#fff7f1] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)] sm:p-4"
        >
            <img
                :src="imageSrc"
                :alt="imageAlt"
                class="h-auto w-full rounded-[1.15rem] border border-[#eadfd7] bg-white object-cover"
                loading="lazy"
            />
        </div>
    </article>
</template>
