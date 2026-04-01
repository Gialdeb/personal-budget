<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
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
        <Heading
            :title="t('admin.title')"
            :description="t('admin.description')"
        />

        <div class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-4">
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-br from-slate-950 via-slate-900 to-amber-900 px-5 py-6 text-slate-50 dark:border-slate-800"
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
