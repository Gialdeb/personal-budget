<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { helpCenterContent } from '@/i18n/help-center-content';
import { show as showHelpCenterArticle } from '@/routes/help-center/articles';
import type { PublicKnowledgeArticle } from '@/types';

defineProps<{
    article: PublicKnowledgeArticle;
}>();

const { locale } = useI18n();
const content = computed(() =>
    locale.value === 'it' ? helpCenterContent.it : helpCenterContent.en,
);
</script>

<template>
    <article
        class="rounded-[2rem] border border-[#e9ddd4] bg-white p-7 shadow-[0_24px_60px_-44px_rgba(15,23,42,0.16)]"
    >
        <p
            v-if="article.section?.title"
            class="text-[11px] font-semibold tracking-[0.18em] text-[#b65642] uppercase"
        >
            {{ article.section.title }}
        </p>

        <div class="mt-3 space-y-3">
            <h3
                class="text-[1.3rem] font-semibold tracking-tight text-slate-950"
            >
                {{ article.title }}
            </h3>
            <p class="text-sm leading-7 text-slate-600">
                {{ article.excerpt }}
            </p>
        </div>

        <div class="mt-6 flex items-center justify-between gap-4">
            <p
                class="text-xs font-medium tracking-[0.16em] text-slate-400 uppercase"
            >
                {{ article.available_locales.join(' · ') }}
            </p>
            <Link
                :href="showHelpCenterArticle(article)"
                class="inline-flex items-center gap-2 rounded-2xl border border-[#e7dad1] bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
            >
                {{ content.common.articleReadLabel }}
                <ArrowRight class="size-4" />
            </Link>
        </div>
    </article>
</template>
