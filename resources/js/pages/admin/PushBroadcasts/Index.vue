<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    Filter,
    SendHorizontal,
    Shield,
    UserRoundCheck,
    UserRoundX,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import AppToastStack from '@/components/ui/AppToastStack.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useToastFeedback } from '@/composables/useToastFeedback';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as pushBroadcastsIndex,
    store as storePushBroadcast,
} from '@/routes/admin/push-broadcasts';
import { store as storePushReminder } from '@/routes/admin/push-broadcasts/reminders';
import type {
    AdminPushBroadcastHistoryItem,
    AdminPushBroadcastsPageProps,
    AdminPushUserItem,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminPushBroadcastsPageProps>();
const page = usePage();
const { t, locale } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    { title: t('admin.sections.pushBroadcasts'), href: pushBroadcastsIndex() },
];

const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);
const { feedback, showFeedback } = useToastFeedback();

const historyFilters = useForm({
    history_search: props.filters.history_search,
    history_type: props.filters.history_type,
    history_status: props.filters.history_status,
    history_date: props.filters.history_date,
    active_search: props.filters.active_search,
    inactive_search: props.filters.inactive_search,
});

const broadcastForm = useForm({
    title: '',
    body: '',
    url: '',
    target_mode: 'all',
    target_user_uuid: '',
});

const reminderForm = useForm({
    user_uuid: '',
});

const selectedTargetUser = ref<AdminPushUserItem | null>(null);

watch(
    flash,
    (currentFlash) => {
        if (currentFlash.success) {
            showFeedback({
                variant: 'default',
                title: t('admin.pushBroadcasts.title'),
                message: currentFlash.success,
            });
        }
    },
    { immediate: true, deep: true },
);

watch(
    pageErrors,
    (errors) => {
        const message =
            errors.title ?? errors.body ?? errors.target_user_uuid ?? errors.user_uuid;

        if (message) {
            showFeedback({
                variant: 'destructive',
                title: t('admin.pushBroadcasts.title'),
                message,
            });
        }
    },
    { immediate: true, deep: true },
);

watch(
    () => broadcastForm.target_mode,
    (targetMode) => {
        if (targetMode === 'all') {
            selectedTargetUser.value = null;
            broadcastForm.target_user_uuid = '';
        }
    },
);

