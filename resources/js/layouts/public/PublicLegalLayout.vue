<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import PublicLocaleSwitcher from '@/components/public/PublicLocaleSwitcher.vue';
import PublicSeoHead from '@/components/public/PublicSeoHead.vue';
import TawkToWidget from '@/components/public/TawkToWidget.vue';
import { login, register } from '@/routes';

const props = defineProps<{
    title: string;
    description: string;
    eyebrow: string;
    pageTitle: string;
}>();

const { t } = useI18n();
const page = usePage();
const isMobileMenuOpen = ref(false);
const tawkToConfig = computed(
    () => page.props.publicIntegrations?.tawkTo ?? null,
);
</script>

<template>
    <PublicSeoHead />
    <TawkToWidget :config="tawkToConfig" />

    <div class="min-h-screen bg-[#fffdfb] text-slate-950">
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
                        <p
                            class="truncate text-sm font-semibold tracking-tight"
                        >
                            {{ t('app.name') }}
                        </p>
                        <p class="truncate text-xs text-slate-500">
                            {{ props.eyebrow }}
                        </p>
                    </div>
                </Link>

                <nav class="hidden items-center gap-4 lg:flex">
                    <Link
                        href="/"
                        class="text-sm font-medium text-slate-600 transition hover:text-slate-950"
                    >
                        {{ t('legal.common.backHome') }}
                    </Link>
                    <Link
                        :href="login()"
                        class="text-sm font-medium text-slate-700 transition hover:text-slate-950"
                    >
                        {{ t('legal.common.login') }}
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-flex items-center rounded-2xl bg-[#ea5a47] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                    >
                        {{ t('legal.common.register') }}
                    </Link>
                    <PublicLocaleSwitcher />
                </nav>

                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm lg:hidden"
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
                    class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-4 sm:px-8"
                >
                    <Link href="/" class="text-sm font-medium text-slate-700">
                        {{ t('legal.common.backHome') }}
                    </Link>
                    <Link
                        :href="login()"
                        class="text-sm font-medium text-slate-700"
                    >
                        {{ t('legal.common.login') }}
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-flex items-center justify-center rounded-2xl bg-[#ea5a47] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#de4f3d]"
                    >
                        {{ t('legal.common.register') }}
                    </Link>
                    <PublicLocaleSwitcher />
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl px-6 py-12 sm:px-8 lg:py-16">
            <section class="space-y-5">
                <p
                    class="text-[11px] font-semibold tracking-[0.18em] text-[#b65642] uppercase"
                >
                    {{ props.eyebrow }}
                </p>
                <h1
                    class="max-w-4xl text-4xl leading-none font-semibold tracking-[-0.04em] text-slate-950 sm:text-5xl"
                >
                    {{ props.title }}
                </h1>
                <p class="max-w-3xl text-base leading-8 text-slate-600">
                    {{ props.description }}
                </p>
                <p class="text-sm font-medium text-slate-500">
                    {{ t('legal.common.effectiveDateLabel') }}:
                    {{ t('legal.common.lastUpdated') }}
                </p>
            </section>

            <div class="mt-10">
                <slot />
            </div>
        </main>

        <PublicCookieConsent />
    </div>
</template>
