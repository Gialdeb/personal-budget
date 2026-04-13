<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import HelpCenterArticleCard from '@/components/public/help-center/HelpCenterArticleCard.vue';
import HelpCenterHero from '@/components/public/help-center/HelpCenterHero.vue';
import HelpCenterSectionCard from '@/components/public/help-center/HelpCenterSectionCard.vue';
import HelpCenterSupportCta from '@/components/public/help-center/HelpCenterSupportCta.vue';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSeoHead from '@/components/public/PublicSeoHead.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { helpCenterContent } from '@/i18n/help-center-content';
import { index as changelogIndex } from '@/routes/changelog';
import type { HelpCenterIndexPageProps } from '@/types';

const props = defineProps<HelpCenterIndexPageProps>();
const { locale } = useI18n();

const content = computed(() =>
    locale.value === 'it' ? helpCenterContent.it : helpCenterContent.en,
);

const featuredArticles = computed(() =>
    props.sections
        .flatMap((section) => section.articles)
        .slice(0, 3),
);
</script>

<template>
    <PublicSeoHead />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader :can-register="props.canRegister" />

        <main class="pb-18">
            <HelpCenterHero
                :eyebrow="content.index.hero.eyebrow"
                :title="content.index.hero.title"
                :description="content.index.hero.description"
            />

            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-16 px-6 sm:px-8 lg:gap-18"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <a
                        href="#help-center-sections"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 transition hover:text-slate-950"
                    >
                        {{ content.index.hero.sectionAnchorLabel }}
                        <ArrowDownRight class="size-4" />
                    </a>
                    <Link
                        :href="changelogIndex()"
                        class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-slate-800"
                    >
                        {{ content.index.hero.changelogLabel }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <PublicPageSection
                    id="help-center-sections"
                    :eyebrow="content.index.sections.eyebrow"
                    :title="content.index.sections.title"
                    :description="content.index.sections.description"
                >
                    <div class="grid gap-5 lg:grid-cols-3">
                        <HelpCenterSectionCard
                            v-for="section in props.sections"
                            :key="section.uuid"
                            :section="section"
                        />
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    v-if="featuredArticles.length > 0"
                    :eyebrow="content.index.featured.eyebrow"
                    :title="content.index.featured.title"
                    :description="content.index.featured.description"
                >
                    <div class="grid gap-5 lg:grid-cols-3">
                        <HelpCenterArticleCard
                            v-for="article in featuredArticles"
                            :key="article.uuid"
                            :article="article"
                        />
                    </div>
                </PublicPageSection>

                <HelpCenterSupportCta
                    :can-register="props.canRegister"
                    :source-route="'help-center.index'"
                    source-url="/help-center"
                />
            </div>
        </main>

        <PublicSiteFooter :can-register="props.canRegister" />
        <PublicCookieConsent />
    </div>
</template>
