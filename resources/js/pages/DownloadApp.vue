<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    House,
    Smartphone,
    TabletSmartphone,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicPageSection from '@/components/public/PublicPageSection.vue';
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue';
import PublicSiteHeader from '@/components/public/PublicSiteHeader.vue';
import { usePwa } from '@/composables/usePwa';
import { downloadAppContent } from '@/i18n/download-app-content';
import { trackPublicCta } from '@/lib/analytics';
import { resolvePublicDownloadImage } from '@/lib/public-feature-assets';
import { features, pricing, register } from '@/routes';

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
    locale.value === 'it' ? downloadAppContent.it : downloadAppContent.en,
);

const benefitIcons = [ArrowDownToLine, Smartphone, House, TabletSmartphone];
const androidImage = computed(() =>
    resolvePublicDownloadImage(locale.value, 'android-install'),
);
const iosImage = computed(() =>
    resolvePublicDownloadImage(locale.value, 'ios-install'),
);
const {
    installState,
    installDiagnostic,
    isLaunchingInstallPrompt,
    launchInstall,
} = usePwa();
const isDev = import.meta.env.DEV;

const installHelpHref = computed(() =>
    installState.value === 'ios' ? '#ios' : '#android',
);

const installCtaLabel = computed(() => {
    if (isLaunchingInstallPrompt.value) {
        return content.value.cta.installingLabel;
    }

    switch (installState.value) {
        case 'installed':
            return content.value.cta.installedLabel;
        case 'ios':
            return content.value.cta.iosLabel;
        case 'dismissed':
            return content.value.cta.dismissedLabel;
        case 'unsupported':
            return content.value.cta.unavailableLabel;
        default:
            return content.value.cta.installLabel;
    }
});

const installHint = computed(() => {
    switch (installState.value) {
        case 'ios':
            return content.value.cta.iosHint;
        case 'dismissed':
            return content.value.cta.dismissedHint;
        case 'unsupported':
            return content.value.cta.unavailableHint;
        default:
            return content.value.cta.description;
    }
});

async function handleInstallClick(event: MouseEvent): Promise<void> {
    trackPublicCta(page, 'download_app_clicked', {
        placement: 'download_app_cta',
        target: installState.value,
    });

    if (isDev) {
        console.debug(
            `[PWA install] CTA clicked on /download-app. trusted=${event.isTrusted}. state=${installState.value}.`,
        );
    }

    const result = await launchInstall();

    if (result === 'ios' || result === 'dismissed' || result === 'unsupported') {
        window.location.hash = installHelpHref.value;
    }
}
</script>

