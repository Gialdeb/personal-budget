<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    ChevronDown,
    CircleDollarSign,
    FileUp,
    Inbox,
    LayoutGrid,
    Plus,
    ScrollText,
    Settings2,
    SlidersHorizontal,
    Sparkles,
    Tags,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import {
    persistHeaderInfoExpanded,
    readHeaderInfoExpanded,
} from '@/lib/header-preferences';
import { edit as accounts } from '@/routes/accounts';
import { index as imports } from '@/routes/imports';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as trackedItems } from '@/routes/tracked-items';
import { show as transactionsShow } from '@/routes/transactions';
import type {
    Auth,
    BreadcrumbItem,
    NotificationInboxItem,
    NotificationInboxPreview,
    TransactionsNavigation,
} from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const { t } = useI18n();
const { getInitials } = useInitials();

type RouteSection =
    | 'dashboard'
    | 'planning'
    | 'transactions'
    | 'recurring'
    | 'imports'
    | 'accounts'
    | 'references'
    | 'settings'
    | 'admin'
    | 'generic';

const auth = computed(() => page.props.auth as Auth);
const navigation = computed(
    () => page.props.transactionsNavigation as TransactionsNavigation | null,
);

const currentPath = computed(() => {
    const url = String(page.url ?? '/');

    return url.split('?')[0] || '/';
});

const currentSection = computed<RouteSection>(() => {
    const path = currentPath.value;

    if (path === '/') {
        return 'dashboard';
    }

    if (path.startsWith('/budgets/planning')) {
        return 'planning';
    }

    if (path.startsWith('/transactions')) {
        return 'transactions';
    }

    if (path.startsWith('/recurring-entries')) {
        return 'recurring';
    }

    if (path.startsWith('/imports')) {
        return 'imports';
    }

    if (path.startsWith('/settings/accounts')) {
        return 'accounts';
    }

    if (path.startsWith('/settings/tracked-items')) {
        return 'references';
    }

    if (path.startsWith('/settings')) {
        return 'settings';
    }

    if (path.startsWith('/admin')) {
        return 'admin';
    }

    return 'generic';
});

const pageTitle = computed(() => {
    if (props.breadcrumbs.length > 0) {
        return props.breadcrumbs.at(-1)?.title ?? t('app.name');
    }

    if (currentSection.value === 'dashboard') {
        return t('nav.dashboard');
    }

    return t('app.name');
});

const pageDescription = computed(() =>
    t(`app.shell.pages.${currentSection.value}.description`),
);
const isInfoExpanded = ref(readHeaderInfoExpanded());

const transactionsHref = computed(() => {
    if (navigation.value?.context.year && navigation.value?.context.month) {
        return transactionsShow({
            year: navigation.value.context.year,
            month: navigation.value.context.month,
        });
    }

    const now = new Date();

    return transactionsShow({
        year: now.getFullYear(),
        month: now.getMonth() + 1,
    });
});

const quickActions = computed(() => [
    {
        label: t('app.shell.actions.newTransaction'),
        href: transactionsHref.value,
        icon: Plus,
        variant: 'default' as const,
    },
    {
        label: t('app.shell.actions.importTransactions'),
        href: imports(),
        icon: FileUp,
        variant: 'secondary' as const,
    },
    {
        label: t('app.shell.actions.newAccount'),
        href: accounts(),
        icon: CircleDollarSign,
        variant: 'secondary' as const,
    },
    {
        label: t('app.shell.actions.newReference'),
        href: trackedItems(),
        icon: Tags,
        variant: 'secondary' as const,
    },
]);

const sharedNotificationInbox = computed(
    () =>
        (page.props.notificationInbox ??
            null) as NotificationInboxPreview | null,
);
const notificationInbox = ref<NotificationInboxPreview>({
    unread_count: sharedNotificationInbox.value?.unread_count ?? 0,
    latest: sharedNotificationInbox.value?.latest ?? [],
    index_url:
        sharedNotificationInbox.value?.index_url ?? notificationsIndex().url,
    preview_url:
        sharedNotificationInbox.value?.preview_url ?? '/notifications/preview',
    mark_all_read_url:
        sharedNotificationInbox.value?.mark_all_read_url ??
        '/notifications/mark-all-read',
});
const isMarkingAllNotificationsRead = ref(false);
const activeNotificationUuid = ref<string | null>(null);
const isNotificationBellAnimated = ref(false);
let notificationBellAnimationTimeout: ReturnType<typeof setTimeout> | null =
    null;
