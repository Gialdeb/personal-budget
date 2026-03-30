<script setup lang="ts">
import PublicRichTextRenderer from '@/components/public/changelog/PublicRichTextRenderer.vue';
import type { PublicChangelogSection } from '@/types';

defineProps<{
    section: PublicChangelogSection;
}>();
</script>

<template>
    <section
        class="rounded-[2rem] border border-[#e9ddd4] bg-white p-7 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.16)]"
    >
        <div class="flex items-center justify-between gap-4">
            <h2
                class="text-[1.25rem] font-semibold tracking-tight text-slate-950"
            >
                {{ section.label ?? section.key }}
            </h2>
            <span
                class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
            >
                {{ section.items.length }} item
            </span>
        </div>

        <div class="mt-6 space-y-4">
            <article
                v-for="item in section.items"
                :key="`${section.key}-${item.sort_order}-${item.title ?? 'item'}`"
                class="rounded-[1.5rem] border border-[#f0e5dd] bg-[#fffaf6] p-5"
            >
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3
                            v-if="item.title"
                            class="text-base font-semibold tracking-tight text-slate-950"
                        >
                            {{ item.title }}
                        </h3>
                        <span
                            v-if="item.platform"
                            class="inline-flex items-center rounded-full border border-[#ebe6df] bg-white px-2.5 py-1 text-[11px] font-semibold tracking-[0.14em] text-slate-500 uppercase"
                        >
                            {{ item.platform }}
                        </span>
                        <span
                            v-if="item.item_type"
                            class="inline-flex items-center rounded-full border border-[#f7d7d1] bg-[#fff1ee] px-2.5 py-1 text-[11px] font-semibold tracking-[0.14em] text-[#de4f3d] uppercase"
                        >
                            {{ item.item_type }}
                        </span>
                    </div>

                    <PublicRichTextRenderer :content="item.body" />

                    <div v-if="item.link_url && item.link_label" class="pt-1">
                        <a
                            :href="item.link_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center rounded-2xl border border-[#e7dad1] bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                        >
                            {{ item.link_label }}
                        </a>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
