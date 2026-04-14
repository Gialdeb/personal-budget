<script setup lang="ts">
defineProps<{
    eyebrow: string;
    title: string;
    description: string;
    stats?: Array<{
        label: string;
        value: string;
        note?: string;
    }>;
}>();
</script>

<template>
    <section
        class="relative mx-auto w-full max-w-7xl px-6 pt-10 pb-18 sm:px-8 lg:pt-16 lg:pb-22"
    >
        <div
            class="absolute inset-x-6 top-0 -z-10 h-full rounded-[2.5rem] bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.08),transparent_26%),radial-gradient(circle_at_top_right,rgba(234,90,71,0.08),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.96),rgba(255,252,248,0.94))] sm:inset-x-8"
        />

        <div
            class="grid gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(18rem,24rem)]"
        >
            <div class="max-w-4xl space-y-6">
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                >
                    {{ eyebrow }}
                </div>
                <div class="space-y-4">
                    <h1
                        class="max-w-4xl text-[2.55rem] leading-none font-semibold tracking-[-0.04em] text-slate-950 sm:text-[3.4rem] lg:text-[4.25rem]"
                    >
                        {{ title }}
                    </h1>
                    <p
                        class="max-w-3xl text-base leading-8 text-slate-600 sm:text-lg"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>

            <div
                v-if="stats?.length"
                class="grid gap-3 rounded-[2rem] border border-[#efe4db] bg-white/88 p-5 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] backdrop-blur"
            >
                <article
                    v-for="stat in stats"
                    :key="stat.label"
                    class="rounded-[1.5rem] border border-[#f3e7df] bg-[#fffaf6] px-4 py-4"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase"
                    >
                        {{ stat.label }}
                    </p>
                    <p
                        class="mt-3 text-2xl font-semibold tracking-tight text-slate-950"
                    >
                        {{ stat.value }}
                    </p>
                    <p
                        v-if="stat.note"
                        class="mt-2 text-sm leading-6 text-slate-600"
                    >
                        {{ stat.note }}
                    </p>
                </article>
            </div>
        </div>
    </section>
</template>