let hasObservedNotificationCount = false;

const notifications = computed(() => notificationInbox.value.latest);
const unreadPreviewNotifications = computed(() =>
    notifications.value.filter((notification) => notification.is_unread),
);
const unreadNotificationsCount = computed(
    () => notificationInbox.value.unread_count,
);
const hasNotifications = computed(
    () => unreadPreviewNotifications.value.length > 0,
);

const statusChips = computed(() => {
    const user = auth.value.user;
    const chips = [
        {
            key: 'currency',
            label: t('app.shell.statusBaseCurrency'),
            value: user.base_currency_code,
        },
        {
            key: 'format',
            label: t('app.shell.statusFormatLocale'),
            value: user.format_locale,
        },
    ];

    if (user.settings?.active_year) {
        chips.push({
            key: 'year',
            label: t('app.shell.statusActiveYear'),
            value: String(user.settings.active_year),
        });
    }

    if (navigation.value?.context.period_label) {
        chips.push({
            key: 'period',
            label: t('app.shell.statusCurrentPeriod'),
            value: navigation.value.context.period_label,
        });
    }

    return chips;
});

const sectionIcon = computed(() => {
    if (currentSection.value === 'transactions') {
        return ScrollText;
    }

    if (currentSection.value === 'recurring') {
        return CalendarDays;
    }

    if (currentSection.value === 'imports') {
        return FileUp;
    }

    if (
        currentSection.value === 'settings' ||
        currentSection.value === 'accounts' ||
        currentSection.value === 'references'
    ) {
        return Settings2;
    }

    return LayoutGrid;
});

const headerToneClass = computed(() =>
    currentSection.value === 'transactions' ||
    currentSection.value === 'planning'
        ? 'from-sky-500/12 via-white to-emerald-500/10 dark:from-sky-500/10 dark:via-slate-950 dark:to-emerald-500/10'
        : 'from-slate-900/[0.06] via-white to-sky-500/[0.08] dark:from-slate-800 dark:via-slate-950 dark:to-sky-500/[0.08]',
);

const infoToggleLabel = computed(() =>
    isInfoExpanded.value
        ? t('app.shell.collapseInfo')
        : t('app.shell.expandInfo'),
);

watch(isInfoExpanded, (value) => {
    persistHeaderInfoExpanded(value);
});

watch(
    sharedNotificationInbox,
    (value) => {
        if (!value) {
            return;
        }

        notificationInbox.value = {
            unread_count: value.unread_count,
            latest: [...value.latest],
            index_url: value.index_url,
            preview_url: value.preview_url,
            mark_all_read_url: value.mark_all_read_url,
        };
    },
    { deep: true },
);

watch(unreadNotificationsCount, (value, previousValue) => {
    if (!hasObservedNotificationCount) {
        hasObservedNotificationCount = true;

        return;
    }

    if (value > previousValue) {
        triggerNotificationBellAnimation();
    }
});

onBeforeUnmount(() => {
    if (notificationBellAnimationTimeout) {
        clearTimeout(notificationBellAnimationTimeout);
    }
});

