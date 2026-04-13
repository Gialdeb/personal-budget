<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import HelpCenterArticleCard from '@/components/public/help-center/HelpCenterArticleCard.vue';
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
import type { HelpCenterSectionPageProps } from '@/types';

const props = defineProps<HelpCenterSectionPageProps>();
const page = usePage();
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
        label: props.section.title ?? '',
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
                :eyebrow="content.section.eyebrow"
                :title="props.section.title ?? ''"
                :description="props.section.description ?? ''"
            />

            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-16 px-6 sm:px-8 lg:gap-18"
            >
                <HelpCenterBreadcrumbs :items="breadcrumbs" />

                <PublicPageSection
                    :eyebrow="content.common.browseLabel"
                    :title="content.section.articlesTitle"
                    :description="content.section.articlesDescription"
                >
                    <div
                        v-if="props.section.articles.length === 0"
                        class="rounded-[2rem] border border-dashed border-[#ddc9bc] bg-white px-6 py-8 text-center"
                    >
                        <h2
                            class="text-xl font-semibold tracking-tight text-slate-950"
                        >
                            {{ content.section.emptyTitle }}
                        </h2>
                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            {{ content.section.emptyDescription }}
                        </p>
                    </div>

                    <div v-else class="grid gap-5 lg:grid-cols-2">
                        <HelpCenterArticleCard
                            v-for="article in props.section.articles"
                            :key="article.uuid"
                            :article="article"
                        />
                    </div>
                </PublicPageSection>

                <HelpCenterSupportCta
                    :can-register="props.canRegister"
                    :source-route="'help-center.sections.show'"
                    :source-url="String(page.url ?? '')"
                />
            </div>
        </main>

        <PublicSiteFooter :can-register="props.canRegister" />
        <PublicCookieConsent />
    </div>
</template>