<template>
    <Head :title="content.headTitle" />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
        <PublicSiteHeader
            :can-register="canRegister"
            current-page="download-app"
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
                            <a
                                href="#android"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                                @click="
                                    trackPublicCta(page, 'download_app_clicked', {
                                        placement: 'download_app_hero_android',
                                        target: '#android',
                                    });
                                "
                            >
                                {{ content.hero.androidLabel }}
                            </a>
                            <a
                                href="#ios"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                                @click="
                                    trackPublicCta(page, 'download_app_clicked', {
                                        placement: 'download_app_hero_ios',
                                        target: '#ios',
                                    });
                                "
                            >
                                {{ content.hero.iosLabel }}
                            </a>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 rounded-[2rem] border border-[#efe4db] bg-white/88 p-5 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] backdrop-blur"
                    >
                        <div
                            v-for="(item, index) in content.benefits.items"
                            :key="item"
                            class="flex items-center gap-3 rounded-2xl border border-[#f3e7df] bg-[#fffaf6] px-4 py-3"
                        >
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-[#fff1ea] text-[#ea5a47]"
                            >
                                <component
                                    :is="benefitIcons[index]"
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
                    :eyebrow="content.benefits.eyebrow"
                    :title="content.benefits.title"
                    :description="content.benefits.description"
                />

                <PublicPageSection
                    id="android"
                    :eyebrow="content.android.eyebrow"
                    :title="content.android.title"
                    :description="content.android.description"
                >
                    <div
                        class="grid gap-6 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:items-start"
                    >
                        <img
                            :src="androidImage"
                            :alt="content.android.imageAlt"
                            class="w-full rounded-[1.75rem] border border-[#f2e8e1] bg-white"
                        />
                        <div class="grid gap-4">
                            <article
                                v-for="(step, index) in content.android.steps"
                                :key="step.title"
                                class="flex gap-4 rounded-[1.5rem] border border-[#f2e8e1] bg-white px-4 py-4"
                            >
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-[#fff1ea] text-sm font-semibold text-[#ea5a47]"
                                >
                                    {{ index + 1 }}
                                </div>
                                <div>
                                    <h2
                                        class="text-base font-semibold tracking-tight text-slate-950"
                                    >
                                        {{ step.title }}
                                    </h2>
                                    <p
                                        class="mt-2 text-sm leading-7 text-slate-600"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </article>
                        </div>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    id="ios"
                    :eyebrow="content.ios.eyebrow"
                    :title="content.ios.title"
                    :description="content.ios.description"
                >
                    <div
                        class="grid gap-6 rounded-[2rem] border border-[#efe4db] bg-[linear-gradient(180deg,#ffffff_0%,#fff8f4_100%)] p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.18)] lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:items-start"
                    >
                        <img
                            :src="iosImage"
                            :alt="content.ios.imageAlt"
                            class="w-full rounded-[1.75rem] border border-[#f2e8e1] bg-white"
                        />
                        <div class="grid gap-4">
                            <article
                                v-for="(step, index) in content.ios.steps"
                                :key="step.title"
                                class="flex gap-4 rounded-[1.5rem] border border-[#f2e8e1] bg-white px-4 py-4"
                            >
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-[#fff1ea] text-sm font-semibold text-[#ea5a47]"
                                >
                                    {{ index + 1 }}
                                </div>
                                <div>
                                    <h2
                                        class="text-base font-semibold tracking-tight text-slate-950"
                                    >
                                        {{ step.title }}
                                    </h2>
                                    <p
                                        class="mt-2 text-sm leading-7 text-slate-600"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </article>
                        </div>
                    </div>
                </PublicPageSection>

                <PublicPageSection
                    :eyebrow="content.faq.eyebrow"
                    :title="content.faq.title"
                    :description="content.faq.description"
                >
                    <div class="grid gap-5 lg:grid-cols-2">
                        <article
                            v-for="item in content.faq.items"
                            :key="item.question"
                            class="rounded-[2rem] border border-[#efe4db] bg-white p-6 shadow-[0_26px_70px_-48px_rgba(15,23,42,0.16)]"
                        >
                            <h2
                                class="text-base font-semibold tracking-tight text-slate-950"
                            >
                                {{ item.question }}
                            </h2>
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
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d] disabled:cursor-not-allowed disabled:bg-[#d8c7bb] disabled:text-slate-600"
                                :disabled="installState === 'installed' || isLaunchingInstallPrompt"
                                @click="handleInstallClick"
                            >
                                {{ installCtaLabel }}
                            </button>
                            <Link
                                v-if="canRegister && !$page.props.auth.user"
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                                @click="
                                    trackPublicCta(page, 'cta_register_clicked', {
                                        placement: 'download_app_cta_register',
                                        target: register().url,
                                    });
                                "
                            >
                                {{ content.cta.registerLabel }}
                            </Link>
                            <Link
                                :href="features()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.cta.featuresLabel }}
                            </Link>
                            <Link
                                :href="pricing()"
                                class="inline-flex items-center justify-center rounded-2xl border border-[#e7dad1] bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d8c7bb] hover:text-slate-950"
                            >
                                {{ content.cta.pricingLabel }}
                            </Link>
                        </div>
                    </div>
                    <p class="text-sm leading-7 text-slate-600">
                        {{ installHint }}
                    </p>
                    <p
                        v-if="isDev"
                        class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 font-mono text-xs leading-6 text-slate-600"
                    >
                        {{ installDiagnostic }}
                    </p>
                </PublicPageSection>
            </div>
        </main>

        <PublicSiteFooter :can-register="canRegister" />
        <PublicCookieConsent />
    </div>
</template>
