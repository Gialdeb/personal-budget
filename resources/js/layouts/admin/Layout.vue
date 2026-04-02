<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    Bot,
    History,
    Mail,
    SendHorizontal,
    Settings2,
    Shield,
    Users,
    Waypoints,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { index, users, activityLog } from '@/routes/admin';
import { index as automationIndex } from '@/routes/admin/automation';
import { index as changelogIndex } from '@/routes/admin/changelog/index';
import { index as communicationCategoriesIndex } from '@/routes/admin/communication-categories';
import { index as communicationTemplatesIndex } from '@/routes/admin/communication-templates';
import { index as communicationComposerIndex } from '@/routes/admin/communications/compose';
import { index as communicationOutboundIndex } from '@/routes/admin/communications/outbound';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: t('admin.sections.overview'),
        href: index(),
        icon: Shield,
    },
    {
        title: t('admin.sections.users'),
        href: users(),
        icon: Users,
    },
    {
        title: t('admin.sections.activityLog'),
        href: activityLog(),
        icon: Activity,
    },
    {
        title: t('admin.sections.automation'),
        href: automationIndex(),
        icon: Bot,
    },
    {
        title: t('admin.sections.changelog'),
        href: changelogIndex(),
        icon: History,
    },
    {
        title: t('admin.sections.communicationCategories'),
        href: communicationCategoriesIndex(),
        icon: Settings2,
    },
    {
        title: t('admin.sections.communicationComposer'),
        href: communicationComposerIndex(),
        icon: SendHorizontal,
    },
    {
        title: t('admin.sections.communicationOutbound'),
        href: communicationOutboundIndex(),
        icon: Waypoints,
    },
    {
        title: t('admin.sections.communicationTemplates'),
        href: communicationTemplatesIndex(),
        icon: Mail,
    },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
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
    index({
        query: {
            mobile: 'launcher',
        },
    }),
);
const isMobileLauncher = computed(
    () => currentUrl.value.searchParams.get('mobile') === 'launcher',
);
const activeAdminItem = computed<NavItem | null>(
    () =>
        sidebarNavItems.value.find((item) => isCurrentOrParentUrl(item.href)) ??
        sidebarNavItems.value[0] ??
        null,
);

function summaryKey(title: string): string {
    if (title === t('admin.sections.overview')) {
        return 'admin.summaries.overview';
    }

    if (title === t('admin.sections.users')) {
        return 'admin.summaries.users';
    }

    if (title === t('admin.sections.automation')) {
        return 'admin.summaries.automation';
    }

    if (title === t('admin.sections.communicationTemplates')) {
        return 'admin.summaries.communicationTemplates';
    }

    if (title === t('admin.sections.changelog')) {
        return 'admin.summaries.changelog';
    }

    if (title === t('admin.sections.communicationCategories')) {
        return 'admin.summaries.communicationCategories';
    }

    if (title === t('admin.sections.communicationComposer')) {
        return 'admin.summaries.communicationComposer';
    }

    if (title === t('admin.sections.communicationOutbound')) {
        return 'admin.summaries.communicationOutbound';
    }

    return 'admin.summaries.activityLog';
}
</script>

<template>
    <div class="px-4 py-6 md:px-6">
        <div
            v-if="isMobileLauncher"
            class="md:hidden"
            data-test="admin-mobile-launcher"
        >
            <div
                class="rounded-[1.75rem] border border-slate-200/80 bg-white/92 px-5 py-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                >
                    {{ t('admin.shell.eyebrow') }}
                </p>
                <h1
                    class="mt-2 text-[1.65rem] leading-tight font-semibold tracking-[-0.03em] text-slate-950 dark:text-slate-50"
                >
                    {{ t('admin.title') }}
                </h1>
                <p
                    class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                >
                    {{ t('admin.description') }}
                </p>
            </div>

            <nav class="mt-4 grid grid-cols-2 gap-3" aria-label="Admin">
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
                            {{ t(summaryKey(item.title)) }}
                        </p>
                    </div>
                </Link>
            </nav>
        </div>

        <div v-if="!isMobileLauncher" class="hidden md:block">
            <Heading
                :title="t('admin.title')"
                :description="t('admin.description')"
            />
        </div>

        <div
            v-if="!isMobileLauncher"
            class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]"
        >
            <aside class="hidden space-y-4 md:block">
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-linear-to-br from-slate-950 via-slate-900 to-amber-900 px-5 py-6 text-slate-50 dark:border-slate-800"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.24em] text-slate-300 uppercase"
                        >
                            {{ t('admin.shell.eyebrow') }}
                        </p>
                        <h2 class="mt-3 text-lg font-semibold tracking-tight">
                            {{ t('admin.shell.title') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-300">
                            {{ t('admin.shell.description') }}
                        </p>
                    </div>

                    <nav class="space-y-2 p-3" aria-label="Admin navigation">
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
                                class="group flex min-w-0 items-center gap-3"
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
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span class="truncate text-sm font-medium">
                                        {{ item.title }}
                                    </span>
                                    <span
                                        class="line-clamp-2 text-xs leading-5"
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
                <div
                    class="mb-4 flex items-center gap-3 md:hidden"
                    data-test="admin-mobile-page-header"
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
                            {{ activeAdminItem?.title ?? t('admin.title') }}
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
