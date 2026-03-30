<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarRange,
    CircleUserRound,
    Landmark,
    Layers3,
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
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security/index';
import { edit as editTrackedItems } from '@/routes/tracked-items';
import { edit as editYears } from '@/routes/years';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const hasSharedCategories = computed(
    () => page.props.settingsNavigation?.has_shared_categories === true,
);

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: t('settings.sections.profile'),
        icon: CircleUserRound,
        href: editProfile(),
    },
    {
        title: t('settings.sections.categories'),
        href: editCategories(),
        icon: Layers3,
    },
    ...(hasSharedCategories.value
        ? [{
              title: t('settings.sections.sharedCategories'),
              href: '/settings/shared-categories',
              icon: Network,
          }]
        : []),
    {
        title: t('settings.sections.trackedItems'),
        href: editTrackedItems(),
        icon: Route,
    },
    {
        title: t('settings.sections.banks'),
        href: editBanks(),
        icon: Building2,
    },
    {
        title: t('settings.sections.accounts'),
        href: editAccounts(),
        icon: Landmark,
    },
    {
        title: t('settings.sections.years'),
        href: editYears(),
        icon: CalendarRange,
    },
    {
        title: t('settings.sections.security'),
        icon: ShieldCheck,
        href: editSecurity(),
    },
    {
        title: t('settings.sections.appearance'),
        href: editAppearance(),
        icon: Palette,
    },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();

function summaryKey(title: string): string {
    if (title === t('settings.sections.profile')) {
        return 'settings.summaries.profile';
    }

    if (title === t('settings.sections.categories')) {
        return 'settings.summaries.categories';
    }

    if (title === t('settings.sections.sharedCategories')) {
        return 'settings.summaries.sharedCategories';
    }

    if (title === t('settings.sections.trackedItems')) {
        return 'settings.summaries.trackedItems';
    }

    if (title === t('settings.sections.banks')) {
        return 'settings.summaries.banks';
    }

    if (title === t('settings.sections.accounts')) {
        return 'settings.summaries.accounts';
    }

    if (title === t('settings.sections.years')) {
        return 'settings.summaries.years';
    }

    if (title === t('settings.sections.security')) {
        return 'settings.summaries.security';
    }

    return 'settings.summaries.appearance';
}
</script>

<template>
    <div class="px-4 py-6 md:px-6">
        <Heading
            :title="t('settings.title')"
            :description="t('settings.description')"
        />

        <div class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-4">
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-900 px-5 py-6 text-slate-50 dark:border-slate-800"
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
                                        'flex h-10 w-10 items-center justify-center rounded-xl border transition-colors',
                                        isCurrentOrParentUrl(item.href)
                                            ? 'border-white/15 bg-white/10 dark:border-slate-300/40 dark:bg-slate-200'
                                            : 'border-slate-200 bg-slate-50 group-hover:border-slate-300 group-hover:bg-white dark:border-slate-800 dark:bg-slate-900 dark:group-hover:border-slate-700 dark:group-hover:bg-slate-800',
                                    ]"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-4 w-4"
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
                                        {{ t(summaryKey(item.title)) }}
                                    </span>
                                </div>
                            </Link>
                        </Button>
                    </nav>
                </div>
            </aside>

            <div class="min-w-0">
                <section class="space-y-6">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
