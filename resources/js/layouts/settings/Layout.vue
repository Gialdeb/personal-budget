<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    ArrowLeft,
    Building2,
    CalendarRange,
    ChartCandlestick,
    CircleUserRound,
    FolderInput,
    FolderOutput,
    Landmark,
    Layers3,
    LifeBuoy,
    Network,
    Palette,
    Route,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAccounts } from '@/routes/accounts';
import { edit as editAppearance } from '@/routes/appearance/index';
import { edit as editBanks } from '@/routes/banks';
import { edit as editCategories } from '@/routes/categories';
import { edit as editExchangeRates } from '@/routes/exchange-rates';
import { edit as editExports } from '@/routes/exports';
import { index as imports } from '@/routes/imports';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security/index';
import { index as settingsIndex } from '@/routes/settings';
import { edit as editSharedCategories } from '@/routes/shared-categories';
import { index as supportIndex } from '@/routes/support';
import { edit as editTrackedItems } from '@/routes/tracked-items';
import { edit as editYears } from '@/routes/years';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const hasSharedCategories = computed(
    () => page.props.settingsNavigation?.has_shared_categories === true,
);
const importsEnabled = computed(
    () => page.props.features?.imports_enabled === true,
);
const currentUrl = computed(
    () =>
        new URL(
            String(page.url ?? '/'),
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ),
);
const mobileLauncherHref = computed(() =>
    settingsIndex(),
);
const isSettingsRoot = computed(() => currentUrl.value.pathname === '/settings');
const isMobileLauncher = computed(
    () =>
        isSettingsRoot.value ||
        currentUrl.value.searchParams.get('mobile') === 'launcher',
);
const isMobileViewport = useMediaQuery('(max-width: 767px)');
const showMobileLauncher = computed(
    () => isMobileViewport.value && isMobileLauncher.value,
);

type SettingsNavItem = NavItem & {
    summary: string;
    desktopIconClass?: string;
};

