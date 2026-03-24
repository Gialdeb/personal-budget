<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    ChevronDown,
    CircleDollarSign,
    FileUp,
    LayoutGrid,
    Plus,
    ScrollText,
    Settings2,
    SlidersHorizontal,
    Tags,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar';
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
import { edit as trackedItems } from '@/routes/tracked-items';
import { show as transactionsShow } from '@/routes/transactions';
import type { Auth, BreadcrumbItem, TransactionsNavigation } from '@/types';

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

const notifications = computed(() => [
    {
        id: 'period',
        title: t('app.shell.notifications.items.periodTitle'),
        description: t('app.shell.notifications.items.periodDescription'),
        tone: 'bg-sky-500/10 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    },
    {
        id: 'preferences',
        title: t('app.shell.notifications.items.preferencesTitle'),
        description: t('app.shell.notifications.items.preferencesDescription'),
        tone: 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
]);

const unreadNotificationsCount = computed(() => notifications.value.length);

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

    if (currentSection.value === 'settings' || currentSection.value === 'accounts' || currentSection.value === 'references') {
        return Settings2;
    }

    return LayoutGrid;
});

const headerToneClass = computed(() =>
    currentSection.value === 'transactions' || currentSection.value === 'planning'
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

function toggleInfoPanel(): void {
    isInfoExpanded.value = !isInfoExpanded.value;
}
</script>

<template>
    <header class="shrink-0 px-4 pt-4 md:px-6 md:pt-6">
        <div
            class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-br shadow-[0_30px_80px_-56px_rgba(15,23,42,0.6)] backdrop-blur-sm dark:border-slate-800"
            :class="headerToneClass"
        >
            <div class="border-b border-slate-200/70 px-5 py-3 dark:border-slate-800/80">
                <div class="flex flex-wrap items-start justify-between gap-3 md:flex-nowrap md:items-center">
                    <div class="flex min-w-0 flex-1 items-start gap-3 sm:items-center">
                        <SidebarTrigger
                            class="rounded-xl border border-slate-200/80 bg-white/90 shadow-sm transition hover:bg-white dark:border-slate-800 dark:bg-slate-950/80 dark:hover:bg-slate-900"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                                <component :is="sectionIcon" class="size-3.5 shrink-0" />
                                <span class="truncate">{{ t('app.shell.headerContext') }}</span>
                            </div>
                            <div
                                v-if="props.breadcrumbs.length > 0"
                                class="mt-1 hidden min-w-0 sm:block"
                            >
                                <Breadcrumbs :breadcrumbs="breadcrumbs" />
                            </div>
                        </div>
                    </div>

                    <div class="ml-auto flex shrink-0 items-center gap-2 self-start sm:self-center">
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
                                    :aria-label="t('app.shell.openQuickActions')"
                                >
                                    <SlidersHorizontal class="size-4 sm:mr-2" />
                                    <span class="hidden sm:inline">
                                        {{ t('app.shell.quickActions') }}
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-64 rounded-2xl">
                                <DropdownMenuLabel class="text-xs tracking-[0.14em] text-slate-500 uppercase">
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
                                        <component :is="action.icon" class="mr-2 size-4" />
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
                                    :aria-label="t('app.shell.notifications.open')"
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
                            <DropdownMenuContent align="end" class="w-80 rounded-2xl p-2">
                                <div class="px-2 py-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                            {{ t('app.shell.notifications.title') }}
                                        </p>
                                        <Badge variant="secondary" class="rounded-full">
                                            {{ t('app.shell.notifications.unread', { count: unreadNotificationsCount }) }}
                                        </Badge>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ t('app.shell.notifications.subtitle') }}
                                    </p>
                                </div>
                                <DropdownMenuSeparator />
                                <div class="space-y-2 p-2">
                                    <div
                                        v-for="notification in notifications"
                                        :key="notification.id"
                                        class="rounded-2xl border border-slate-200/80 bg-white/90 p-3 dark:border-slate-800 dark:bg-slate-950/80"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-950 dark:text-slate-50">
                                                    {{ notification.title }}
                                                </p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                                    {{ notification.description }}
                                                </p>
                                            </div>
                                            <span
                                                class="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                                :class="notification.tone"
                                            />
                                        </div>
                                    </div>
                                </div>
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
                                        <Avatar class="h-9 w-9 overflow-hidden rounded-2xl ring-1 ring-slate-200/80 ring-offset-2 ring-offset-white shadow-sm dark:ring-slate-700/80 dark:ring-offset-slate-950">
                                            <AvatarImage :src="auth.user.avatar ?? ''" :alt="auth.user.name" />
                                            <AvatarFallback class="rounded-2xl bg-gradient-to-br from-sky-500 via-cyan-500 to-emerald-500 text-sm font-semibold text-white">
                                                {{ getInitials(auth.user.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                    </div>
                                    <div class="hidden min-w-0 text-left md:grid">
                                        <span class="truncate text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                            {{ t('app.shell.userMenu.account') }}
                                        </span>
                                        <span class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50">
                                            {{ auth.user.name }}
                                        </span>
                                    </div>
                                    <ChevronDown class="hidden size-4 text-slate-400 md:block" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-64 rounded-2xl">
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
                                <p class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {{ pageDescription }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                            <Badge
                                v-for="chip in statusChips"
                                :key="chip.key"
                                variant="secondary"
                                class="rounded-full border border-slate-200/80 bg-white/85 px-3 py-1 text-xs text-slate-700 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-200"
                            >
                                <span class="mr-1.5 text-slate-500 dark:text-slate-400">
                                    {{ chip.label }}
                                </span>
                                <span class="font-semibold">{{ chip.value }}</span>
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
