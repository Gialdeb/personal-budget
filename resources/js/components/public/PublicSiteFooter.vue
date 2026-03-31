<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Globe, Github, Linkedin } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PublicLocaleSwitcher from '@/components/public/PublicLocaleSwitcher.vue';
import { openCookieConsentPreferences } from '@/composables/useCookieConsent';
import { publicProfileLinks } from '@/config/public-profile';
import { trackPublicCta } from '@/lib/analytics';
import { dashboard, features, login, pricing, register } from '@/routes';
import { index as changelogIndex } from '@/routes/changelog';

defineProps<{
    canRegister: boolean;
}>();

const { t } = useI18n();
const page = usePage();
const launchYear = 2026;
const currentYear = new Date().getFullYear();
const copyrightLabel = computed(() => {
    const yearLabel =
        currentYear > launchYear
            ? `${launchYear}-${currentYear}`
            : `${launchYear}`;

    return `© Soamco Budget ${yearLabel}. Made with ♡ in Italy.`;
});

const footerGroups = computed(() => [
    {
        title: t('auth.welcome.footer.groups.features.title'),
        links: [
            {
                label: t(
                    'auth.welcome.footer.groups.features.links.howItWorks',
                ),
                href: features(),
            },
            {
                label: t('auth.welcome.footer.groups.features.links.pricing'),
                href: pricing(),
            },
        ],
    },
    {
        title: t('auth.welcome.footer.groups.resources.title'),
        links: [
            {
                label: t('auth.welcome.footer.groups.resources.links.apps'),
                href: '/download-app',
            },
            {
                label: t('auth.welcome.footer.groups.resources.links.stories'),
                href: '/customers',
            },
        ],
    },
    {
        title: t('auth.welcome.footer.groups.company.title'),
        links: [
            {
                label: t('auth.welcome.footer.groups.company.links.about'),
                href: '/about-me',
            },
        ],
    },
]);

const legalLinks = computed(() => [
    {
        label: t('auth.welcome.footer.legal.privacy'),
        type: 'link',
        href: '/privacy',
    },
    {
        label: t('auth.welcome.footer.legal.terms'),
        type: 'link',
        href: '/terms-of-service',
    },
    {
        label: t('auth.welcome.footer.legal.cookies'),
        type: 'button',
    },
]);

const socialLinks = [
    {
        label: 'Website',
        href: publicProfileLinks.website,
        icon: Globe,
    },
    {
        label: 'LinkedIn',
        href: publicProfileLinks.linkedin,
        icon: Linkedin,
    },
    {
        label: 'GitHub',
        href: publicProfileLinks.github,
        icon: Github,
    },
];

function trackFooterLoginClick(): void {
    trackPublicCta(page, 'cta_login_clicked', {
        placement: 'footer',
        target: login().url,
    });
}

function trackFooterRegisterClick(): void {
    trackPublicCta(page, 'cta_register_clicked', {
        placement: 'footer',
        target: register().url,
    });
}

function trackFooterChangelogClick(): void {
    trackPublicCta(page, 'changelog_cta_clicked', {
        placement: 'footer',
        target: changelogIndex().url,
    });
}
</script>

<template>
    <footer class="mt-12 border-t border-[#e9ddd4] bg-[#fcf5ef] py-12">
        <div class="mx-auto w-full max-w-7xl px-6 sm:px-8">
            <div class="border-t border-[#e4d7ce] pt-10">
                <div
                    class="grid gap-8 lg:grid-cols-[minmax(16rem,1.2fr)_repeat(3,minmax(9rem,0.7fr))_auto] lg:gap-10"
                >
                    <div class="max-w-sm space-y-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-gradient-to-br from-[#ea5a47] via-[#ef6c5b] to-[#f28c6e] text-white shadow-[0_16px_35px_-22px_rgba(234,90,71,0.45)]"
                            >
                                <AppLogoIcon class="size-5 text-white" />
                            </div>
                            <p class="text-sm font-semibold text-slate-950">
                                {{ t('app.name') }}
                            </p>
                        </div>
                        <p class="text-[1.05rem] leading-8 text-slate-700">
                            {{ t('auth.welcome.footer.description') }}
                        </p>
                    </div>

                    <div
                        v-for="group in footerGroups"
                        :key="group.title"
                        class="space-y-4 border-t border-[#e4d7ce] pt-6 lg:border-t-0 lg:pt-0"
                    >
                        <p
                            class="text-sm font-semibold tracking-tight text-slate-950"
                        >
                            {{ group.title }}
                        </p>
                        <ul class="space-y-3">
                            <li
                                v-for="link in group.links"
                                :key="link.label"
                                class="text-sm text-slate-700"
                            >
                                <Link
                                    v-if="link.href"
                                    :href="link.href"
                                    class="transition hover:text-slate-950"
                                >
                                    {{ link.label }}
                                </Link>
                                <span
                                    v-else
                                    class="transition hover:text-slate-950"
                                >
                                    {{ link.label }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div
                        class="border-t border-[#e4d7ce] pt-6 lg:flex lg:flex-col lg:items-end lg:gap-4 lg:border-t-0 lg:pt-0"
                    >
                        <div
                            class="flex flex-row gap-4 text-slate-800 lg:flex-col"
                        >
                            <a
                                v-for="item in socialLinks"
                                :key="item.label"
                                :href="item.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                :aria-label="item.label"
                                class="flex h-11 w-11 items-center justify-center rounded-full border border-[#e6d9d0] bg-white text-[#ea5a47] transition hover:border-[#dcc8be] hover:text-[#de4f3d]"
                            >
                                <component :is="item.icon" class="size-5" />
                            </a>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-12 flex flex-col gap-4 border-t border-[#e4d7ce] pt-6 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div
                        class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-500"
                    >
                        <template v-for="item in legalLinks" :key="item.label">
                            <a
                                v-if="item.type === 'link'"
                                :href="item.href"
                                class="transition hover:text-slate-700"
                            >
                                {{ item.label }}
                            </a>
                            <button
                                v-else-if="item.type === 'button'"
                                type="button"
                                class="transition hover:text-slate-700"
                                @click="openCookieConsentPreferences"
                            >
                                {{ item.label }}
                            </button>
                            <span
                                v-else
                                class="transition hover:text-slate-700"
                            >
                                {{ item.label }}
                            </span>
                        </template>
                        <span class="text-slate-400">
                            {{ copyrightLabel }}
                        </span>
                    </div>

                    <div
                        class="grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center"
                    >
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : login()
                            "
                            class="rounded-2xl border border-[#e8ddd6] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-[#dccdc4] hover:text-slate-950"
                            @click="trackFooterLoginClick"
                        >
                            {{
                                $page.props.auth.user
                                    ? t('auth.welcome.actions.dashboard')
                                    : t('auth.welcome.nav.login')
                            }}
                        </Link>
                        <Link
                            v-if="canRegister && !$page.props.auth.user"
                            :href="register()"
                            class="rounded-2xl bg-[#ea5a47] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                            @click="trackFooterRegisterClick"
                        >
                            {{ t('auth.welcome.nav.registerFree') }}
                        </Link>
                        <Link
                            :href="changelogIndex()"
                            class="rounded-2xl border border-[#e8ddd6] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-[#dccdc4] hover:text-slate-950"
                            @click="trackFooterChangelogClick"
                        >
                            {{ t('auth.welcome.actions.changelog') }}
                        </Link>
                        <div class="sm:col-span-2 lg:col-span-1">
                            <PublicLocaleSwitcher />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>
