<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheckBig,
    Gift,
    HeartHandshake,
    ShieldCheck,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { pricingContent } from '@/i18n/pricing-content';
import { features, register } from '@/routes';
import { index as support } from '@/routes/support';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const { locale } = useI18n();

const content = computed(() =>
    locale.value === 'it' ? pricingContent.it : pricingContent.en,
);

const heroIcons = [CircleCheckBig, Wrench, HeartHandshake];
const supportIcons = [ShieldCheck, Wrench, HeartHandshake];
</script>

<template>
    <Head :title="content.headTitle" />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader :can-register="canRegister" current-page="pricing" />

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
                            >
                                {{ content.hero.primaryLabel }}
                            </Link>
                            <Link
                                :href="features()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.hero.secondaryLabel }}
                            </Link>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 rounded-[2rem] border border-[#efe4db] bg-white/88 p-5 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] backdrop-blur"
                    >
                        <div
                            v-for="(item, index) in content.hero.highlights"
                            :key="item"
                            class="flex items-center gap-3 rounded-2xl border border-[#f3e7df] bg-[#fffaf6] px-4 py-3"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                            >
                                <component
                                    :is="heroIcons[index]"
                                    class="size-4.5"
                                />
                            </div>
                            <p
                                class="text-sm leading-6 font-medium text-slate-700"
                            >
                                {{ item }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-16 px-6 sm:px-8 lg:gap-18"
            >
                <PublicPageSection
                    :eyebrow="content.free.eyebrow"
                    :title="content.free.title"
                    :description="content.free.description"
                >
                    <div
                        class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]"
                    >
                        <article
                            class="rounded-[2rem] border border-[#efe4db] bg-white p-7 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)]"
                        >
                            <div
                                class="inline-flex items-center rounded-full border border-[#d7ebe5] bg-[#eff8f5] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#0f766e] uppercase"
                            >
                                {{ content.free.badge }}
                            </div>
                            <ul class="mt-6 grid gap-4">
                                <li
                                    v-for="point in content.free.points"
                                    :key="point"
                                    class="flex gap-3 rounded-2xl border border-[#f2e8e1] bg-[#fffaf7] px-4 py-4"
                                >
                                    <CircleCheckBig
                                        class="mt-0.5 size-5 shrink-0 text-[#ea5a47]"
                                    />
                                    <span
                                        class="text-sm leading-7 text-slate-700 sm:text-[0.95rem]"
                                    >
                                        {{ point }}
                                    </span>
                                </li>
                            </ul>
                        </article>

                        <aside
                            class="relative overflow-hidden rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-7 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)]"
                        >
                            <div
                                class="absolute top-0 right-0 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(234,90,71,0.16),transparent_68%)]"
                            />

                            <div class="relative space-y-5">
                                <div
                                    class="inline-flex items-center rounded-full border border-[#f2dfd8] bg-white/90 px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                                >
                                    Visione sostenibile
                                </div>

                                <p
                                    class="max-w-sm text-[0.95rem] leading-8 text-slate-600"
                                >
                                    {{ content.free.note }}
                                </p>

                                <div class="grid gap-3">
                                    <article
                                        class="rounded-2xl border border-[#f2e8e1] bg-white px-4 py-4"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950"
                                        >
                                            Accesso semplice
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-6 text-slate-500"
                                        >
                                            Nessun piano artificiale: inizi
                                            subito e capisci se il prodotto ti è
                                            utile.
                                        </p>
                                    </article>

                                    <article
                                        class="rounded-2xl border border-[#f2e8e1] bg-white px-4 py-4"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950"
                                                >
                                                    Crescita sostenuta
                                                </p>
                                                <p
                                                    class="mt-1 text-xs leading-6 text-slate-500"
                                                >
                                                    Se il progetto ti aiuta
                                                    davvero, puoi sostenerlo nel
                                                    tempo con una donazione
                                                    facoltativa.
                                                </p>
                                            </div>
                                            <HeartHandshake
                                                class="size-5 shrink-0 text-[#ea5a47]"
                                            />
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </aside>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.why.eyebrow"
                    :title="content.why.title"
                    :description="content.why.description"
                >
                    <div class="grid gap-5 lg:grid-cols-3">
                        <article
                            v-for="item in content.why.items"
                            :key="item.title"
                            class="rounded-[2rem] border border-[#efe4db] bg-white p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.16)]"
                        >
                            <h3
                                class="text-lg font-semibold tracking-tight text-slate-950"
                            >
                                {{ item.title }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">
                                {{ item.description }}
                            </p>
                        </article>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.support.eyebrow"
                    :title="content.support.title"
                    :description="content.support.description"
                >
                    <div
                        class="grid gap-6 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-7 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.95fr)]"
                    >
                        <div class="space-y-4">
                            <div
                                v-for="(point, index) in content.support.points"
                                :key="point"
                                class="flex gap-3 rounded-2xl border border-[#f2e8e1] bg-white px-4 py-4"
                            >
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                                >
                                    <component
                                        :is="supportIcons[index]"
                                        class="size-4.5"
                                    />
                                </div>
                                <p class="text-sm leading-7 text-slate-700">
                                    {{ point }}
                                </p>
                            </div>
                        </div>

                        <aside
                            class="rounded-[1.75rem] border border-dashed border-[#ddc9bc] bg-white/80 p-6"
                        >
                            <div class="space-y-4">
                                <button
                                    v-if="!$page.props.auth.user"
                                    type="button"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white opacity-90"
                                    @click="$inertia.visit(register())"
                                >
                                    <Gift class="size-4" />
                                    {{ content.support.primaryLabel }}
                                </button>
                                <Link
                                    v-else
                                    :href="support()"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                                >
                                    <Gift class="size-4" />
                                    {{ content.support.primaryLabel }}
                                </Link>
                                <Link
                                    :href="
                                        $page.props.auth.user
                                            ? support()
                                            : register()
                                    "
                                    class="inline-flex w-full items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                                >
                                    {{ content.support.secondaryLabel }}
                                </Link>
                                <p class="text-sm leading-7 text-slate-500">
                                    {{ content.support.footnote }}
                                </p>
                            </div>
                        </aside>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.faq.eyebrow"
                    :title="content.faq.title"
                    :description="content.faq.description"
                >
                    <div class="grid gap-5 lg:grid-cols-3">
                        <article
                            v-for="item in content.faq.items"
                            :key="item.question"
                            class="rounded-[2rem] border border-[#efe4db] bg-white p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.16)]"
                        >
                            <h3
                                class="text-base font-semibold tracking-tight text-slate-950"
                            >
                                {{ item.question }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">
                                {{ item.answer }}
                            </p>
                        </article>
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
                                {{ content.cta.description }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="canRegister && !$page.props.auth.user"
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                            >
                                {{ content.cta.primaryLabel }}
                            </Link>
                            <Link
                                :href="features()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.cta.secondaryLabel }}
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
