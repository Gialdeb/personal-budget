<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, CalendarDays, FileUp, Inbox, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import type {
    BreadcrumbItem,
    NotificationInboxItem,
    NotificationInboxPreview,
    NotificationsPageProps,
} from '@/types';

const props = defineProps<NotificationsPageProps>();
const page = usePage();
const { t } = useI18n();
const activeNotificationUuid = ref<string | null>(null);
const isMarkingAllNotificationsRead = ref(false);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('app.shell.notificationsPage.title'),
        href: notificationsIndex(),
    },
];

const sharedNotificationInbox = computed(
    () =>
        (page.props.notificationInbox ??
            null) as NotificationInboxPreview | null,
);
const notifications = computed(() => props.notifications.data);

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function notificationIcon(notification: NotificationInboxItem) {
    if (notification.presentation.icon === 'import') {
        return FileUp;
    }

    if (notification.presentation.icon === 'report') {
        return CalendarDays;
    }

    if (notification.presentation.icon === 'welcome') {
        return Sparkles;
    }

    return Bell;
}

function notificationAccentClass(notification: NotificationInboxItem): string {
    if (notification.presentation.icon === 'import') {
        return 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300';
    }

    if (notification.presentation.icon === 'report') {
        return 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300';
    }

    if (notification.presentation.icon === 'welcome') {
        return 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    return 'bg-slate-500/12 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300';
}

function isRichNotification(notification: NotificationInboxItem): boolean {
    return (
        Boolean(notification.presentation.image_url) ||
        notification.presentation.layout !== 'standard_card'
    );
}

function notificationCardClass(notification: NotificationInboxItem): string {
    if (isRichNotification(notification)) {
        return notification.is_unread
            ? 'border-violet-200/90 bg-gradient-to-br from-white via-violet-50 to-sky-50 shadow-[0_24px_50px_-36px_rgba(124,58,237,0.4)] dark:border-violet-900/70 dark:bg-[linear-gradient(135deg,rgba(76,29,149,0.24),rgba(15,23,42,0.86),rgba(14,116,144,0.18))]'
            : 'border-slate-200/80 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] dark:border-slate-800 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.94),rgba(2,6,23,0.84))]';
    }

    return notification.is_unread
        ? 'border-sky-200/90 bg-sky-50/70 shadow-[0_18px_40px_-28px_rgba(14,116,144,0.3)] dark:border-sky-900/70 dark:bg-sky-950/20'
        : 'border-slate-200/80 bg-white/90 dark:border-slate-800 dark:bg-slate-950/80';
}

function formatNotificationDate(date: string | null): string {
    if (!date) {
        return t('app.shell.notifications.now');
    }

    const locale = String(
        (page.props.locale as { current?: string } | undefined)?.current ??
            'en',
    );

    return new Intl.DateTimeFormat(locale === 'it' ? 'it-IT' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

async function markNotificationAsRead(
    notification: NotificationInboxItem,
): Promise<void> {
    if (notification.is_read || activeNotificationUuid.value) {
        return;
    }

    activeNotificationUuid.value = notification.uuid;

    try {
        await fetch(
            `${sharedNotificationInbox.value?.index_url ?? '/notifications'}/${notification.uuid}/read`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        router.reload({
            only: ['notifications', 'summary', 'notificationInbox'],
        });
    } finally {
        activeNotificationUuid.value = null;
    }
}

async function markAllNotificationsAsRead(): Promise<void> {
    if (
        props.summary.unread_count === 0 ||
        isMarkingAllNotificationsRead.value
    ) {
        return;
    }

    isMarkingAllNotificationsRead.value = true;

    try {
        await fetch(
            sharedNotificationInbox.value?.mark_all_read_url ??
                '/notifications/mark-all-read',
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        router.reload({
            only: ['notifications', 'summary', 'notificationInbox'],
        });
    } finally {
        isMarkingAllNotificationsRead.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('app.shell.notificationsPage.title')" />

        <section class="space-y-6">
            <div
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 px-6 py-6 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-2">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-400"
                            >
                                <Bell class="size-3.5" />
                                {{ t('app.shell.notificationsPage.title') }}
                            </div>
                            <div>
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('app.shell.notificationsPage.title') }}
                                </h1>
                                <p
                                    class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t(
                                            'app.shell.notificationsPage.description',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="secondary"
                                class="rounded-full px-3 py-1"
                            >
                                {{
                                    t(
                                        'app.shell.notificationsPage.unreadBadge',
                                        { count: props.summary.unread_count },
                                    )
                                }}
                            </Badge>
                            <Button
                                variant="secondary"
                                class="rounded-full"
                                :disabled="
                                    props.summary.unread_count === 0 ||
                                    isMarkingAllNotificationsRead
                                "
                                @click="markAllNotificationsAsRead"
                            >
                                {{
                                    t(
                                        'app.shell.notificationsPage.actions.markAllAsRead',
                                    )
                                }}
                            </Button>
                            <Link
                                :href="dashboard()"
                                class="inline-flex h-10 items-center rounded-full border border-slate-200/80 px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
                            >
                                {{
                                    t(
                                        'app.shell.notificationsPage.actions.backToDashboard',
                                    )
                                }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <div
                        v-if="props.notifications.data.length === 0"
                        class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-10 text-center dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-3xl bg-slate-200/80 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <Inbox class="size-6" />
                        </div>
                        <h2
                            class="mt-4 text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ t('app.shell.notificationsPage.empty.title') }}
                        </h2>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            {{
                                t(
                                    'app.shell.notificationsPage.empty.description',
                                )
                            }}
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <article
                            v-for="notification in notifications"
                            :key="notification.uuid"
                            class="rounded-[1.5rem] border p-4 transition md:p-5"
                            :class="notificationCardClass(notification)"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start"
                            >
                                <div
                                    v-if="notification.presentation.image_url"
                                    class="overflow-hidden rounded-[1.25rem] border border-white/60 shadow-sm sm:w-40 sm:shrink-0 dark:border-slate-700/70"
                                >
                                    <img
                                        :src="
                                            notification.presentation.image_url
                                        "
                                        :alt="
                                            notification.content.title ||
                                            t(
                                                'app.shell.notificationsPage.title',
                                            )
                                        "
                                        class="h-32 w-full object-cover sm:h-full"
                                    />
                                </div>
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl"
                                    :class="[
                                        notificationAccentClass(notification),
                                        {
                                            'sm:hidden': Boolean(
                                                notification.presentation
                                                    .image_url,
                                            ),
                                        },
                                    ]"
                                >
                                    <component
                                        :is="notificationIcon(notification)"
                                        class="size-5"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                                    :class="
                                                        isRichNotification(
                                                            notification,
                                                        )
                                                            ? 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300'
                                                            : 'bg-slate-200/70 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                                    "
                                                >
                                                    {{
                                                        isRichNotification(
                                                            notification,
                                                        )
                                                            ? t(
                                                                  'app.shell.notificationsPage.richLabel',
                                                              )
                                                            : t(
                                                                  'app.shell.notificationsPage.standardLabel',
                                                              )
                                                    }}
                                                </span>
                                                <h2
                                                    class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        notification.content
                                                            .title
                                                    }}
                                                </h2>
                                                <span
                                                    v-if="
                                                        notification.is_unread
                                                    "
                                                    class="inline-flex items-center rounded-full bg-sky-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                                >
                                                    {{
                                                        t(
                                                            'app.shell.notifications.newLabel',
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-2 text-sm leading-6"
                                                :class="
                                                    isRichNotification(
                                                        notification,
                                                    )
                                                        ? 'text-slate-700 dark:text-slate-200'
                                                        : 'text-slate-600 dark:text-slate-300'
                                                "
                                            >
                                                {{
                                                    notification.content.message
                                                }}
                                            </p>
                                            <p
                                                class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    formatNotificationDate(
                                                        notification.created_at,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="flex flex-wrap items-center gap-2 md:justify-end"
                                        >
                                            <Link
                                                v-if="
                                                    notification.content.cta_url
                                                "
                                                :href="
                                                    notification.content.cta_url
                                                "
                                                class="inline-flex h-10 items-center rounded-full px-4 text-sm font-medium transition"
                                                :class="
                                                    isRichNotification(
                                                        notification,
                                                    )
                                                        ? 'bg-violet-600 text-white hover:bg-violet-700 dark:bg-violet-500 dark:hover:bg-violet-400'
                                                        : 'bg-slate-950 text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white'
                                                "
                                            >
                                                {{
                                                    notification.content
                                                        .cta_label ||
                                                    t(
                                                        'app.shell.notifications.openItem',
                                                    )
                                                }}
                                            </Link>
                                            <Button
                                                v-if="notification.is_unread"
                                                variant="ghost"
                                                class="rounded-full"
                                                :disabled="
                                                    activeNotificationUuid ===
                                                    notification.uuid
                                                "
                                                @click="
                                                    markNotificationAsRead(
                                                        notification,
                                                    )
                                                "
                                            >
                                                {{
                                                    t(
                                                        'app.shell.notificationsPage.actions.markAsRead',
                                                    )
                                                }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <nav
                            v-if="props.notifications.meta.last_page > 1"
                            class="flex flex-wrap items-center justify-between gap-3 pt-2"
                        >
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ props.notifications.meta.from ?? 0 }}-{{
                                    props.notifications.meta.to ?? 0
                                }}
                                / {{ props.notifications.meta.total }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="link in props.notifications.meta
                                        .links"
                                    :key="`${link.label}-${link.url}`"
                                    :href="link.url || '#'"
                                    class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-full border px-3 text-sm transition"
                                    :class="
                                        link.active
                                            ? 'border-slate-950 bg-slate-950 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-950'
                                            : 'border-slate-200/80 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900'
                                    "
                                >
                                    <span v-html="link.label" />
                                </Link>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
