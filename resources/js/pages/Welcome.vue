<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BellDot,
    CheckCircle2,
    ChevronRight,
    HeartHandshake,
    Landmark,
    LayoutTemplate,
    ReceiptText,
    RefreshCcw,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicFeatureCard from '@/components/public/PublicFeatureCard.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSeoHead from '@/components/public/PublicSeoHead.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { trackPublicCta } from '@/lib/analytics';
import { dashboard, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const { t } = useI18n();
const page = usePage();

const featureCards = computed(() => [
    {
        icon: LayoutTemplate,
        title: t('auth.welcome.features.cards.workspace.title'),
        description: t('auth.welcome.features.cards.workspace.description'),
    },
    {
        icon: RefreshCcw,
        title: t('auth.welcome.features.cards.recurring.title'),
        description: t('auth.welcome.features.cards.recurring.description'),
    },
    {
        icon: BellDot,
        title: t('auth.welcome.features.cards.visibility.title'),
        description: t('auth.welcome.features.cards.visibility.description'),
    },
]);

const principles = computed(() => [
    {
        title: t('auth.welcome.principles.items.clarity.title'),
        description: t('auth.welcome.principles.items.clarity.description'),
    },
    {
        title: t('auth.welcome.principles.items.control.title'),
        description: t('auth.welcome.principles.items.control.description'),
    },
    {
        title: t('auth.welcome.principles.items.rhythm.title'),
        description: t('auth.welcome.principles.items.rhythm.description'),
    },
]);

const publicStats = computed(() => [
    {
        label: t('auth.welcome.hero.stats.readiness.label'),
        value: t('auth.welcome.hero.stats.readiness.value'),
        note: t('auth.welcome.hero.stats.readiness.note'),
    },
    {
        label: t('auth.welcome.hero.stats.structure.label'),
        value: t('auth.welcome.hero.stats.structure.value'),
        note: t('auth.welcome.hero.stats.structure.note'),
    },
    {
        label: t('auth.welcome.hero.stats.visibility.label'),
        value: t('auth.welcome.hero.stats.visibility.value'),
        note: t('auth.welcome.hero.stats.visibility.note'),
    },
]);

const pricingHighlights = computed(() => [
    t('auth.welcome.pricing.items.households'),
    t('auth.welcome.pricing.items.recurring'),
    t('auth.welcome.pricing.items.visibility'),
]);

function trackRegisterClick(placement: string): void {
    trackPublicCta(page, 'cta_register_clicked', {
        placement,
        target: register().url,
    });
}

function trackFeaturesClick(placement: string): void {
    trackPublicCta(page, 'cta_features_clicked', {
        placement,
        target: '/features',
    });
}
</script>

<template>
    <PublicSeoHead />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader :can-register="canRegister" current-page="home" />

        <main class="pb-18">
            <section
                class="relative mx-auto w-full max-w-7xl px-6 pt-10 pb-18 sm:px-8 lg:pt-16 lg:pb-24"
            >
                <div
                    class="absolute inset-x-6 top-0 -z-10 h-full rounded-[2.5rem] bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.08),transparent_28%),radial-gradient(circle_at_top_right,rgba(234,90,71,0.08),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.96),rgba(255,252,248,0.94))] sm:inset-x-8"
                />

                <div
                    class="grid gap-14 lg:grid-cols-[minmax(0,1.1fr)_minmax(21rem,27rem)] lg:items-center"
                >
                    <div class="max-w-3xl space-y-9">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                        >
                            <Sparkles class="size-3.5 text-[#ea5a47]" />
                            {{ t('auth.welcome.hero.eyebrow') }}
                        </div>

                        <div class="space-y-5">
                            <h1
                                class="max-w-4xl text-[2.35rem] leading-[0.96] font-semibold tracking-[-0.04em] text-slate-950 sm:text-[3.05rem] lg:text-[4.1rem]"
                            >
                                {{ t('auth.welcome.hero.title') }}
                            </h1>
                            <p
                                class="max-w-2xl text-lg leading-8 text-slate-600"
                            >
                                {{ t('auth.welcome.hero.description') }}
                            </p>
                        </div>

                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center"
                        >
                            <Link
                                :href="
                                    $page.props.auth.user
                                        ? dashboard()
                                        : register()
                                "
                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#ea5a47] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                                @click="trackRegisterClick('home_hero_primary')"
                            >
                                {{
                                    $page.props.auth.user
                                        ? t('auth.welcome.actions.dashboard')
                                        : t('auth.welcome.nav.registerFree')
                                }}
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                href="/features"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                                @click="
                                    trackFeaturesClick('home_hero_secondary')
                                "
                            >
                                {{ t('auth.welcome.actions.discoverFeatures') }}
                                <ChevronRight class="size-4" />
                            </Link>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-x-5 gap-y-3 text-sm text-slate-500"
                        >
                            <div class="inline-flex items-center gap-2">
                                <CheckCircle2 class="size-4 text-emerald-500" />
                                {{ t('auth.welcome.hero.meta.noCard') }}
                            </div>
                            <div class="inline-flex items-center gap-2">
                                <CheckCircle2 class="size-4 text-emerald-500" />
                                {{ t('auth.welcome.hero.meta.beta') }}
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <article
                                v-for="stat in publicStats"
                                :key="stat.label"
                                class="rounded-[1.75rem] border border-[#f0e7e1] bg-white p-5 shadow-[0_16px_44px_-36px_rgba(15,23,42,0.18)]"
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
                                    class="mt-2 text-sm leading-6 text-slate-600"
                                >
                                    {{ stat.note }}
                                </p>
                            </article>
                        </div>
                    </div>

                    <div
                        class="rounded-[2.25rem] border border-[#efe5df] bg-white p-5 shadow-[0_26px_60px_-40px_rgba(15,23,42,0.18)] sm:p-6"
                    >
                        <div
                            class="rounded-[1.75rem] border border-[#f4ece7] bg-[#fffaf7] p-5"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-950"
                                    >
                                        {{ t('auth.welcome.preview.title') }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-6 text-slate-600"
                                    >
                                        {{
                                            t(
                                                'auth.welcome.preview.description',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#fff1eb] text-[#ea5a47]"
                                >
                                    <Landmark class="size-5" />
                                </div>
                            </div>

                            <div class="mt-6 space-y-3">
                                <div
                                    class="flex items-center justify-between rounded-2xl border border-[#f1e7e1] bg-white px-4 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-950"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.preview.rows.cashflow.title',
                                                )
                                            }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'auth.welcome.preview.rows.cashflow.subtitle',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-sm font-semibold text-emerald-600"
                                    >
                                        +12%
                                    </span>
                                </div>

                                <div
                                    class="flex items-center justify-between rounded-2xl border border-[#f1e7e1] bg-white px-4 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-950"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.preview.rows.recurring.title',
                                                )
                                            }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'auth.welcome.preview.rows.recurring.subtitle',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-sm font-semibold text-slate-950"
                                    >
                                        9
                                    </span>
                                </div>

                                <div
                                    class="flex items-center justify-between rounded-2xl border border-[#f1e7e1] bg-white px-4 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-950"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.preview.rows.alerts.title',
                                                )
                                            }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'auth.welcome.preview.rows.alerts.subtitle',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-sm font-semibold text-amber-600"
                                    >
                                        2
                                    </span>
                                </div>
                            </div>

                            <div
                                class="mt-6 rounded-[1.5rem] border border-[#f4e4db] bg-white px-5 py-4"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm font-medium text-slate-950"
                                >
                                    <ShieldCheck
                                        class="size-4 text-emerald-500"
                                    />
                                    {{ t('auth.welcome.preview.banner.title') }}
                                </div>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-600"
                                >
                                    {{
                                        t(
                                            'auth.welcome.preview.banner.description',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="mx-auto w-full max-w-7xl space-y-18 px-6 sm:px-8">
                <PublicPageSection
                    id="funzionalita"
                    :eyebrow="t('auth.welcome.features.eyebrow')"
                    :title="t('auth.welcome.features.title')"
                    :description="t('auth.welcome.features.description')"
                    content-class="grid gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]"
                >
                    <article
                        class="relative overflow-hidden rounded-[2rem] border border-[#efe5df] bg-[linear-gradient(180deg,#ffffff_0%,#fff7f2_100%)] p-6 shadow-[0_24px_60px_-44px_rgba(15,23,42,0.18)] sm:p-7"
                    >
                        <div
                            class="absolute -top-16 right-0 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(234,90,71,0.16),transparent_68%)]"
                        />
                        <div
                            class="absolute -bottom-16 left-0 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(245,158,11,0.12),transparent_68%)]"
                        />

                        <div class="relative space-y-6">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-white/90 px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                            >
                                <LayoutTemplate class="size-3.5" />
                                {{ featureCards[0].title }}
                            </div>

                            <div class="max-w-2xl space-y-3">
                                <h3
                                    class="text-[1.65rem] leading-tight font-semibold tracking-tight text-slate-950 sm:text-[1.9rem]"
                                >
                                    {{
                                        t(
                                            'auth.welcome.features.showcase.title',
                                        )
                                    }}
                                </h3>
                                <p
                                    class="text-sm leading-7 text-slate-600 sm:text-base"
                                >
                                    {{ featureCards[0].description }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <article
                                    v-for="stat in publicStats"
                                    :key="`${stat.label}-feature`"
                                    class="rounded-[1.5rem] border border-[#f1e6de] bg-white/92 p-4 shadow-[0_16px_40px_-32px_rgba(15,23,42,0.18)]"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase"
                                    >
                                        {{ stat.label }}
                                    </p>
                                    <p
                                        class="mt-2 text-base font-semibold text-slate-950"
                                    >
                                        {{ stat.value }}
                                    </p>
                                    <p
                                        class="mt-2 text-xs leading-6 text-slate-600"
                                    >
                                        {{ stat.note }}
                                    </p>
                                </article>
                            </div>
                        </div>
                    </article>

                    <div class="grid gap-5">
                        <PublicFeatureCard
                            v-for="feature in featureCards.slice(1)"
                            :key="feature.title"
                            :icon="feature.icon"
                            :title="feature.title"
                            :description="feature.description"
                        />
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    id="prezzi"
                    :eyebrow="t('auth.welcome.principles.eyebrow')"
                    :title="t('auth.welcome.pricing.title')"
                    :description="t('auth.welcome.pricing.description')"
                    content-class="grid gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]"
                >
                    <article
                        class="relative overflow-hidden rounded-[2rem] border border-[#efe5df] bg-[linear-gradient(180deg,#ffffff_0%,#fff6f0_100%)] p-7 shadow-[0_22px_58px_-40px_rgba(15,23,42,0.16)]"
                    >
                        <div
                            class="absolute inset-x-0 top-0 h-28 bg-[radial-gradient(circle_at_top,rgba(234,90,71,0.15),transparent_72%)]"
                        />
                        <p
                            class="relative text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                        >
                            {{ t('auth.welcome.pricing.plan.label') }}
                        </p>
                        <h3
                            class="relative mt-4 text-2xl font-semibold tracking-tight text-slate-950"
                        >
                            {{ t('auth.welcome.pricing.plan.title') }}
                        </h3>
                        <p
                            class="relative mt-2 text-sm leading-7 text-slate-600"
                        >
                            {{ t('auth.welcome.pricing.plan.description') }}
                        </p>
                        <div class="relative mt-8 flex items-end gap-2">
                            <span
                                class="text-4xl font-semibold tracking-tight text-slate-950"
                            >
                                {{ t('auth.welcome.pricing.plan.price') }}
                            </span>
                            <span
                                v-if="t('auth.welcome.pricing.plan.period')"
                                class="pb-1 text-sm text-slate-500"
                            >
                                {{ t('auth.welcome.pricing.plan.period') }}
                            </span>
                        </div>

                        <ul class="relative mt-6 grid gap-3">
                            <li
                                v-for="item in pricingHighlights"
                                :key="`${item}-primary`"
                                class="flex items-start gap-3 rounded-2xl border border-[#f1e6de] bg-white/88 px-4 py-3"
                            >
                                <CheckCircle2
                                    class="mt-0.5 size-4.5 shrink-0 text-emerald-500"
                                />
                                <span class="text-sm leading-6 text-slate-700">
                                    {{ item }}
                                </span>
                            </li>
                        </ul>

                        <Link
                            v-if="canRegister && !$page.props.auth.user"
                            :href="register()"
                            class="relative mt-7 inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                        >
                            {{ t('auth.welcome.nav.registerFree') }}
                        </Link>
                    </article>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <article
                            class="rounded-[1.75rem] border border-[#f1e7e1] bg-white p-6 shadow-[0_18px_40px_-34px_rgba(15,23,42,0.14)] sm:col-span-2"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-[#ea5a47]"
                            >
                                <HeartHandshake class="size-4" />
                            </div>
                            <p
                                class="mt-4 text-lg font-semibold tracking-tight text-slate-950"
                            >
                                {{ t('auth.welcome.pricing.support.title') }}
                            </p>
                            <p class="mt-2 text-sm leading-7 text-slate-600">
                                {{
                                    t(
                                        'auth.welcome.pricing.support.description',
                                    )
                                }}
                            </p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <article
                                    v-for="principle in principles"
                                    :key="`${principle.title}-support`"
                                    class="rounded-2xl border border-[#f2e8e1] bg-[#fffaf7] px-4 py-4"
                                >
                                    <p
                                        class="text-sm font-semibold text-slate-950"
                                    >
                                        {{ principle.title }}
                                    </p>
                                    <p
                                        class="mt-2 text-xs leading-6 text-slate-600"
                                    >
                                        {{ principle.description }}
                                    </p>
                                </article>
                            </div>
                        </article>

                        <article
                            class="rounded-[1.75rem] border border-[#f1e7e1] bg-[#fffaf7] p-6"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-[#ea5a47]"
                            >
                                <ReceiptText class="size-4" />
                            </div>
                            <h3
                                class="mt-4 text-lg font-semibold tracking-tight text-slate-950"
                            >
                                {{ t('auth.welcome.pricing.items.households') }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">
                                {{
                                    t(
                                        'auth.welcome.pricing.items.householdsDescription',
                                    )
                                }}
                            </p>
                        </article>

                        <article
                            class="rounded-[1.75rem] border border-[#f1e7e1] bg-[#fffaf7] p-6"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-[#ea5a47]"
                            >
                                <RefreshCcw class="size-4" />
                            </div>
                            <h3
                                class="mt-4 text-lg font-semibold tracking-tight text-slate-950"
                            >
                                {{ t('auth.welcome.pricing.items.recurring') }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">
                                {{
                                    t(
                                        'auth.welcome.pricing.items.recurringDescription',
                                    )
                                }}
                            </p>
                        </article>
                    </div>
                </PublicPageSection>

                <PublicPageSection class="pb-4">
                    <div
                        class="relative overflow-hidden rounded-[2rem] border border-[#f0e5de] bg-[linear-gradient(180deg,#fff7f2_0%,#fff1ea_100%)] px-6 py-8 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.14)] sm:px-8 sm:py-10"
                    >
                        <div
                            class="absolute right-0 bottom-0 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(234,90,71,0.14),transparent_68%)]"
                        />
                        <div
                            class="relative flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <div class="max-w-2xl space-y-4">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.2em] text-[#b65642] uppercase"
                                >
                                    {{ t('auth.welcome.cta.eyebrow') }}
                                </p>
                                <h2
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl"
                                >
                                    {{ t('auth.welcome.cta.title') }}
                                </h2>
                                <p
                                    class="text-sm leading-7 text-slate-600 sm:text-base"
                                >
                                    {{ t('auth.welcome.cta.description') }}
                                </p>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div
                                        class="rounded-2xl border border-[#f1e3db] bg-white/82 px-4 py-3"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.pricing.plan.price',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-6 text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.pricing.items.households',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-2xl border border-[#f1e3db] bg-white/82 px-4 py-3"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.features.cards.recurring.title',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-6 text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.pricing.items.recurringDescription',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-2xl border border-[#f1e3db] bg-white/82 px-4 py-3"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.pricing.support.title',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-6 text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'auth.welcome.pricing.items.visibility',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <Link
                                    :href="
                                        $page.props.auth.user
                                            ? dashboard()
                                            : register()
                                    "
                                    class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                                >
                                    {{
                                        $page.props.auth.user
                                            ? t(
                                                  'auth.welcome.actions.dashboard',
                                              )
                                            : t('auth.welcome.nav.registerFree')
                                    }}
                                </Link>
                                <Link
                                    href="/pricing"
                                    class="inline-flex items-center justify-center rounded-2xl border border-[#e8d8d0] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#dcc8be] hover:text-slate-950"
                                >
                                    {{ t('auth.welcome.actions.viewPricing') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </PublicPageSection>
            </div>
        </main>

        <PublicSiteFooter :can-register="canRegister" />
        <PublicCookieConsent />
    </div>
</template>
