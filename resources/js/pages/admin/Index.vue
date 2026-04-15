<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    BookOpenText,
    Bot,
    CircleHelp,
    History,
    Mail,
    MessageSquareQuote,
    SendHorizontal,
    Settings2,
    Shield,
    Users,
    Waypoints,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { activityLog, index, users } from '@/routes/admin';
import { index as automationIndex } from '@/routes/admin/automation';
import { index as changelogIndex } from '@/routes/admin/changelog/index';
import { index as communicationCategoriesIndex } from '@/routes/admin/communication-categories';
import { index as communicationTemplatesIndex } from '@/routes/admin/communication-templates';
import { index as communicationComposerIndex } from '@/routes/admin/communications/compose';
import { index as communicationOutboundIndex } from '@/routes/admin/communications/outbound';
import { index as contextualHelpIndex } from '@/routes/admin/contextual-help';
import { index as knowledgeArticlesIndex } from '@/routes/admin/knowledge-articles';
import { index as pushBroadcastsIndex } from '@/routes/admin/push-broadcasts';
import { index as supportRequestsIndex } from '@/routes/admin/support-requests';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const pushNotificationsEnabled = computed(
    () => Boolean(page.props.features?.push_notifications_enabled),
);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('admin.sections.overview'),
        href: index(),
    },
];

const sectionCards = computed(() =>
    [
        {
            title: t('admin.overview.cards.users.title'),
            description: t('admin.overview.cards.users.description'),
            status: t('admin.overview.cards.users.status'),
            href: users(),
            icon: Users,
        },
        {
            title: t('admin.overview.cards.activityLog.title'),
            description: t('admin.overview.cards.activityLog.description'),
            status: t('admin.overview.cards.activityLog.status'),
            href: activityLog(),
            icon: Activity,
        },
        {
            title: t('admin.overview.cards.automation.title'),
            description: t('admin.overview.cards.automation.description'),
            status: t('admin.overview.cards.automation.status'),
            href: automationIndex(),
            icon: Bot,
        },
        {
            title: t('admin.overview.cards.changelog.title'),
            description: t('admin.overview.cards.changelog.description'),
            status: t('admin.overview.cards.changelog.status'),
            href: changelogIndex(),
            icon: History,
        },
        {
            title: t('admin.overview.cards.knowledgeBase.title'),
            description: t('admin.overview.cards.knowledgeBase.description'),
            status: t('admin.overview.cards.knowledgeBase.status'),
            href: knowledgeArticlesIndex(),
            icon: BookOpenText,
        },
        {
            title: t('admin.overview.cards.contextualHelp.title'),
            description: t('admin.overview.cards.contextualHelp.description'),
            status: t('admin.overview.cards.contextualHelp.status'),
            href: contextualHelpIndex(),
            icon: CircleHelp,
        },
        {
            title: t('admin.overview.cards.supportRequests.title'),
            description: t('admin.overview.cards.supportRequests.description'),
            status: t('admin.overview.cards.supportRequests.status'),
            href: supportRequestsIndex(),
            icon: MessageSquareQuote,
        },
        {
            title: t('admin.overview.cards.communicationCategories.title'),
            description: t(
                'admin.overview.cards.communicationCategories.description',
            ),
            status: t('admin.overview.cards.communicationCategories.status'),
            href: communicationCategoriesIndex(),
            icon: Settings2,
        },
        {
            title: t('admin.overview.cards.communicationComposer.title'),
            description: t(
                'admin.overview.cards.communicationComposer.description',
            ),
            status: t('admin.overview.cards.communicationComposer.status'),
            href: communicationComposerIndex(),
            icon: SendHorizontal,
        },
        {
            title: t('admin.overview.cards.communicationOutbound.title'),
            description: t(
                'admin.overview.cards.communicationOutbound.description',
            ),
            status: t('admin.overview.cards.communicationOutbound.status'),
            href: communicationOutboundIndex(),
            icon: Waypoints,
        },
        {
            title: t('admin.overview.cards.communicationTemplates.title'),
            description: t(
                'admin.overview.cards.communicationTemplates.description',
            ),
            status: t('admin.overview.cards.communicationTemplates.status'),
            href: communicationTemplatesIndex(),
            icon: Mail,
        },
        pushNotificationsEnabled.value
            ? {
                  title: t('admin.overview.cards.pushBroadcasts.title'),
                  description: t(
                      'admin.overview.cards.pushBroadcasts.description',
                  ),
                  status: t('admin.overview.cards.pushBroadcasts.status'),
                  href: pushBroadcastsIndex(),
                  icon: SendHorizontal,
              }
            : null,
    ].filter(
        (
            item,
        ): item is {
            title: string;
            description: string;
            status: string;
            href: string | Record<string, unknown>;
            icon: unknown;
        } => item !== null,
    ),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.overview.title')" />

        <AdminLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-sky-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] tracking-[0.2em] text-amber-900 uppercase dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                            >
                                {{ t('admin.shell.eyebrow') }}
                            </Badge>
                            <Heading
                                variant="small"
                                :title="t('admin.overview.title')"
                                :description="t('admin.overview.description')"
                            />
                        </div>
                        <div
                            class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300"
                        >
                            <Shield class="h-4 w-4 text-amber-500" />
                            <span>{{ t('admin.badge') }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-8 py-8">
                    <div class="grid gap-4 xl:grid-cols-2">
                        <Card
                            v-for="item in sectionCards"
                            :key="item.title"
                            class="rounded-[1.5rem] border-slate-200/80 bg-white/90 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <CardHeader class="space-y-4">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-5 w-5 text-slate-700 dark:text-slate-200"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <CardTitle class="text-base">
                                        {{ item.title }}
                                    </CardTitle>
                                    <CardDescription class="text-sm leading-6">
                                        {{ item.description }}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent
                                class="flex items-center justify-between gap-4 pt-0"
                            >
                                <span
                                    class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                >
                                    {{ item.status }}
                                </span>
                                <Button
                                    variant="ghost"
                                    class="h-10 rounded-xl px-3"
                                    as-child
                                >
                                    <Link :href="item.href">
                                        {{ item.title }}
                                        <ArrowRight class="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <div
                        class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 p-6 dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <h2
                            class="text-base font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                        >
                            {{ t('admin.overview.empty.title') }}
                        </h2>
                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ t('admin.overview.empty.description') }}
                        </p>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