function submitHistoryFilters(): void {
    router.get(
        pushBroadcastsIndex.url({
            query: {
                history_search:
                    historyFilters.history_search.trim() === ''
                        ? null
                        : historyFilters.history_search.trim(),
                history_type:
                    historyFilters.history_type === 'all'
                        ? null
                        : historyFilters.history_type,
                history_status:
                    historyFilters.history_status === 'all'
                        ? null
                        : historyFilters.history_status,
                history_date:
                    historyFilters.history_date === ''
                        ? null
                        : historyFilters.history_date,
                active_search:
                    historyFilters.active_search.trim() === ''
                        ? null
                        : historyFilters.active_search.trim(),
                inactive_search:
                    historyFilters.inactive_search.trim() === ''
                        ? null
                        : historyFilters.inactive_search.trim(),
            },
        }),
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function resetHistoryFilters(): void {
    historyFilters.history_search = '';
    historyFilters.history_type = 'all';
    historyFilters.history_status = 'all';
    historyFilters.history_date = '';
    historyFilters.active_search = '';
    historyFilters.inactive_search = '';
    submitHistoryFilters();
}

function selectTargetUser(user: AdminPushUserItem): void {
    selectedTargetUser.value = user;
    broadcastForm.target_mode = 'single';
    broadcastForm.target_user_uuid = user.uuid;
}

function clearTargetUser(): void {
    selectedTargetUser.value = null;
    broadcastForm.target_mode = 'all';
    broadcastForm.target_user_uuid = '';
}

function submitBroadcast(): void {
    broadcastForm.post(storePushBroadcast().url, {
        preserveScroll: true,
        onSuccess: () => {
            broadcastForm.reset('title', 'body', 'url');
            clearTargetUser();
        },
    });
}

function sendReminder(user: AdminPushUserItem): void {
    reminderForm.transform(() => ({
        user_uuid: user.uuid,
    })).post(storePushReminder().url, {
        preserveScroll: true,
        onSuccess: () => {
            reminderForm.reset();
        },
    });
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale.value === 'it' ? 'it-IT' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function statusBadgeClass(status: string): string {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    if (status === 'completed_with_failures') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
    }

    if (status === 'failed') {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300';
    }

    if (status === 'sending') {
        return 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300';
    }

    return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
}

function userStatusBadgeClass(status: string | undefined): string {
    if (status === 'eligible') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    if (status === 'disabled_in_preferences') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
    }

    return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
}

function historyTypeLabel(item: AdminPushBroadcastHistoryItem): string {
    return t(`admin.pushBroadcasts.filters.types.${item.target_mode}`);
}

function historyFilterTypeLabel(value: string): string {
    return t(`admin.pushBroadcasts.filters.types.${value}`);
}

function historyFilterStatusLabel(value: string): string {
    if (value === 'all') {
        return t('admin.pushBroadcasts.filters.statuses.all');
    }

    return t(`admin.pushBroadcasts.statuses.${value}`);
}

function historyTargetLabel(item: AdminPushBroadcastHistoryItem): string {
    if (item.target_mode === 'single_user') {
        return item.target_label;
    }

    return t('admin.pushBroadcasts.targets.allEligibleUsers');
}

function historySummary(item: AdminPushBroadcastHistoryItem): string {
    return `${item.sent_count} / ${item.target_tokens_count}`;
}

function visitPage(url: string | null): void {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.pushBroadcasts.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-[linear-gradient(110deg,rgba(14,165,233,0.12),rgba(59,130,246,0.08),rgba(16,185,129,0.1))] px-8 py-7 dark:border-slate-800"
                    >
                        <Heading
                            variant="small"
                            :title="t('admin.pushBroadcasts.title')"
                            :description="t('admin.pushBroadcasts.description')"
                        />
                    </div>

                    <div class="space-y-6 px-8 py-8">
                        <AppToastStack :items="[feedback]" />

                        <div class="grid gap-4 xl:grid-cols-4">
                            <Card class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800">
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        {{ t('admin.pushBroadcasts.audience.eligibleUsers') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="pt-0 text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {{ props.audience.eligible_users_count }}
                                </CardContent>
                            </Card>

                            <Card class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800">
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        {{ t('admin.pushBroadcasts.audience.targetTokens') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="pt-0 text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {{ props.audience.target_tokens_count }}
                                </CardContent>
                            </Card>

                            <Card class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800">
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        {{ t('admin.pushBroadcasts.audience.activeUsers') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="pt-0 text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {{ props.audience.users_with_active_tokens_count }}
                                </CardContent>
                            </Card>

                            <Card class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800">
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                        {{ t('admin.pushBroadcasts.audience.inactiveUsers') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="pt-0 text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {{ props.audience.users_without_active_push_count }}
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                            <Card class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800">
                                <CardHeader class="space-y-1">
                                    <CardTitle class="text-lg">
                                        {{ t('admin.pushBroadcasts.form.sectionTitle') }}
                                    </CardTitle>
                                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                        {{ t('admin.pushBroadcasts.form.sectionDescription') }}
                                    </p>
                                </CardHeader>
                                <CardContent class="space-y-5">
                                    <div class="grid gap-5 lg:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label for="push-title">
                                                {{ t('admin.pushBroadcasts.form.title') }}
                                            </Label>
                                            <Input
                                                id="push-title"
                                                v-model="broadcastForm.title"
                                                data-test="push-broadcast-title"
                                            />
                                            <InputError :message="broadcastForm.errors.title" />
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="push-url">
                                                {{ t('admin.pushBroadcasts.form.url') }}
                                            </Label>
                                            <Input
                                                id="push-url"
                                                v-model="broadcastForm.url"
                                                data-test="push-broadcast-url"
                                            />
                                            <InputError :message="broadcastForm.errors.url" />
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="push-body">
                                            {{ t('admin.pushBroadcasts.form.body') }}
                                        </Label>
                                        <textarea
                                            id="push-body"
                                            v-model="broadcastForm.body"
                                            data-test="push-broadcast-body"
                                            class="min-h-32 w-full rounded-[0.9rem] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-600 dark:focus:ring-slate-800"
                                        />
                                        <InputError :message="broadcastForm.errors.body" />
                                    </div>

                                    <div class="grid gap-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
                                        <div class="space-y-2">
                                            <Label>{{ t('admin.pushBroadcasts.form.targetMode') }}</Label>
                                            <Select v-model="broadcastForm.target_mode">
                                                <SelectTrigger data-test="push-broadcast-target-mode">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">
                                                        {{ t('admin.pushBroadcasts.targetModes.all') }}
                                                    </SelectItem>
                                                    <SelectItem value="single">
                                                        {{ t('admin.pushBroadcasts.targetModes.single') }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div class="space-y-2">
                                            <Label>{{ t('admin.pushBroadcasts.form.selectedUser') }}</Label>
                                            <div
                                                class="rounded-[1rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/60"
                                            >
                                                <div
                                                    v-if="selectedTargetUser"
                                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <div>
                                                        <p class="font-medium text-slate-950 dark:text-slate-50">
                                                            {{ selectedTargetUser.name }}
                                                        </p>
                                                        <p class="text-slate-500 dark:text-slate-400">
                                                            {{ selectedTargetUser.email }}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        class="rounded-xl"
                                                        @click="clearTargetUser"
                                                    >
                                                        {{ t('admin.pushBroadcasts.actions.clearTarget') }}
                                                    </Button>
                                                </div>
                                                <p
                                                    v-else
                                                    class="text-slate-500 dark:text-slate-400"
                                                >
                                                    {{ t('admin.pushBroadcasts.form.selectedUserEmpty') }}
                                                </p>
                                            </div>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{
                                                    broadcastForm.target_mode === 'single'
                                                        ? t('admin.pushBroadcasts.form.targetHintSingle')
                                                        : t('admin.pushBroadcasts.form.targetHintAll')
                                                }}
                                            </p>
                                            <InputError :message="broadcastForm.errors.target_user_uuid" />
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <Button
                                            :disabled="broadcastForm.processing"
                                            class="h-11 rounded-xl px-5"
                                            data-test="push-broadcast-submit"
                                            @click="submitBroadcast"
                                        >
                                            <SendHorizontal class="mr-2 h-4 w-4" />
                                            {{ t('admin.pushBroadcasts.actions.queue') }}
                                        </Button>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ t('admin.pushBroadcasts.form.helper') }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800">
                                <CardHeader class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <Filter class="h-4 w-4 text-slate-500" />
                                        <CardTitle class="text-lg">
                                            {{ t('admin.pushBroadcasts.filters.title') }}
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent class="space-y-5">
                                    <div class="space-y-2">
                                        <Label for="history-search">
                                            {{ t('admin.pushBroadcasts.filters.search') }}
                                        </Label>
                                        <Input
                                            id="history-search"
                                            v-model="historyFilters.history_search"
                                            :placeholder="t('admin.pushBroadcasts.filters.searchPlaceholder')"
                                        />
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label>{{ t('admin.pushBroadcasts.filters.type') }}</Label>
                                            <Select v-model="historyFilters.history_type">
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="option in props.options.history_types"
                                                        :key="option.value"
                                                        :value="option.value"
                                                    >
                                                        {{ historyFilterTypeLabel(option.value) }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div class="space-y-2">
                                            <Label>{{ t('admin.pushBroadcasts.filters.status') }}</Label>
                                            <Select v-model="historyFilters.history_status">
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="option in props.options.history_statuses"
                                                        :key="option.value"
                                                        :value="option.value"
                                                    >
                                                        {{ historyFilterStatusLabel(option.value) }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="history-date">
                                            {{ t('admin.pushBroadcasts.filters.date') }}
                                        </Label>
                                        <Input
                                            id="history-date"
                                            v-model="historyFilters.history_date"
                                            type="date"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="active-search">
                                            {{ t('admin.pushBroadcasts.filters.activeUsersSearch') }}
                                        </Label>
                                        <Input
                                            id="active-search"
                                            v-model="historyFilters.active_search"
                                            :placeholder="t('admin.pushBroadcasts.filters.usersPlaceholder')"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="inactive-search">
                                            {{ t('admin.pushBroadcasts.filters.inactiveUsersSearch') }}
                                        </Label>
                                        <Input
                                            id="inactive-search"
                                            v-model="historyFilters.inactive_search"
                                            :placeholder="t('admin.pushBroadcasts.filters.usersPlaceholder')"
                                        />
                                    </div>

                                    <div class="flex flex-wrap gap-3">
                                        <Button class="rounded-xl" @click="submitHistoryFilters">
                                            {{ t('admin.pushBroadcasts.actions.applyFilters') }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            @click="resetHistoryFilters"
                                        >
                                            {{ t('admin.pushBroadcasts.actions.resetFilters') }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <Card class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800">
                                <CardHeader class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <UserRoundCheck class="h-4 w-4 text-emerald-600" />
                                        <CardTitle class="text-lg">
                                            {{ t('admin.pushBroadcasts.sections.activeUsers') }}
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        v-if="props.activePushUsers.data.length === 0"
                                        class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                                    >
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ t('admin.pushBroadcasts.activeUsers.empty') }}
                                        </p>
                                    </div>

                                    <div v-else class="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>{{ t('admin.pushBroadcasts.activeUsers.user') }}</TableHead>
                                                    <TableHead>{{ t('admin.pushBroadcasts.activeUsers.devices') }}</TableHead>
                                                    <TableHead>{{ t('admin.pushBroadcasts.activeUsers.lastSeen') }}</TableHead>
                                                    <TableHead>{{ t('admin.pushBroadcasts.activeUsers.eligibility') }}</TableHead>
                                                    <TableHead class="text-right">{{ t('admin.pushBroadcasts.activeUsers.actions') }}</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow
                                                    v-for="user in props.activePushUsers.data"
                                                    :key="user.uuid"
                                                >
                                                    <TableCell>
                                                        <div class="space-y-1">
                                                            <p class="font-medium text-slate-950 dark:text-slate-50">
                                                                {{ user.name }}
                                                            </p>
                                                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                                                {{ user.email }}
                                                            </p>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>{{ user.active_devices_count }}</TableCell>
                                                    <TableCell>{{ formatDateTime(user.last_seen_at) }}</TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            class="rounded-full px-3 py-1"
                                                            :class="userStatusBadgeClass(user.eligibility_status)"
                                                        >
                                                            {{
                                                                t(
                                                                    `admin.pushBroadcasts.userStatuses.${user.eligibility_status}`,
                                                                )
                                                            }}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell class="text-right">
                                                        <Button
                                                            variant="outline"
                                                            class="rounded-xl"
                                                            :disabled="!user.can_target_push"
                                                            @click="selectTargetUser(user)"
                                                        >
                                                            {{ t('admin.pushBroadcasts.actions.useAsTarget') }}
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>

                                    <div
                                        v-if="props.activePushUsers.meta.last_page > 1"
                                        class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800"
                                    >
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.activePushUsers.links.prev"
                                            @click="visitPage(props.activePushUsers.links.prev)"
                                        >
                                            {{ t('admin.pushBroadcasts.history.previous') }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.activePushUsers.links.next"
                                            @click="visitPage(props.activePushUsers.links.next)"
                                        >
                                            {{ t('admin.pushBroadcasts.history.next') }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800">
                                <CardHeader class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <UserRoundX class="h-4 w-4 text-amber-600" />
                                        <CardTitle class="text-lg">
                                            {{ t('admin.pushBroadcasts.sections.inactiveUsers') }}
                                        </CardTitle>
                                    </div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ t('admin.pushBroadcasts.inactiveUsers.helper') }}
                                    </p>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        v-if="props.inactivePushUsers.data.length === 0"
                                        class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                                    >
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ t('admin.pushBroadcasts.inactiveUsers.empty') }}
                                        </p>
                                    </div>

                                    <div v-else class="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>{{ t('admin.pushBroadcasts.inactiveUsers.user') }}</TableHead>
                                                    <TableHead>{{ t('admin.pushBroadcasts.inactiveUsers.devices') }}</TableHead>
                                                    <TableHead>{{ t('admin.pushBroadcasts.inactiveUsers.lastSeen') }}</TableHead>
                                                    <TableHead>{{ t('admin.pushBroadcasts.inactiveUsers.status') }}</TableHead>
                                                    <TableHead class="text-right">{{ t('admin.pushBroadcasts.inactiveUsers.actions') }}</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow
                                                    v-for="user in props.inactivePushUsers.data"
                                                    :key="user.uuid"
                                                >
                                                    <TableCell>
                                                        <div class="space-y-1">
                                                            <p class="font-medium text-slate-950 dark:text-slate-50">
                                                                {{ user.name }}
                                                            </p>
                                                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                                                {{ user.email }}
                                                            </p>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>{{ user.active_devices_count }}</TableCell>
                                                    <TableCell>{{ formatDateTime(user.last_seen_at) }}</TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            class="rounded-full px-3 py-1"
                                                            :class="userStatusBadgeClass(user.status)"
                                                        >
                                                            {{
                                                                t(
                                                                    `admin.pushBroadcasts.userStatuses.${user.status}`,
                                                                )
                                                            }}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell class="text-right">
                                                        <Button
                                                            variant="outline"
                                                            class="rounded-xl"
                                                            :disabled="reminderForm.processing"
                                                            @click="sendReminder(user)"
                                                        >
                                                            <Shield class="mr-2 h-4 w-4" />
                                                            {{ t('admin.pushBroadcasts.actions.sendReminder') }}
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>

                                    <div
                                        v-if="props.inactivePushUsers.meta.last_page > 1"
                                        class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800"
                                    >
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.inactivePushUsers.links.prev"
                                            @click="visitPage(props.inactivePushUsers.links.prev)"
                                        >
                                            {{ t('admin.pushBroadcasts.history.previous') }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.inactivePushUsers.links.next"
                                            @click="visitPage(props.inactivePushUsers.links.next)"
                                        >
                                            {{ t('admin.pushBroadcasts.history.next') }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800">
                            <CardHeader class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <BellRing class="h-4 w-4 text-slate-500" />
                                    <CardTitle class="text-lg">
                                        {{ t('admin.pushBroadcasts.sections.history') }}
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div
                                    v-if="props.broadcasts.data.length === 0"
                                    class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                                >
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ t('admin.pushBroadcasts.history.empty') }}
                                    </p>
                                </div>

                                <div v-else class="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.queuedAt') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.titleColumn') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.type') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.target') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.sent') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.failed') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.invalidated') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.author') }}</TableHead>
                                                <TableHead>{{ t('admin.pushBroadcasts.history.status') }}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow
                                                v-for="broadcast in props.broadcasts.data"
                                                :key="broadcast.uuid"
                                            >
                                                <TableCell class="align-top text-sm text-slate-500 dark:text-slate-400">
                                                    {{ formatDateTime(broadcast.queued_at) }}
                                                </TableCell>
                                                <TableCell class="align-top">
                                                    <div class="space-y-1">
                                                        <p class="font-medium text-slate-950 dark:text-slate-50">
                                                            {{ broadcast.title }}
                                                        </p>
                                                        <p class="max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                                                            {{ broadcast.body_snippet }}
                                                        </p>
                                                        <p
                                                            v-if="broadcast.url"
                                                            class="text-xs text-sky-700 dark:text-sky-300"
                                                        >
                                                            {{ broadcast.url }}
                                                        </p>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="align-top text-sm text-slate-600 dark:text-slate-300">
                                                    {{ historyTypeLabel(broadcast) }}
                                                </TableCell>
                                                <TableCell class="align-top">
                                                    <div class="space-y-1 text-sm">
                                                        <p class="font-medium text-slate-950 dark:text-slate-50">
                                                            {{ historyTargetLabel(broadcast) }}
                                                        </p>
                                                        <p class="text-slate-500 dark:text-slate-400">
                                                            {{ broadcast.target_users_count }} users / {{ broadcast.target_tokens_count }} devices
                                                        </p>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="align-top text-sm text-slate-600 dark:text-slate-300">
                                                    {{ historySummary(broadcast) }}
                                                </TableCell>
                                                <TableCell class="align-top text-sm text-slate-600 dark:text-slate-300">
                                                    {{ broadcast.failed_count }}
                                                </TableCell>
                                                <TableCell class="align-top text-sm text-slate-600 dark:text-slate-300">
                                                    {{ broadcast.invalidated_count }}
                                                </TableCell>
                                                <TableCell class="align-top">
                                                    <div class="space-y-1 text-sm">
                                                        <p class="font-medium text-slate-950 dark:text-slate-50">
                                                            {{ broadcast.creator?.name ?? '—' }}
                                                        </p>
                                                        <p class="text-slate-500 dark:text-slate-400">
                                                            {{ broadcast.creator?.email ?? '' }}
                                                        </p>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="align-top">
                                                    <Badge
                                                        class="rounded-full px-3 py-1"
                                                        :class="statusBadgeClass(broadcast.status)"
                                                    >
                                                        {{ t(`admin.pushBroadcasts.statuses.${broadcast.status}`) }}
                                                    </Badge>
                                                    <p
                                                        v-if="broadcast.error_message"
                                                        class="mt-2 max-w-xs text-xs leading-5 text-rose-600 dark:text-rose-400"
                                                    >
                                                        {{ broadcast.error_message }}
                                                    </p>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>

                                <div
                                    v-if="props.broadcasts.meta.last_page > 1"
                                    class="flex flex-col gap-3 border-t border-slate-200 pt-4 md:flex-row md:items-center md:justify-between dark:border-slate-800"
                                >
                                    <p class="text-sm text-slate-600 dark:text-slate-300">
                                        {{
                                            t('admin.pushBroadcasts.history.page', {
                                                current: props.broadcasts.meta.current_page,
                                                last: props.broadcasts.meta.last_page,
                                            })
                                        }}
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.broadcasts.links.prev"
                                            @click="visitPage(props.broadcasts.links.prev)"
                                        >
                                            {{ t('admin.pushBroadcasts.history.previous') }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.broadcasts.links.next"
                                            @click="visitPage(props.broadcasts.links.next)"
                                        >
                                            {{ t('admin.pushBroadcasts.history.next') }}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
