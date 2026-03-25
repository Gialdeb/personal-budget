<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, CalendarRange, Mailbox, Search } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import { index as outboundIndex, show as outboundShow } from '@/routes/admin/communications/outbound';
import type {
    AdminCommunicationOutboundIndexPageProps,
    AdminOutboundItem,
    AdminUserFilterValue,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminCommunicationOutboundIndexPageProps>();
const { t } = useI18n();

const page = usePage();
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    { title: t('admin.sections.communicationOutbound'), href: outboundIndex() },
];

const search = ref(props.filters.search);
const selectedStatus = ref(props.filters.status ?? 'all');
const selectedChannel = ref(props.filters.channel ?? 'all');
const selectedCategory = ref(props.filters.category ?? 'all');
const recipientSearch = ref(props.filters.recipient);
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
let filterTimeout: ReturnType<typeof setTimeout> | null = null;

const statusOptions = computed<AdminUserFilterValue[]>(() => [
    { value: 'all', label: t('admin.communicationOutbound.filters.statusPlaceholder') },
    ...props.options.statuses.map((status) => ({
        value: status,
        label: t(`admin.communicationOutbound.statuses.${status}`),
    })),
]);

const channelOptions = computed<AdminUserFilterValue[]>(() => [
    { value: 'all', label: t('admin.communicationOutbound.filters.channelPlaceholder') },
    ...props.options.channels.map((channel) => ({
        value: channel,
        label: t(`admin.communicationOutbound.channels.${channel}`),
    })),
]);

const categoryOptions = computed<AdminUserFilterValue[]>(() => [
    { value: 'all', label: t('admin.communicationOutbound.filters.categoryPlaceholder') },
    ...props.options.categories,
]);

const feedback = computed(() => {
    const firstError = Object.values(pageErrors.value)[0];

    if (!firstError) {
        return null;
    }

    return {
        title: t('admin.communicationOutbound.feedback.errorTitle'),
        message: firstError,
    };
});

const listSummary = computed(() => {
    if (props.outboundMessages.meta.total === 0) {
        return t('admin.communicationOutbound.list.emptySummary');
    }

    return t('admin.communicationOutbound.list.summary', {
        from: props.outboundMessages.meta.from ?? 0,
        to: props.outboundMessages.meta.to ?? 0,
        total: props.outboundMessages.meta.total,
    });
});

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        selectedStatus.value = filters.status ?? 'all';
        selectedChannel.value = filters.channel ?? 'all';
        selectedCategory.value = filters.category ?? 'all';
        recipientSearch.value = filters.recipient;
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
    },
    { deep: true },
);

watch(
    [search, selectedStatus, selectedChannel, selectedCategory, recipientSearch, dateFrom, dateTo],
    () => {
        if (filterTimeout) {
            clearTimeout(filterTimeout);
        }

        filterTimeout = setTimeout(() => {
            router.get(
                outboundIndex.url({
                    query: {
                        search: search.value.trim() === '' ? null : search.value.trim(),
                        status: selectedStatus.value === 'all' ? null : selectedStatus.value,
                        channel: selectedChannel.value === 'all' ? null : selectedChannel.value,
                        category: selectedCategory.value === 'all' ? null : selectedCategory.value,
                        recipient: recipientSearch.value.trim() === '' ? null : recipientSearch.value.trim(),
                        date_from: dateFrom.value === '' ? null : dateFrom.value,
                        date_to: dateTo.value === '' ? null : dateTo.value,
                    },
                }),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }, 250);
    },
);

onUnmounted(() => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }
});

function resetFilters(): void {
    search.value = '';
    selectedStatus.value = 'all';
    selectedChannel.value = 'all';
    selectedCategory.value = 'all';
    recipientSearch.value = '';
    dateFrom.value = '';
    dateTo.value = '';
}

function statusClass(status: string | null): string {
    if (status === 'sent') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (status === 'failed') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300';
    }

    if (status === 'skipped') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
    }

    return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300';
}

function displayValue(value: string | null | undefined): string {
    return value && value.trim() !== ''
        ? value
        : t('admin.communicationOutbound.empty.noValue');
}

