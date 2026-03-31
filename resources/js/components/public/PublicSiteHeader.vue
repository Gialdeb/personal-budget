<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { trackPublicCta } from '@/lib/analytics';
import { dashboard, login, register } from '@/routes';

defineProps<{
    canRegister?: boolean;
    currentPage?:
        | 'home'
        | 'features'
        | 'pricing'
        | 'changelog'
        | 'about-me'
        | 'customers'
        | 'download-app';
}>();

const { t } = useI18n();
const page = usePage();
const isMobileMenuOpen = ref(false);

const navItems = computed(() => [
    { key: 'home', label: t('auth.welcome.nav.home'), href: '/' },
    {
        key: 'features',
        label: t('auth.welcome.nav.features'),
        href: '/features',
    },
    { key: 'pricing', label: t('auth.welcome.nav.pricing'), href: '/pricing' },
]);

function trackLoginClick(placement: string): void {
    trackPublicCta(page, 'cta_login_clicked', {
        placement,
        target: login().url,
    });
}

function trackRegisterClick(placement: string): void {
    trackPublicCta(page, 'cta_register_clicked', {
        placement,
        target: register().url,
    });
}
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/92 backdrop-blur"
    >
        <div
            class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-6 py-5 sm:px-8"
        >
            <Link href="/" class="flex min-w-0 items-center gap-3">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-[1.1rem] bg-gradient-to-br from-[#ea5a47] via-[#ef6c5b] to-[#f28c6e] text-white shadow-[0_16px_35px_-20px_rgba(234,90,71,0.55)]"
                >
                    <AppLogoIcon class="size-5 text-white" />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="truncate text-sm font-semibold tracking-tight"
                        >
                            {{ t('app.name') }}
                        </span>
                    </div>
                    <p class="truncate text-xs text-slate-500">
                        {{ t('auth.welcome.nav.tagline') }}
                    </p>
                </div>
            </Link>

            <nav
                class="hidden items-center gap-8 lg:flex"
                aria-label="Public primary navigation"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    class="text-sm font-medium transition"
                    :class="
                        currentPage === item.key
                            ? 'text-slate-950'
                            : 'text-slate-600 hover:text-slate-950'
                    "
                >
                    {{ item.label }}
                </Link>

                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="text-sm font-medium text-slate-700 transition hover:text-slate-950"
                >
                    {{ t('auth.welcome.actions.dashboard') }}
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-sm font-medium text-slate-700 transition hover:text-slate-950"
                        @click="trackLoginClick('header_desktop')"
                    >
                        {{ t('auth.welcome.nav.login') }}
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="inline-flex items-center rounded-2xl bg-[#ea5a47] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                        @click="trackRegisterClick('header_desktop')"
                    >
                        {{ t('auth.welcome.nav.registerFree') }}
                    </Link>
                </template>
            </nav>

            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm lg:hidden"
                :aria-label="t('auth.welcome.nav.openMenu')"
                :aria-expanded="isMobileMenuOpen"
                @click="isMobileMenuOpen = !isMobileMenuOpen"
            >
                <Menu class="size-5" />
            </button>
        </div>

        <div
            v-if="isMobileMenuOpen"
            class="border-t border-slate-200/70 bg-white lg:hidden"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-4 sm:px-8"
            >
                <Link
                    v-for="item in navItems"
                    :key="`${item.key}-mobile`"
                    :href="item.href"
                    class="rounded-2xl px-4 py-3 text-sm font-medium transition"
                    :class="
                        currentPage === item.key
                            ? 'bg-[#fff3ee] text-slate-950'
                            : 'text-slate-700 hover:bg-slate-50'
                    "
                    @click="isMobileMenuOpen = false"
                >
                    {{ item.label }}
                </Link>

                <Link
                    v-if="!page.props.auth.user"
                    :href="login()"
                    class="rounded-2xl px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="
                        trackLoginClick('header_mobile');
                        isMobileMenuOpen = false;
                    "
                >
                    {{ t('auth.welcome.nav.login') }}
                </Link>

                <Link
                    v-if="canRegister && !page.props.auth.user"
                    :href="register()"
                    class="mt-2 inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                    @click="
                        trackRegisterClick('header_mobile');
                        isMobileMenuOpen = false;
                    "
                >
                    {{ t('auth.welcome.nav.registerFree') }}
                </Link>
            </div>
        </div>
    </header>
</template>
