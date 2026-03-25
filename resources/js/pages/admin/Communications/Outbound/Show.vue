<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import { index as outboundIndex } from '@/routes/admin/communications/outbound';
import type {
    AdminCommunicationOutboundShowPageProps,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminCommunicationOutboundShowPageProps>();
const { t } = useI18n();

const contentFields = computed<
    Array<{
        key: keyof AdminCommunicationOutboundShowPageProps['outboundMessage']['content'];
        label: string;
    }>
>(() => [
    {
        key: 'subject',
        label: t('admin.communicationOutbound.detail.labels.subject'),
    },
    {
        key: 'title',
        label: t('admin.communicationOutbound.detail.labels.title'),
    },
    {
        key: 'body',
        label: t('admin.communicationOutbound.detail.labels.body'),
    },
    {
        key: 'cta_label',
        label: t('admin.communicationOutbound.detail.labels.ctaLabel'),
    },
    {
        key: 'cta_url',
        label: t('admin.communicationOutbound.detail.labels.ctaUrl'),
    },
]);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    { title: t('admin.sections.communicationOutbound'), href: outboundIndex() },
    { title: t('admin.communicationOutbound.breadcrumbDetail'), href: outboundIndex() },
];

const summaryItems = computed(() => [
    { label: t('admin.communicationOutbound.detail.labels.uuid'), value: props.outboundMessage.uuid },
    { label: t('admin.communicationOutbound.detail.labels.createdAt'), value: props.outboundMessage.created_at },
    { label: t('admin.communicationOutbound.detail.labels.queuedAt'), value: props.outboundMessage.queued_at },
    { label: t('admin.communicationOutbound.detail.labels.sentAt'), value: props.outboundMessage.sent_at },
    { label: t('admin.communicationOutbound.detail.labels.failedAt'), value: props.outboundMessage.failed_at },
    { label: t('admin.communicationOutbound.detail.labels.channel'), value: props.outboundMessage.channel_label },
    { label: t('admin.communicationOutbound.detail.labels.status'), value: props.outboundMessage.status_label },
    { label: t('admin.communicationOutbound.detail.labels.category'), value: props.outboundMessage.category.name ?? props.outboundMessage.category.key },
    { label: t('admin.communicationOutbound.detail.labels.template'), value: props.outboundMessage.template?.name ?? null },
    { label: t('admin.communicationOutbound.detail.labels.recipient'), value: props.outboundMessage.recipient?.label ?? null },
    { label: t('admin.communicationOutbound.detail.labels.context'), value: props.outboundMessage.context?.label ?? null },
    { label: t('admin.communicationOutbound.detail.labels.creator'), value: props.outboundMessage.creator?.label ?? null },
    { label: t('admin.communicationOutbound.detail.labels.error'), value: props.outboundMessage.error_message },
]);

function displayValue(value: string | null | undefined): string {
    return value && value.trim() !== ''
        ? value
        : t('admin.communicationOutbound.empty.noValue');
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.communicationOutbound.detail.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div class="border-b border-slate-200/70 px-6 py-6 dark:border-slate-800">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <Heading
                                variant="small"
                                :title="t('admin.communicationOutbound.detail.title')"
                                :description="t('admin.communicationOutbound.detail.description')"
                            />
                            <div class="flex flex-wrap gap-2">
                                <Badge :class="['rounded-full border px-3 py-1 text-[11px] uppercase', statusClass(props.outboundMessage.status)]">
                                    {{ props.outboundMessage.status_label }}
                                </Badge>
                                <Badge class="rounded-full border px-3 py-1 text-[11px] uppercase">
                                    {{ props.outboundMessage.channel_label }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <div class="flex flex-wrap gap-3">
                            <Button variant="outline" class="rounded-xl" as-child>
                                <Link :href="outboundIndex()">
                                    {{ t('admin.communicationOutbound.actions.backToOutbound') }}
                                </Link>
                            </Button>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                            <Card class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800">
                                <CardHeader>
                                    <CardTitle>{{ t('admin.communicationOutbound.detail.sections.summary') }}</CardTitle>
                                </CardHeader>
                                <CardContent class="grid gap-4 md:grid-cols-2">
                                    <div
                                        v-for="item in summaryItems"
                                        :key="item.label"
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/50"
                                    >
                                        <p class="text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                            {{ item.label }}
                                        </p>
                                        <p class="mt-2 text-sm leading-6 text-slate-950 dark:text-slate-50">
                                            {{ displayValue(item.value) }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800">
                                <CardHeader>
                                    <CardTitle>{{ t('admin.communicationOutbound.detail.sections.content') }}</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        v-for="field in contentFields"
                                        :key="field.key"
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/50"
                                    >
                                        <p class="text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                            {{ field.label }}
                                        </p>
                                        <p class="mt-2 text-sm leading-6 whitespace-pre-wrap text-slate-950 dark:text-slate-50">
                                            {{ displayValue(props.outboundMessage.content[field.key]) }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800">
                            <CardHeader>
                                <CardTitle>{{ t('admin.communicationOutbound.detail.sections.payload') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 text-xs leading-6 text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-200">{{ JSON.stringify(props.outboundMessage.payload_snapshot ?? {}, null, 2) }}</pre>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