function recipientLine(item: AdminOutboundItem): string {
    if (!item.recipient) {
        return t('admin.communicationOutbound.empty.noValue');
    }

    return item.recipient.email
        ? `${item.recipient.label} · ${item.recipient.email}`
        : item.recipient.label;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.communicationOutbound.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-r from-slate-950/5 via-sky-500/10 to-emerald-500/10 px-6 py-6 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <Heading
                                variant="small"
                                :title="t('admin.communicationOutbound.title')"
                                :description="
                                    t('admin.communicationOutbound.description')
                                "
                            />
                            <Badge
                                class="rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-[11px] tracking-[0.18em] uppercase dark:border-slate-700 dark:bg-slate-900/80"
                            >
                                {{ listSummary }}
                            </Badge>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <Alert v-if="feedback" variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertTitle>{{ feedback.title }}</AlertTitle>
                            <AlertDescription>{{ feedback.message }}</AlertDescription>
                        </Alert>

                        <Card
                            class="rounded-[1.5rem] border-slate-200/80 bg-slate-50/70 shadow-none dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardHeader class="gap-2">
                                <CardTitle class="text-base">
                                    {{ t('admin.communicationOutbound.filters.title') }}
                                </CardTitle>
                                <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {{ t('admin.communicationOutbound.filters.description') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 xl:grid-cols-6">
                                    <div class="xl:col-span-2">
                                        <Label for="outbound-search">
                                            {{ t('admin.communicationOutbound.filters.searchLabel') }}
                                        </Label>
                                        <div class="relative mt-2">
                                            <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                            <Input
                                                id="outbound-search"
                                                v-model="search"
                                                class="pl-9"
                                                :placeholder="t('admin.communicationOutbound.filters.searchPlaceholder')"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Label>{{ t('admin.communicationOutbound.filters.statusLabel') }}</Label>
                                        <Select v-model="selectedStatus">
                                            <SelectTrigger class="mt-2">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="option in statusOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div>
                                        <Label>{{ t('admin.communicationOutbound.filters.channelLabel') }}</Label>
                                        <Select v-model="selectedChannel">
                                            <SelectTrigger class="mt-2">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="option in channelOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div>
                                        <Label>{{ t('admin.communicationOutbound.filters.categoryLabel') }}</Label>
                                        <Select v-model="selectedCategory">
                                            <SelectTrigger class="mt-2">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="option in categoryOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div>
                                        <Label for="outbound-recipient">
                                            {{ t('admin.communicationOutbound.filters.recipientLabel') }}
                                        </Label>
                                        <Input
                                            id="outbound-recipient"
                                            v-model="recipientSearch"
                                            class="mt-2"
                                            :placeholder="t('admin.communicationOutbound.filters.recipientPlaceholder')"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                                    <div>
                                        <Label for="outbound-date-from">
                                            {{ t('admin.communicationOutbound.filters.dateFromLabel') }}
                                        </Label>
                                        <Input id="outbound-date-from" v-model="dateFrom" class="mt-2" type="date" />
                                    </div>
                                    <div>
                                        <Label for="outbound-date-to">
                                            {{ t('admin.communicationOutbound.filters.dateToLabel') }}
                                        </Label>
                                        <Input id="outbound-date-to" v-model="dateTo" class="mt-2" type="date" />
                                    </div>
                                    <div class="flex items-end">
                                        <Button
                                            variant="outline"
                                            class="w-full rounded-xl"
                                            @click="resetFilters"
                                        >
                                            {{ t('admin.communicationOutbound.filters.reset') }}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="overflow-hidden rounded-[1.5rem] border-slate-200/80 bg-white/90 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <CardHeader class="gap-2">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <CardTitle class="text-base">
                                            {{ t('admin.communicationOutbound.list.title') }}
                                        </CardTitle>
                                        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {{ t('admin.communicationOutbound.list.description') }}
                                        </p>
                                    </div>
                                    <div class="hidden items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600 md:flex dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                        <CalendarRange class="h-4 w-4" />
                                        <span>{{ listSummary }}</span>
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent class="space-y-4">
                                <div
                                    v-if="props.outboundMessages.data.length === 0"
                                    class="rounded-[1.25rem] border border-dashed border-slate-300/90 bg-slate-50/80 p-6 text-center dark:border-slate-700 dark:bg-slate-900/50"
                                >
                                    <Mailbox class="mx-auto h-8 w-8 text-slate-400" />
                                    <h3 class="mt-3 text-sm font-semibold text-slate-950 dark:text-slate-50">
                                        {{ t('admin.communicationOutbound.empty.title') }}
                                    </h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        {{ t('admin.communicationOutbound.empty.description') }}
                                    </p>
                                </div>

                                <div v-else class="hidden overflow-x-auto lg:block">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                        <thead>
                                            <tr class="text-left text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.createdAt') }}</th>
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.category') }}</th>
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.recipient') }}</th>
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.channel') }}</th>
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.status') }}</th>
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.template') }}</th>
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.context') }}</th>
                                                <th class="px-3 py-3">{{ t('admin.communicationOutbound.table.error') }}</th>
                                                <th class="px-3 py-3 text-right">{{ t('admin.communicationOutbound.table.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-900">
                                            <tr
                                                v-for="message in props.outboundMessages.data"
                                                :key="message.uuid"
                                                class="align-top"
                                            >
                                                <td class="px-3 py-4 text-slate-600 dark:text-slate-300">
                                                    {{ displayValue(message.created_at) }}
                                                </td>
                                                <td class="px-3 py-4">
                                                    <div class="font-medium text-slate-950 dark:text-slate-50">
                                                        {{ displayValue(message.category.name) }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ displayValue(message.category.key) }}
                                                    </div>
                                                </td>
                                                <td class="px-3 py-4 text-slate-600 dark:text-slate-300">
                                                    {{ recipientLine(message) }}
                                                </td>
                                                <td class="px-3 py-4">
                                                    <Badge class="rounded-full border px-3 py-1 text-[11px] uppercase">
                                                        {{ message.channel_label }}
                                                    </Badge>
                                                </td>
                                                <td class="px-3 py-4">
                                                    <Badge
                                                        :class="['rounded-full border px-3 py-1 text-[11px] uppercase', statusClass(message.status)]"
                                                    >
                                                        {{ message.status_label }}
                                                    </Badge>
                                                </td>
                                                <td class="px-3 py-4 text-slate-600 dark:text-slate-300">
                                                    {{ displayValue(message.template?.name) }}
                                                </td>
                                                <td class="px-3 py-4 text-slate-600 dark:text-slate-300">
                                                    {{ displayValue(message.context?.label) }}
                                                </td>
                                                <td class="max-w-[18rem] px-3 py-4 text-slate-600 dark:text-slate-300">
                                                    <p v-if="message.error_message" class="line-clamp-3 text-rose-700 dark:text-rose-300">
                                                        {{ message.error_message }}
                                                    </p>
                                                    <span v-else>{{ t('admin.communicationOutbound.empty.noValue') }}</span>
                                                </td>
                                                <td class="px-3 py-4 text-right">
                                                    <Button variant="ghost" class="rounded-xl" as-child>
                                                        <Link :href="outboundShow({ outboundMessage: message.uuid })">
                                                            {{ t('admin.communicationOutbound.actions.open') }}
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-if="props.outboundMessages.data.length > 0" class="grid gap-4 lg:hidden">
                                    <Card
                                        v-for="message in props.outboundMessages.data"
                                        :key="message.uuid"
                                        class="rounded-[1.25rem] border-slate-200/80 shadow-none dark:border-slate-800"
                                    >
                                        <CardContent class="space-y-4 p-5">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                                        {{ displayValue(message.category.name) }}
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ recipientLine(message) }}
                                                    </p>
                                                </div>
                                                <Badge
                                                    :class="['rounded-full border px-3 py-1 text-[11px] uppercase', statusClass(message.status)]"
                                                >
                                                    {{ message.status_label }}
                                                </Badge>
                                            </div>

                                            <div class="grid gap-3 text-sm text-slate-600 dark:text-slate-300">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span>{{ t('admin.communicationOutbound.table.channel') }}</span>
                                                    <span class="font-medium text-slate-950 dark:text-slate-50">{{ message.channel_label }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-3">
                                                    <span>{{ t('admin.communicationOutbound.table.createdAt') }}</span>
                                                    <span class="font-medium text-slate-950 dark:text-slate-50">{{ displayValue(message.created_at) }}</span>
                                                </div>
                                                <div class="flex items-start justify-between gap-3">
                                                    <span>{{ t('admin.communicationOutbound.table.error') }}</span>
                                                    <span class="max-w-[14rem] text-right" :class="message.error_message ? 'text-rose-700 dark:text-rose-300' : ''">
                                                        {{ displayValue(message.error_message) }}
                                                    </span>
                                                </div>
                                            </div>

                                            <Button variant="outline" class="w-full rounded-xl" as-child>
                                                <Link :href="outboundShow({ outboundMessage: message.uuid })">
                                                    {{ t('admin.communicationOutbound.actions.open') }}
                                                    <ArrowRight class="ml-2 h-4 w-4" />
                                                </Link>
                                            </Button>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div
                                    v-if="props.outboundMessages.meta.last_page > 1"
                                    class="flex flex-col gap-3 border-t border-slate-200 pt-4 md:flex-row md:items-center md:justify-between dark:border-slate-800"
                                >
                                    <p class="text-sm text-slate-600 dark:text-slate-300">
                                        {{ t('admin.communicationOutbound.pagination.page', { current: props.outboundMessages.meta.current_page, last: props.outboundMessages.meta.last_page }) }}
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.outboundMessages.links.prev"
                                            @click="
                                                props.outboundMessages.links.prev &&
                                                    router.visit(props.outboundMessages.links.prev, { preserveScroll: true, preserveState: true })
                                            "
                                        >
                                            {{ t('admin.communicationOutbound.pagination.previous') }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            class="rounded-xl"
                                            :disabled="!props.outboundMessages.links.next"
                                            @click="
                                                props.outboundMessages.links.next &&
                                                    router.visit(props.outboundMessages.links.next, { preserveScroll: true, preserveState: true })
                                            "
                                        >
                                            {{ t('admin.communicationOutbound.pagination.next') }}
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
