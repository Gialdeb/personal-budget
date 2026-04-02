<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Download,
    LayoutDashboard,
    ReceiptText,
    RefreshCcw,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicFeatureShowcase from '@/components/public/PublicFeatureShowcase.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSeoHead from '@/components/public/PublicSeoHead.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { featuresContent } from '@/i18n/features-content';
import { trackPublicCta } from '@/lib/analytics';
import { resolvePublicFeatureImage } from '@/lib/public-feature-assets';
import { dashboard, login, register } from '@/routes';

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
    locale.value === 'it' ? featuresContent.it : featuresContent.en,
);

const heroHighlights = computed(() => [
    { icon: LayoutDashboard, label: content.value.hero.highlights[0] },
    { icon: ReceiptText, label: content.value.hero.highlights[1] },
    { icon: RefreshCcw, label: content.value.hero.highlights[2] },
]);

const sections = computed(() =>
    content.value.sections.items.map((section, index) => ({
        ...section,
        imageSrc: resolvePublicFeatureImage(locale.value, section.key),
        reversed: index % 2 === 1,
    })),
);

function trackRegisterClick(placement: string): void {
    trackPublicCta(page, 'cta_register_clicked', {
        placement,
        target: register().url,
    });
}

function trackLoginClick(placement: string): void {
    trackPublicCta(page, 'cta_login_clicked', {
        placement,
        target: login().url,
    });
}

function trackPricingClick(placement: string): void {
    trackPublicCta(page, 'cta_pricing_clicked', {
        placement,
        target: '/pricing',
    });
}
</script>

<template>
    <PublicSeoHead />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader :can-register="canRegister" current-page="features" />

        <main class="pb-18">
            <section
                class="relative mx-auto w-full max-w-7xl px-6 pt-10 pb-18 sm:px-8 lg:pt-16 lg:pb-22"
            >
                <div
                    class="absolute inset-x-6 top-0 -z-10 h-full rounded-[2.5rem] bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.08),transparent_26%),radial-gradient(circle_at_top_right,rgba(234,90,71,0.08),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.96),rgba(255,252,248,0.94))] sm:inset-x-8"
                />

                <div
                    class="grid gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(18rem,24rem)] lg:items-end"
                >
                    <div class="max-w-4xl space-y-6">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                        >
                            {{ content.hero.eyebrow }}
                        </div>
                        <div class="space-y-4">
                            <h1
                                class="max-w-4xl text-[2.65rem] leading-none font-semibold tracking-[-0.04em] text-slate-950 sm:text-[3.5rem] lg:text-[4.4rem]"
                            >
                                {{ content.hero.title }}
                            </h1>
                            <p
                                class="max-w-3xl text-base leading-8 text-slate-600 sm:text-lg"
                            >
                                {{ content.hero.description }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="canRegister && !$page.props.auth.user"
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                                @click="
                                    trackRegisterClick('features_hero_primary')
                                "
                            >
                                {{ content.hero.registerLabel }}
                            </Link>
                            <Link
                                :href="
                                    $page.props.auth.user
                                        ? dashboard()
                                        : login()
                                "
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                                @click="
                                    trackLoginClick('features_hero_secondary')
                                "
                            >
                                {{ content.hero.loginLabel }}
                            </Link>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 rounded-[2rem] border border-[#efe4db] bg-white/88 p-5 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] backdrop-blur"
                    >
                        <div
                            v-for="item in heroHighlights"
                            :key="item.label"
                            class="flex items-center gap-3 rounded-2xl border border-[#f3e7df] bg-[#fffaf6] px-4 py-3"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                            >
                                <component :is="item.icon" class="size-4.5" />
                            </div>
                            <p
                                class="text-sm leading-6 font-medium text-slate-700"
                            >
                                {{ item.label }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-16 px-6 sm:px-8 lg:gap-18"
            >
                <PublicPageSection
                    id="feature-blocks"
                    :eyebrow="content.sections.eyebrow"
                    :title="content.sections.title"
                    :description="content.sections.description"
                >
                    <div class="grid gap-8">
                        <PublicFeatureShowcase
                            v-for="section in sections"
                            :key="section.key"
                            :title="section.title"
                            :description="section.description"
                            :highlights="section.highlights"
                            :image-src="section.imageSrc"
                            :image-alt="section.imageAlt"
                            :reversed="section.reversed"
                        />
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.importer.eyebrow"
                    :title="content.importer.title"
                    :description="content.importer.description"
                >
                    <div
                        class="grid gap-6 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] lg:grid-cols-[minmax(0,1.05fr)_minmax(18rem,0.95fr)]"
                    >
                        <div class="space-y-4">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-white/90 px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                            >
                                <Download class="size-3.5" />
                                {{ content.importer.eyebrow }}
                            </div>
                            <p
                                class="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base"
                            >
                                {{ content.importer.note }}
                            </p>

                            <div class="grid gap-3">
                                <article
                                    v-for="bullet in content.importer.bullets"
                                    :key="bullet"
                                    class="rounded-2xl border border-[#f1e7e1] bg-white px-4 py-4"
                                >
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                                        >
                                            <ReceiptText class="size-4" />
                                        </div>
                                        <p
                                            class="text-sm leading-7 text-slate-700"
                                        >
                                            {{ bullet }}
                                        </p>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <aside
                            class="rounded-[1.75rem] border border-[#f0e4db] bg-white p-6 shadow-[0_20px_50px_-40px_rgba(15,23,42,0.18)]"
                        >
                            <div class="space-y-4">
                                <div
                                    v-for="card in content.importer.cards"
                                    :key="card.title"
                                    class="flex items-center justify-between rounded-2xl border border-[#f2e8e1] bg-[#fffaf7] px-4 py-4"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-slate-950"
                                        >
                                            {{ card.title }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-6 text-slate-500"
                                        >
                                            {{ card.description }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-xs font-semibold uppercase"
                                        :class="
                                            card.badge === 'Sync'
                                                ? 'text-emerald-600'
                                                : 'text-[#b65642]'
                                        "
                                    >
                                        {{ card.badge }}
                                    </span>
                                </div>
                            </div>
                        </aside>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    class="pb-4"
                    :eyebrow="content.cta.eyebrow"
                    :title="content.cta.title"
                    :description="content.cta.description"
                >
                    <div
                        class="flex flex-col gap-3 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="max-w-2xl">
                            <p class="text-sm leading-7 text-slate-600">
                                {{ content.cta.note }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="canRegister && !$page.props.auth.user"
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                                @click="
                                    trackRegisterClick('features_cta_primary')
                                "
                            >
                                {{ content.cta.registerLabel }}
                            </Link>
                            <Link
                                href="/pricing"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                                @click="
                                    trackPricingClick('features_cta_secondary')
                                "
                            >
                                {{ content.cta.pricingLabel }}
                            </Link>
                        </div>
                    </div>
                </PublicPageSection>
            </div>
        </main>

        <PublicSiteFooter :can-register="canRegister" />
        <PublicCookieConsent />
    </div>
</template>
