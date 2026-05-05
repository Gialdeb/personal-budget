<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Globe,
    Github,
    Linkedin,
    ShieldCheck,
    Sparkles,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSeoHead from '@/components/public/PublicSeoHead.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { publicProfileLinks } from '@/config/public-profile';
import { aboutContent } from '@/i18n/about-content';
import PublicMarketingLayout from '@/layouts/public/PublicMarketingLayout.vue';
import { trackPublicCta } from '@/lib/analytics';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const { locale } = useI18n();
const page = usePage();

const content = computed(() =>
    locale.value === 'it' ? aboutContent.it : aboutContent.en,
);

const workIcons = [Sparkles, ShieldCheck, Wrench, Sparkles];

function trackProfileLink(
    eventName: string,
    placement: string,
    target: string,
): void {
    trackPublicCta(page, eventName, {
        placement,
        target,
    });
}
</script>

<template>
    <PublicMarketingLayout>
        <PublicSeoHead />

        <div class="min-h-screen bg-[#fffdfb] text-slate-950">
            <PublicSiteHeader :can-register="canRegister" current-page="about-me" />

        <main class="pb-14 sm:pb-18">
            <section
                class="relative mx-auto w-full max-w-7xl px-4 pt-6 pb-14 sm:px-6 sm:pt-10 sm:pb-18 lg:px-8 lg:pt-16 lg:pb-22"
            >
                <div
                    class="absolute inset-x-4 top-0 -z-10 h-full rounded-[2rem] bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.08),transparent_26%),radial-gradient(circle_at_top_right,rgba(234,90,71,0.08),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.96),rgba(255,252,248,0.94))] sm:inset-x-6 sm:rounded-[2.5rem] lg:inset-x-8"
                />

                <div
                    class="grid gap-7 sm:gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(18rem,24rem)] lg:items-end"
                >
                    <div class="max-w-4xl space-y-5 sm:space-y-6">
                        <div
                            class="inline-flex max-w-full items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[10px] font-semibold tracking-[0.14em] text-[#b65642] uppercase sm:text-[11px] sm:tracking-[0.16em]"
                        >
                            {{ content.hero.eyebrow }}
                        </div>

                        <div class="space-y-3 sm:space-y-4">
                            <div class="space-y-2">
                                <p
                                    class="text-xs font-medium tracking-[0.08em] text-slate-500 uppercase sm:text-sm"
                                >
                                    {{ content.hero.nameLabel }}
                                </p>
                                <p
                                    class="text-xl font-semibold tracking-tight text-slate-950 sm:text-3xl"
                                >
                                    {{ publicProfileLinks.name }}
                                </p>
                            </div>
                            <h1
                                class="max-w-4xl text-[2rem] leading-[0.98] font-semibold tracking-[-0.04em] text-slate-950 sm:text-[3rem] lg:text-[4.4rem]"
                            >
                                {{ content.hero.title }}
                            </h1>
                            <p
                                class="max-w-3xl text-[15px] leading-7 text-slate-600 sm:text-lg sm:leading-8"
                            >
                                {{ content.hero.description }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <a
                                :href="publicProfileLinks.website"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d] sm:w-auto"
                                @click="
                                    trackProfileLink(
                                        'about_website_clicked',
                                        'about_hero',
                                        publicProfileLinks.website,
                                    )
                                "
                            >
                                <Globe class="size-4" />
                                {{ content.hero.websiteLabel }}
                            </a>
                            <a
                                :href="publicProfileLinks.linkedin"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950 sm:w-auto"
                                @click="
                                    trackProfileLink(
                                        'about_linkedin_clicked',
                                        'about_hero',
                                        publicProfileLinks.linkedin,
                                    )
                                "
                            >
                                <Linkedin class="size-4" />
                                {{ content.hero.linkedinLabel }}
                            </a>
                            <a
                                :href="publicProfileLinks.github"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950 sm:w-auto"
                                @click="
                                    trackProfileLink(
                                        'about_github_clicked',
                                        'about_hero',
                                        publicProfileLinks.github,
                                    )
                                "
                            >
                                <Github class="size-4" />
                                {{ content.hero.githubLabel }}
                            </a>
                        </div>
                    </div>

                    <div
                        class="rounded-[1.75rem] border border-[#efe4db] bg-white/88 p-4 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] backdrop-blur sm:rounded-[2rem] sm:p-6"
                    >
                        <div class="space-y-4 sm:space-y-5">
                            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-center sm:text-left">
                                <img
                                    :src="publicProfileLinks.portrait"
                                    :alt="publicProfileLinks.name"
                                    class="h-56 w-full rounded-[1.5rem] object-cover object-[center_18%] shadow-[0_18px_35px_-20px_rgba(15,23,42,0.25)] sm:size-20 sm:w-20 sm:object-cover sm:object-top"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-lg font-semibold tracking-tight text-slate-950"
                                    >
                                        {{ publicProfileLinks.name }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-6 text-slate-600"
                                    >
                                        {{ content.hero.nameLabel }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="rounded-[1.5rem] border border-[#f3e7df] bg-[#fffaf6] p-4 sm:rounded-[1.75rem] sm:p-5"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase"
                                >
                                    {{ content.hero.profileLabel }}
                                </p>
                                <p
                                    class="mt-3 text-lg font-semibold tracking-tight text-slate-950"
                                >
                                    {{ content.profile.title }}
                                </p>
                            </div>
                            <div
                                class="rounded-[1.5rem] border border-[#f3e7df] bg-white p-4 sm:rounded-[1.75rem] sm:p-5"
                            >
                                <p
                                    class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase"
                                >
                                    {{ content.hero.projectLabel }}
                                </p>
                                <p
                                    class="mt-3 text-sm leading-7 text-slate-600"
                                >
                                    {{ content.origin.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-12 px-4 sm:px-6 sm:gap-16 lg:px-8 lg:gap-18"
            >
                <PublicPageSection
                    :eyebrow="content.profile.eyebrow"
                    :title="content.profile.title"
                    :description="content.profile.description"
                >
                    <div class="grid gap-4 sm:gap-5 lg:grid-cols-3">
                        <article
                            v-for="item in content.profile.items"
                            :key="item.title"
                            class="rounded-[1.5rem] border border-[#efe4db] bg-white p-5 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.16)] sm:rounded-[2rem] sm:p-6"
                        >
                            <h2
                                class="text-lg font-semibold tracking-tight text-slate-950"
                            >
                                {{ item.title }}
                            </h2>
                            <p class="mt-3 text-sm leading-7 text-slate-600">
                                {{ item.description }}
                            </p>
                        </article>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.origin.eyebrow"
                    :title="content.origin.title"
                    :description="content.origin.description"
                >
                    <div
                        class="grid gap-4 rounded-[1.75rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-4 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] sm:gap-5 sm:rounded-[2rem] sm:p-7 lg:grid-cols-3"
                    >
                        <article
                            v-for="item in content.origin.items"
                            :key="item.title"
                            class="rounded-[1.5rem] border border-[#f2e8e1] bg-white p-4 sm:rounded-[1.75rem] sm:p-5"
                        >
                            <h2
                                class="text-lg font-semibold tracking-tight text-slate-950"
                            >
                                {{ item.title }}
                            </h2>
                            <p class="mt-3 text-sm leading-7 text-slate-600">
                                {{ item.description }}
                            </p>
                        </article>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.work.eyebrow"
                    :title="content.work.title"
                    :description="content.work.description"
                >
                    <div class="grid gap-4 lg:grid-cols-2">
                        <article
                            v-for="(item, index) in content.work.items"
                            :key="item"
                            class="flex items-start gap-4 rounded-[1.5rem] border border-[#efe4db] bg-white p-4 shadow-[0_24px_60px_-48px_rgba(15,23,42,0.16)] sm:rounded-[1.75rem] sm:p-5"
                        >
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                            >
                                <component
                                    :is="workIcons[index]"
                                    class="size-5"
                                />
                            </div>
                            <p class="text-sm leading-7 text-slate-700">
                                {{ item }}
                            </p>
                        </article>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    class="pb-4"
                    :eyebrow="content.links.eyebrow"
                    :title="content.links.title"
                    :description="content.links.description"
                >
                    <div
                        class="grid gap-4 rounded-[1.75rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-4 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] sm:gap-5 sm:rounded-[2rem] sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
                    >
                        <a
                            :href="publicProfileLinks.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group rounded-[1.5rem] border border-[#f2e8e1] bg-white p-5 transition hover:border-[#dcc8be] sm:rounded-[1.75rem] sm:p-6"
                            @click="
                                trackProfileLink(
                                    'about_website_clicked',
                                    'about_links',
                                    publicProfileLinks.website,
                                )
                            "
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-lg font-semibold tracking-tight text-slate-950"
                                    >
                                        {{ content.hero.websiteLabel }}
                                    </p>
                                    <p class="mt-2 break-all text-sm leading-7 text-slate-600 sm:break-normal">
                                        {{ publicProfileLinks.website }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Globe class="size-5 text-[#ea5a47]" />
                                    <ArrowUpRight
                                        class="size-4 text-slate-400"
                                    />
                                </div>
                            </div>
                        </a>

                        <a
                            :href="publicProfileLinks.linkedin"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group rounded-[1.5rem] border border-[#f2e8e1] bg-white p-5 transition hover:border-[#dcc8be] sm:rounded-[1.75rem] sm:p-6"
                            @click="
                                trackProfileLink(
                                    'about_linkedin_clicked',
                                    'about_links',
                                    publicProfileLinks.linkedin,
                                )
                            "
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-lg font-semibold tracking-tight text-slate-950"
                                    >
                                        {{ content.links.linkedinLabel }}
                                    </p>
                                    <p class="mt-2 break-all text-sm leading-7 text-slate-600 sm:break-normal">
                                        {{ publicProfileLinks.linkedin }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Linkedin class="size-5 text-[#ea5a47]" />
                                    <ArrowUpRight
                                        class="size-4 text-slate-400"
                                    />
                                </div>
                            </div>
                        </a>

                        <a
                            :href="publicProfileLinks.github"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group rounded-[1.5rem] border border-[#f2e8e1] bg-white p-5 transition hover:border-[#dcc8be] sm:rounded-[1.75rem] sm:p-6"
                            @click="
                                trackProfileLink(
                                    'about_github_clicked',
                                    'about_links',
                                    publicProfileLinks.github,
                                )
                            "
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-lg font-semibold tracking-tight text-slate-950"
                                    >
                                        {{ content.links.githubLabel }}
                                    </p>
                                    <p class="mt-2 break-all text-sm leading-7 text-slate-600 sm:break-normal">
                                        {{ publicProfileLinks.github }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Github class="size-5 text-[#ea5a47]" />
                                    <ArrowUpRight
                                        class="size-4 text-slate-400"
                                    />
                                </div>
                            </div>
                        </a>
                    </div>
                </PublicPageSection>
            </div>
        </main>

        <PublicSiteFooter :can-register="canRegister" />
        <PublicCookieConsent />
        </div>
    </PublicMarketingLayout>
</template>