const sidebarNavItems = computed<SettingsNavItem[]>(() =>
    [
        {
            title: t('settings.sections.profile'),
            icon: CircleUserRound,
            href: editProfile(),
            summary: t('settings.summaries.profile'),
        },
        {
            title: t('settings.sections.categories'),
            href: editCategories(),
            icon: Layers3,
            summary: t('settings.summaries.categories'),
        },
        ...(hasSharedCategories.value
            ? [
                  {
                      title: t('settings.sections.sharedCategories'),
                      href: editSharedCategories(),
                      icon: Network,
                      summary: t('settings.summaries.sharedCategories'),
                  },
              ]
            : []),
        {
            title: t('settings.sections.trackedItems'),
            href: editTrackedItems(),
            icon: Route,
            summary: t('settings.summaries.trackedItems'),
        },
        {
            title: t('settings.sections.exchangeRates'),
            href: editExchangeRates(),
            icon: ChartCandlestick,
            summary: t('settings.summaries.exchangeRates'),
        },
        {
            title: t('settings.sections.support'),
            href: supportIndex(),
            icon: LifeBuoy,
            summary: t('settings.summaries.support'),
        },
        {
            title: t('settings.sections.banks'),
            href: editBanks(),
            icon: Building2,
            summary: t('settings.summaries.banks'),
        },
        {
            title: t('settings.sections.accounts'),
            href: editAccounts(),
            icon: Landmark,
            summary: t('settings.summaries.accounts'),
        },
        {
            title: t('settings.sections.years'),
            href: editYears(),
            icon: CalendarRange,
            summary: t('settings.summaries.years'),
        },
        {
            title: t('settings.sections.security'),
            icon: ShieldCheck,
            href: editSecurity(),
            summary: t('settings.summaries.security'),
        },
        importsEnabled.value
            ? {
                  title: t('settings.sections.imports'),
                  href: imports(),
                  icon: FolderInput,
                  summary: t('settings.summaries.imports'),
              }
            : null,
        {
            title: t('settings.sections.exports'),
            href: editExports(),
            icon: FolderOutput,
            summary: t('settings.summaries.exports'),
        },
        {
            title: t('settings.sections.appearance'),
            href: editAppearance(),
            icon: Palette,
            summary: t('settings.summaries.appearance'),
        },
    ].filter((item): item is SettingsNavItem => item !== null),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
const activeSettingsItem = computed<SettingsNavItem | null>(
    () =>
        sidebarNavItems.value.find((item) => isCurrentOrParentUrl(item.href)) ??
        sidebarNavItems.value[0] ??
        null,
);
</script>

<template>
    <div class="px-4 py-5 md:px-6 md:py-6">
        <div
            v-if="showMobileLauncher"
            class="md:hidden"
            data-test="settings-mobile-launcher"
        >
            <div
                class="rounded-[1.75rem] border border-slate-200/80 bg-white/92 px-5 py-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                >
                    {{ t('settings.accountArea') }}
                </p>
                <h1
                    class="mt-2 text-[1.65rem] leading-tight font-semibold tracking-[-0.03em] text-slate-950 dark:text-slate-50"
                >
                    {{ t('settings.title') }}
                </h1>
                <p
                    class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                >
                    {{ t('settings.accountAreaDescription') }}
                </p>
            </div>

            <nav class="mt-4 grid grid-cols-2 gap-3" aria-label="Impostazioni">
                <Link
                    v-for="item in sidebarNavItems"
                    :key="`mobile-${toUrl(item.href)}`"
                    :href="item.href"
                    :class="[
                        'group rounded-3xl border px-4 py-4 transition-all',
                        isCurrentOrParentUrl(item.href)
                            ? 'border-slate-900 bg-slate-900 text-white shadow-[0_18px_40px_-28px_rgba(15,23,42,0.7)] dark:border-slate-100 dark:bg-slate-100 dark:text-slate-950'
                            : 'border-slate-200/80 bg-white/92 text-slate-950 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.24)] dark:border-slate-800 dark:bg-slate-950/82 dark:text-slate-50',
                    ]"
                >
                    <div
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-2xl border',
                            isCurrentOrParentUrl(item.href)
                                ? 'border-white/15 bg-white/10 text-white dark:border-slate-300/40 dark:bg-slate-200 dark:text-slate-950'
                                : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200',
                        ]"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                    </div>

                    <div class="mt-4 min-w-0">
                        <p class="text-sm leading-5 font-semibold">
                            {{ item.title }}
                        </p>
                        <p
                            class="mt-1 line-clamp-2 text-[11px] leading-4"
                            :class="
                                isCurrentOrParentUrl(item.href)
                                    ? 'text-white/72 dark:text-slate-600'
                                    : 'text-slate-500 dark:text-slate-400'
                            "
                        >
                            {{ item.summary }}
                        </p>
                    </div>
                </Link>
            </nav>
        </div>

        <div class="hidden md:block">
            <Heading
                :title="t('settings.title')"
                :description="t('settings.description')"
            />
        </div>

        <div
            v-if="!showMobileLauncher"
            class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]"
        >
            <aside class="hidden space-y-4 md:block">
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-linear-to-br from-slate-950 via-slate-900 to-emerald-900 px-5 py-6 text-slate-50 dark:border-slate-800"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.24em] text-slate-300 uppercase"
                        >
                            {{ t('settings.accountArea') }}
                        </p>
                        <h2 class="mt-3 text-lg font-semibold tracking-tight">
                            {{ t('settings.accountAreaTitle') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-300">
                            {{ t('settings.accountAreaDescription') }}
                        </p>
                    </div>

                    <nav class="space-y-2 p-3" aria-label="Impostazioni">
                        <Button
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="[
                                'h-auto w-full justify-start rounded-2xl px-4 py-3 text-left transition-all',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/10 hover:bg-slate-900 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-100'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-slate-50',
                            ]"
                            as-child
                        >
                            <Link
                                :href="item.href"
                                class="group flex items-center gap-3"
                            >
                                <div
                                    :class="[
                                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition-colors',
                                        isCurrentOrParentUrl(item.href)
                                            ? 'border-white/15 bg-white/10 dark:border-slate-300/40 dark:bg-slate-200'
                                            : 'border-slate-200 bg-slate-50 group-hover:border-slate-300 group-hover:bg-white dark:border-slate-800 dark:bg-slate-900 dark:group-hover:border-slate-700 dark:group-hover:bg-slate-800',
                                    ]"
                                >
                                    <component
                                        :is="item.icon"
                                        :class="
                                            item.desktopIconClass ?? 'h-4 w-4'
                                        "
                                    />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium">
                                        {{ item.title }}
                                    </span>
                                    <span
                                        class="text-xs"
                                        :class="
                                            isCurrentOrParentUrl(item.href)
                                                ? 'text-white/70 dark:text-slate-600'
                                                : 'text-slate-500 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200'
                                        "
                                    >
                                        {{ item.summary }}
                                    </span>
                                </div>
                            </Link>
                        </Button>
                    </nav>
                </div>
            </aside>

            <div class="min-w-0">
                <div
                    class="mb-4 flex items-center gap-3 md:hidden"
                    data-test="settings-mobile-page-header"
                >
                    <Link
                        :href="mobileLauncherHref"
                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200/80 bg-white/92 text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-950 dark:border-slate-800 dark:bg-slate-950/82 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:text-slate-50"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-[1.65rem] leading-tight font-semibold tracking-[-0.03em] text-slate-950 dark:text-slate-50"
                        >
                            {{
                                activeSettingsItem?.title ?? t('settings.title')
                            }}
                        </h1>
                    </div>
                </div>

                <section class="space-y-4 md:space-y-6">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
