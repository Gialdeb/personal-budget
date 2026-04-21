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
const mobileLauncherHref = computed(() => settingsIndex());
const isSettingsRoot = computed(
    () => currentUrl.value.pathname === '/settings',
);
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
                class="rounded-[1.75rem] border border-border/80 bg-card/92 px-5 py-5 text-card-foreground shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                >
                    {{ t('settings.accountArea') }}
                </p>
                <h1
                    class="mt-2 text-[1.65rem] leading-tight font-semibold tracking-[-0.03em] text-foreground"
                >
                    {{ t('settings.title') }}
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{ t('settings.accountAreaDescription') }}
                </p>
            </div>

            <nav
                class="mt-4 grid grid-cols-2 gap-3"
                :aria-label="t('settings.navigationLabel')"
            >
                <Link
                    v-for="item in sidebarNavItems"
                    :key="`mobile-${toUrl(item.href)}`"
                    :href="item.href"
                    :class="[
                        'group rounded-3xl border px-4 py-4 transition-all',
                        isCurrentOrParentUrl(item.href)
                            ? 'border-foreground bg-foreground text-background shadow-[0_18px_40px_-28px_rgba(15,23,42,0.45)]'
                            : 'border-border/80 bg-card/92 text-card-foreground shadow-[0_18px_40px_-32px_rgba(15,23,42,0.18)] hover:border-border hover:bg-accent/45',
                    ]"
                >
                    <div
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-2xl border',
                            isCurrentOrParentUrl(item.href)
                                ? 'border-background/15 bg-background/10 text-background'
                                : 'border-border bg-muted text-muted-foreground',
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
                                    ? 'text-background/72'
                                    : 'text-muted-foreground'
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
                    class="overflow-hidden rounded-[1.75rem] border border-border/80 bg-card/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur"
                >
                    <div
                        class="border-b border-border/70 bg-linear-to-br from-foreground/6 via-accent/80 to-secondary px-5 py-6 text-foreground"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.24em] text-muted-foreground uppercase"
                        >
                            {{ t('settings.accountArea') }}
                        </p>
                        <h2 class="mt-3 text-lg font-semibold tracking-tight">
                            {{ t('settings.accountAreaTitle') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ t('settings.accountAreaDescription') }}
                        </p>
                    </div>

                    <nav
                        class="space-y-2 p-3"
                        :aria-label="t('settings.navigationLabel')"
                    >
                        <Button
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="[
                                'h-auto w-full justify-start rounded-2xl px-4 py-3 text-left transition-all',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-foreground text-background shadow-lg shadow-black/10 hover:bg-foreground'
                                    : 'text-muted-foreground hover:bg-foreground hover:text-background',
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
                                            ? 'border-background/15 bg-background/10 text-background'
                                            : 'border-border bg-muted text-muted-foreground group-hover:border-background/15 group-hover:bg-background/10 group-hover:text-background',
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
                                    <span
                                        :class="
                                            isCurrentOrParentUrl(item.href)
                                                ? 'text-background'
                                                : 'text-foreground group-hover:text-background'
                                        "
                                        class="text-sm font-medium transition-colors"
                                    >
                                        {{ item.title }}
                                    </span>
                                    <span
                                        class="text-xs transition-colors"
                                        :class="
                                            isCurrentOrParentUrl(item.href)
                                                ? 'text-background/72'
                                                : 'text-muted-foreground group-hover:text-background/72'
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
                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-border/80 bg-card/92 text-muted-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-[1.65rem] leading-tight font-semibold tracking-[-0.03em] text-foreground"
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
