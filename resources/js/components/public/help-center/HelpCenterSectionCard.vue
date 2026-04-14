<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { helpCenterContent } from '@/i18n/help-center-content';
import { show as showHelpCenterSection } from '@/routes/help-center/sections';
import type { PublicKnowledgeSection } from '@/types';

defineProps<{
    section: PublicKnowledgeSection;
}>();

const { locale } = useI18n();
const content = computed(() =>
    locale.value === 'it' ? helpCenterContent.it : helpCenterContent.en,
);
</script>

<template>
    <article
        class="rounded-[2rem] border border-[#e9ddd4] bg-white p-7 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)]"
    >
        <div class="flex flex-wrap items-center gap-2">
            <span
                v-if="section.article_count > 0"
                class="inline-flex items-center rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
            >
                {{ section.article_count }}
                {{ content.common.articleCountLabel }}
            </span>
            <span
                class="inline-flex items-center rounded-full border border-[#ebe6df] bg-[#f8f5f2] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-slate-600 uppercase"
            >
                {{ section.available_locales.join(' · ') }}
            </span>
        </div>

        <div class="mt-5 space-y-3">
            <h2
                class="text-[1.45rem] font-semibold tracking-tight text-slate-950"
            >
                {{ section.title }}
            </h2>
            <p class="text-sm leading-7 text-slate-600 sm:text-[0.95rem]">
                {{ section.description }}
            </p>
        </div>

        <ul v-if="section.articles.length > 0" class="mt-6 grid gap-3">
            <li
                v-for="article in section.articles.slice(0, 3)"
                :key="article.uuid"
                class="rounded-2xl border border-[#f1e6de] bg-[#fffaf6] px-4 py-3 text-sm leading-6 text-slate-700"
            >
                {{ article.title }}
            </li>
        </ul>

        <div class="mt-6 flex items-center justify-end">
            <Link
                :href="showHelpCenterSection(section)"
                class="inline-flex items-center gap-2 rounded-2xl border border-[#e7dad1] bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
            >
                {{ content.common.sectionOpenLabel }}
                <ArrowRight class="size-4" />
            </Link>
        </div>
    </article>
</template>
