<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheckBig,
    CreditCard,
    LayoutDashboard,
    RefreshCcw,
    ReceiptText,
    ShieldCheck,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { customersContent } from '@/i18n/customers-content';
import { aboutMe, features, pricing, register } from '@/routes';

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
    locale.value === 'it' ? customersContent.it : customersContent.en,
);

const heroIcons = [LayoutDashboard, Wallet, RefreshCcw];
const audienceIcons = [
    ReceiptText,
    CreditCard,
    Users,
    LayoutDashboard,
    ShieldCheck,
];
const scenarioIcons = [ReceiptText, Wallet, Users, RefreshCcw, CreditCard];
</script>

<template>
    <Head :title="content.headTitle" />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader
            :can-register="canRegister"
            current-page="customers"
        />

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
                                {{ content.hero.registerLabel }}
                            </Link>
                            <Link
                                :href="features()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.hero.featuresLabel }}
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
                    :eyebrow="content.audience.eyebrow"
                    :title="content.audience.title"
                    :description="content.audience.description"
                >
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="(item, index) in content.audience.items"
                            :key="item.title"
                            class="rounded-[2rem] border border-[#efe4db] bg-white p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.16)]"
                        >
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                            >
                                <component
                                    :is="audienceIcons[index]"
                                    class="size-5"
                                />
                            </div>
                            <h2
                                class="mt-5 text-lg font-semibold tracking-tight text-slate-950"
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
                    :eyebrow="content.scenarios.eyebrow"
                    :title="content.scenarios.title"
                    :description="content.scenarios.description"
                >
                    <div class="grid gap-6">
                        <article
                            v-for="(item, index) in content.scenarios.items"
                            :key="item.title"
                            class="grid gap-6 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:items-start"
                        >
                            <div class="space-y-4">
                                <div
                                    class="flex size-11 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                                >
                                    <component
                                        :is="scenarioIcons[index]"
                                        class="size-5"
                                    />
                                </div>
                                <div>
                                    <h2
                                        class="text-xl font-semibold tracking-tight text-slate-950"
                                    >
                                        {{ item.title }}
                                    </h2>
                                    <p
                                        class="mt-3 text-sm leading-7 text-slate-600"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <div
                                    v-for="point in item.points"
                                    :key="point"
                                    class="flex gap-3 rounded-2xl border border-[#f2e8e1] bg-white px-4 py-4"
                                >
                                    <CircleCheckBig
                                        class="mt-0.5 size-5 shrink-0 text-[#ea5a47]"
                                    />
                                    <p class="text-sm leading-7 text-slate-700">
                                        {{ point }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.useful.eyebrow"
                    :title="content.useful.title"
                    :description="content.useful.description"
                >
                    <div
                        class="grid gap-4 rounded-[2rem] border border-[#efe4db] bg-white p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.16)]"
                    >
                        <div
                            v-for="item in content.useful.items"
                            :key="item"
                            class="flex gap-3 rounded-2xl border border-[#f2e8e1] bg-[#fffaf7] px-4 py-4"
                        >
                            <CircleCheckBig
                                class="mt-0.5 size-5 shrink-0 text-[#ea5a47]"
                            />
                            <p class="text-sm leading-7 text-slate-700">
                                {{ item }}
                            </p>
                        </div>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.beta.eyebrow"
                    :title="content.beta.title"
                    :description="content.beta.description"
                >
                    <div
                        class="grid gap-4 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] md:grid-cols-3"
                    >
                        <article
                            v-for="item in content.beta.points"
                            :key="item"
                            class="rounded-[1.75rem] border border-[#f2e8e1] bg-white p-5"
                        >
                            <p class="text-sm leading-7 text-slate-700">
                                {{ item }}
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
                                {{ content.cta.registerLabel }}
                            </Link>
                            <Link
                                :href="pricing()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.cta.pricingLabel }}
                            </Link>
                            <Link
                                :href="aboutMe()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.cta.aboutLabel }}
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