function toggleInfoPanel(): void {
    isInfoExpanded.value = !isInfoExpanded.value;
}

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function markNotificationAsRead(
    notification: NotificationInboxItem,
    navigateToCta = false,
): Promise<void> {
    if (activeNotificationUuid.value) {
        return;
    }

    if (notification.is_read && navigateToCta) {
        visitNotificationCta(notification);

        return;
    }

    activeNotificationUuid.value = notification.uuid;

    try {
        if (notification.is_unread) {
            const response = await fetch(
                `${notificationInbox.value.index_url}/${notification.uuid}/read`,
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

            if (response.ok) {
                const payload = (await response.json()) as Pick<
                    NotificationInboxPreview,
                    'unread_count' | 'latest'
                >;

                notificationInbox.value = {
                    ...notificationInbox.value,
                    unread_count: payload.unread_count,
                    latest: payload.latest,
                };
            }
        }

        if (navigateToCta) {
            visitNotificationCta(notification);
        }
    } finally {
        activeNotificationUuid.value = null;
    }
}

async function markAllNotificationsAsRead(): Promise<void> {
    if (
        isMarkingAllNotificationsRead.value ||
        unreadNotificationsCount.value === 0
    ) {
        return;
    }

    isMarkingAllNotificationsRead.value = true;

    try {
        const response = await fetch(
            notificationInbox.value.mark_all_read_url,
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

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as Pick<
            NotificationInboxPreview,
            'unread_count' | 'latest'
        >;

        notificationInbox.value = {
            ...notificationInbox.value,
            unread_count: payload.unread_count,
            latest: payload.latest,
        };
    } finally {
        isMarkingAllNotificationsRead.value = false;
    }
}

function visitNotificationCta(notification: NotificationInboxItem): void {
    const url = notification.content.cta_url;

    if (!url) {
        return;
    }

    if (/^https?:\/\//.test(url)) {
        window.location.assign(url);

        return;
    }

    router.visit(url);
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

function notificationSurfaceClass(notification: NotificationInboxItem): string {
    if (
        notification.presentation.image_url ||
        notification.presentation.layout !== 'standard_card'
    ) {
        return notification.is_unread
            ? 'border-violet-200/90 bg-gradient-to-br from-violet-50 via-white to-sky-50 shadow-[0_18px_40px_-28px_rgba(124,58,237,0.35)] dark:border-violet-900/70 dark:bg-[linear-gradient(135deg,rgba(76,29,149,0.22),rgba(15,23,42,0.82),rgba(14,116,144,0.18))]'
            : 'border-slate-200/80 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] dark:border-slate-800 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.92),rgba(2,6,23,0.84))]';
    }

    return notification.is_unread
        ? 'border-sky-200/90 bg-sky-50/70 shadow-[0_18px_40px_-28px_rgba(14,116,144,0.45)] dark:border-sky-900/70 dark:bg-sky-950/20'
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
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(date));
}

function triggerNotificationBellAnimation(): void {
    isNotificationBellAnimated.value = true;

    if (notificationBellAnimationTimeout) {
        clearTimeout(notificationBellAnimationTimeout);
    }

    notificationBellAnimationTimeout = setTimeout(() => {
        isNotificationBellAnimated.value = false;
    }, 1400);
}
</script>

<template>
    <header class="shrink-0 px-4 pt-4 md:px-6 md:pt-6">
        <div
            class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-br shadow-[0_30px_80px_-56px_rgba(15,23,42,0.6)] backdrop-blur-sm dark:border-slate-800"
            :class="headerToneClass"
        >
            <div
                class="border-b border-slate-200/70 px-5 py-3 dark:border-slate-800/80"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3 md:flex-nowrap md:items-center"
                >
                    <div
                        class="flex min-w-0 flex-1 items-start gap-3 sm:items-center"
                    >
                        <SidebarTrigger
                            class="rounded-xl border border-slate-200/80 bg-white/90 shadow-sm transition hover:bg-white dark:border-slate-800 dark:bg-slate-950/80 dark:hover:bg-slate-900"
                        />
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                <component
                                    :is="sectionIcon"
                                    class="size-3.5 shrink-0"
                                />
                                <span class="truncate">{{
                                    t('app.shell.headerContext')
                                }}</span>
                            </div>
                            <div
                                v-if="props.breadcrumbs.length > 0"
                                class="mt-1 hidden min-w-0 sm:block"
                            >
                                <Breadcrumbs :breadcrumbs="breadcrumbs" />
                            </div>
                        </div>
                    </div>

                    <div
                        class="ml-auto flex shrink-0 items-center gap-2 self-start sm:self-center"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 rounded-full border border-slate-200/80 bg-white/90 shadow-sm transition hover:bg-white dark:border-slate-800 dark:bg-slate-950/80 dark:hover:bg-slate-900"
                            :aria-label="infoToggleLabel"
                            :aria-expanded="isInfoExpanded"
                            @click="toggleInfoPanel"
                        >
                            <ChevronDown
                                class="size-4 transition-transform"
                                :class="isInfoExpanded ? 'rotate-180' : ''"
                            />
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="secondary"
                                    class="h-10 rounded-full px-3 shadow-sm"
                                    :aria-label="
                                        t('app.shell.openQuickActions')
                                    "
                                >
                                    <SlidersHorizontal class="size-4 sm:mr-2" />
                                    <span class="hidden sm:inline">
                                        {{ t('app.shell.quickActions') }}
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="w-64 rounded-2xl"
                            >
                                <DropdownMenuLabel
                                    class="text-xs tracking-[0.14em] text-slate-500 uppercase"
                                >
                                    {{ t('app.shell.quickActions') }}
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-for="action in quickActions"
                                    :key="action.label"
                                    as-child
                                >
                                    <Link
                                        :href="action.href"
                                        prefetch
                                        class="flex w-full items-center"
                                    >
                                        <component
                                            :is="action.icon"
                                            class="mr-2 size-4"
                                        />
                                        {{ action.label }}
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="relative h-10 w-10 rounded-full border border-slate-200/80 bg-white/90 shadow-sm transition hover:bg-white dark:border-slate-800 dark:bg-slate-950/80 dark:hover:bg-slate-900"
                                    :class="
                                        isNotificationBellAnimated
                                            ? 'ring-4 ring-sky-400/20 motion-safe:animate-pulse'
                                            : ''
                                    "
                                    :aria-label="
                                        t('app.shell.notifications.open')
                                    "
                                >
                                    <Bell class="size-4" />
                                    <span
                                        v-if="unreadNotificationsCount > 0"
                                        class="absolute -top-0.5 -right-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-sky-600 px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                    >
                                        {{ unreadNotificationsCount }}
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="w-[22rem] rounded-2xl p-2 sm:w-[25rem]"
                            >
                                <div class="px-2 py-1.5">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                t(
                                                    'app.shell.notifications.title',
                                                )
                                            }}
                                        </p>
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full"
                                        >
                                            {{
                                                t(
                                                    'app.shell.notifications.unread',
                                                    {
                                                        count: unreadNotificationsCount,
                                                    },
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'app.shell.notifications.subtitle',
                                            )
                                        }}
                                    </p>
                                </div>
                                <DropdownMenuSeparator />
                                <div
                                    class="flex items-center justify-between gap-2 px-2 py-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 rounded-full px-3 text-xs"
                                        :disabled="
                                            unreadNotificationsCount === 0 ||
                                            isMarkingAllNotificationsRead
                                        "
                                        @click="markAllNotificationsAsRead"
                                    >
                                        {{
                                            t(
                                                'app.shell.notifications.markAllAsRead',
                                            )
                                        }}
                                    </Button>
                                    <Link
                                        :href="notificationInbox.index_url"
                                        class="text-xs font-medium text-sky-700 transition hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                                        prefetch
                                    >
                                        {{
                                            t('app.shell.notifications.viewAll')
                                        }}
                                    </Link>
                                </div>
                                <div v-if="!hasNotifications" class="px-2 pb-2">
                                    <div
                                        class="rounded-2xl border border-dashed border-slate-300/80 bg-slate-50/80 px-4 py-6 text-center dark:border-slate-700 dark:bg-slate-900/60"
                                    >
                                        <div
                                            class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-200/80 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            <Inbox class="size-5" />
                                        </div>
                                        <p
                                            class="mt-3 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                t(
                                                    'app.shell.notifications.empty.title',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'app.shell.notifications.empty.description',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <TransitionGroup
                                    v-else
                                    tag="div"
                                    class="max-h-[28rem] space-y-2 overflow-y-auto p-2"
                                    enter-active-class="transition duration-200 ease-out"
                                    enter-from-class="translate-y-2 opacity-0"
                                    enter-to-class="translate-y-0 opacity-100"
                                    leave-active-class="transition duration-200 ease-in"
                                    leave-from-class="translate-y-0 opacity-100"
                                    leave-to-class="-translate-y-1 opacity-0"
                                >
                                    <div
                                        v-for="notification in unreadPreviewNotifications"
                                        :key="notification.uuid"
                                        class="rounded-2xl border p-3 transition"
                                        :class="
                                            notificationSurfaceClass(
                                                notification,
                                            )
                                        "
                                    >
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl"
                                                :class="
                                                    notificationAccentClass(
                                                        notification,
                                                    )
                                                "
                                            >
                                                <component
                                                    :is="
                                                        notificationIcon(
                                                            notification,
                                                        )
                                                    "
                                                    class="size-4"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex items-start justify-between gap-3"
                                                >
                                                    <div class="min-w-0">
                                                        <div
                                                            class="flex flex-wrap items-center gap-2"
                                                        >
                                                            <p
                                                                class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                                            >
                                                                {{
                                                                    notification
                                                                        .content
                                                                        .title
                                                                }}
                                                            </p>
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
                                                            class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                                        >
                                                            {{
                                                                notification
                                                                    .content
                                                                    .message
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div
                                                        class="flex shrink-0 items-center gap-2 pl-2"
                                                    >
                                                        <span
                                                            class="h-2.5 w-2.5 rounded-full"
                                                            :class="
                                                                notification.is_unread
                                                                    ? 'bg-sky-500'
                                                                    : 'bg-slate-300 dark:bg-slate-600'
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                                <div
                                                    class="mt-3 flex flex-wrap items-center justify-between gap-2"
                                                >
                                                    <p
                                                        class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            formatNotificationDate(
                                                                notification.created_at,
                                                            )
                                                        }}
                                                    </p>
                                                    <div
                                                        class="flex flex-wrap items-center gap-2"
                                                    >
                                                        <Button
                                                            v-if="
                                                                notification
                                                                    .content
                                                                    .cta_url
                                                            "
                                                            variant="secondary"
                                                            size="sm"
                                                            class="h-8 rounded-full px-3 text-xs"
                                                            :disabled="
                                                                activeNotificationUuid ===
                                                                notification.uuid
                                                            "
                                                            @click="
                                                                markNotificationAsRead(
                                                                    notification,
                                                                    true,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                notification
                                                                    .content
                                                                    .cta_label ||
                                                                t(
                                                                    'app.shell.notifications.openItem',
                                                                )
                                                            }}
                                                        </Button>
                                                        <Button
                                                            v-if="
                                                                notification.is_unread
                                                            "
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-8 rounded-full px-3 text-xs"
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
                                                                    'app.shell.notifications.markAsRead',
                                                                )
                                                            }}
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </TransitionGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    class="h-12 rounded-2xl border border-white/70 bg-white/90 px-2.5 shadow-[0_16px_45px_-28px_rgba(15,23,42,0.65)] transition hover:border-white hover:bg-white dark:border-slate-700/80 dark:bg-slate-950/85 dark:hover:border-slate-600 dark:hover:bg-slate-900"
                                    :aria-label="t('app.shell.userMenu.open')"
                                >
                                    <div class="relative shrink-0">
                                        <Avatar
                                            class="h-9 w-9 overflow-hidden rounded-2xl shadow-sm ring-1 ring-slate-200/80 ring-offset-2 ring-offset-white dark:ring-slate-700/80 dark:ring-offset-slate-950"
                                        >
                                            <AvatarImage
                                                :src="auth.user.avatar ?? ''"
                                                :alt="auth.user.name"
                                            />
                                            <AvatarFallback
                                                class="rounded-2xl bg-gradient-to-br from-sky-500 via-cyan-500 to-emerald-500 text-sm font-semibold text-white"
                                            >
                                                {{
                                                    getInitials(auth.user.name)
                                                }}
                                            </AvatarFallback>
                                        </Avatar>
                                    </div>
                                    <div
                                        class="hidden min-w-0 text-left md:grid"
                                    >
                                        <span
                                            class="truncate text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t('app.shell.userMenu.account')
                                            }}
                                        </span>
                                        <span
                                            class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ auth.user.name }}
                                        </span>
                                    </div>
                                    <ChevronDown
                                        class="hidden size-4 text-slate-400 md:block"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="w-64 rounded-2xl"
                            >
                                <UserMenuContent :user="auth.user" />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            <div class="px-5 py-5">
                <div class="space-y-4">
                    <div
                        v-if="isInfoExpanded"
                        class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end"
                    >
                        <div class="min-w-0 space-y-3">
                            <div
                                class="inline-flex items-center rounded-full border border-white/70 bg-white/80 px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-slate-500 uppercase dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-400"
                            >
                                {{ pageTitle }}
                            </div>
                            <div class="space-y-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50"
                                >
                                    {{ pageTitle }}
                                </h1>
                                <p
                                    class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{ pageDescription }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2 xl:justify-end"
                        >
                            <Badge
                                v-for="chip in statusChips"
                                :key="chip.key"
                                variant="secondary"
                                class="rounded-full border border-slate-200/80 bg-white/85 px-3 py-1 text-xs text-slate-700 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-200"
                            >
                                <span
                                    class="mr-1.5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ chip.label }}
                                </span>
                                <span class="font-semibold">{{
                                    chip.value
                                }}</span>
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
