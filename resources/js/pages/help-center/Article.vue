<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import HelpCenterArticleCard from '@/components/public/help-center/HelpCenterArticleCard.vue';
import HelpCenterArticleContent from '@/components/public/help-center/HelpCenterArticleContent.vue';
import HelpCenterBreadcrumbs from '@/components/public/help-center/HelpCenterBreadcrumbs.vue';
import HelpCenterHero from '@/components/public/help-center/HelpCenterHero.vue';
import HelpCenterSupportCta from '@/components/public/help-center/HelpCenterSupportCta.vue';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSeoHead from '@/components/public/PublicSeoHead.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { helpCenterContent } from '@/i18n/help-center-content';
import { index as helpCenterIndex } from '@/routes/help-center';
import { show as showHelpCenterArticle } from '@/routes/help-center/articles';
import { show as showHelpCenterSection } from '@/routes/help-center/sections';
import type { HelpCenterArticlePageProps } from '@/types';

const props = defineProps<HelpCenterArticlePageProps>();
const { locale } = useI18n();

const content = computed(() =>
    locale.value === 'it' ? helpCenterContent.it : helpCenterContent.en,
);

const breadcrumbs = computed(() => [
    {
        label: content.value.common.rootLabel,
        href: helpCenterIndex(),
    },
    {
        label: props.article.section?.title ?? '',
        href: props.article.section
            ? showHelpCenterSection(props.article.section)
            : null,
    },
    {
        label: props.article.title ?? '',
        href: null,
    },
]);
</script>

<template>
    <PublicSeoHead />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader :can-register="props.canRegister" />

        <main class="pb-18">
            <HelpCenterHero
                :eyebrow="content.article.eyebrow"
                :title="props.article.title ?? ''"
                :description="props.article.excerpt ?? ''"
            />

            <div
                class="mx-auto flex w-full max-w-5xl flex-col gap-12 px-6 sm:px-8 lg:gap-14"
            >
                <HelpCenterBreadcrumbs :items="breadcrumbs" />

                <div
                    class="flex flex-wrap items-center gap-3 text-sm text-slate-500"
                >
                    <span
                        class="inline-flex items-center rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                    >
                        {{ props.article.section?.title }}
                    </span>
                    <span>
                        {{ content.common.availableIn }}:
                        {{ props.article.available_locales.join(' · ') }}
                    </span>
                </div>

                <HelpCenterArticleContent :body="props.article.body" />

                <PublicPageSection
                    v-if="props.relatedArticles.length > 0"
                    :eyebrow="content.common.browseLabel"
                    :title="content.article.relatedTitle"
                    :description="content.article.relatedDescription"
                >
                    <div class="grid gap-5">
                        <HelpCenterArticleCard
                            v-for="relatedArticle in props.relatedArticles"
                            :key="relatedArticle.uuid"
                            :article="relatedArticle"
                        />
                    </div>
                </PublicPageSection>

                <HelpCenterSupportCta
                    :can-register="props.canRegister"
                    :source-route="'help-center.articles.show'"
                    :source-url="showHelpCenterArticle(props.article).url"
                />
            </div>
        </main>

        <PublicSiteFooter :can-register="props.canRegister" />
        <PublicCookieConsent />
    </div>
</template>
