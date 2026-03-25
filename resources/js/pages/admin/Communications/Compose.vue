<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Bell,
    CheckCircle2,
    CircleAlert,
    Mail,
    Search,
    SendHorizontal,
    Sparkles,
    UserPlus2,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import { index as communicationComposerIndex } from '@/routes/admin/communications/compose';
import type {
    AdminCommunicationComposerPageProps,
    AdminManualCommunicationCustomContent,
    AdminManualCommunicationDispatchResult,
    AdminManualCommunicationPreview,
    AdminManualCommunicationRecipient,
    BreadcrumbItem,
    ManualCommunicationChannel,
} from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<AdminCommunicationComposerPageProps>();
const { t } = useI18n();

const selectedCategoryUuid = ref(props.categories[0]?.uuid ?? '');
const selectedChannels = ref<ManualCommunicationChannel[]>([]);
const selectedLocale = ref(props.locale_options[0]?.value ?? 'recipient');
const selectedContentMode = ref<'template' | 'custom'>('template');
const recipientSearch = ref('');
const recipientResults = ref<AdminManualCommunicationRecipient[]>([]);
const selectedRecipients = ref<AdminManualCommunicationRecipient[]>([]);
const preview = ref<AdminManualCommunicationPreview | null>(null);
const sendResult = ref<AdminManualCommunicationDispatchResult | null>(null);
const feedback = ref<FeedbackState | null>(null);
const previewErrors = ref<Record<string, string>>({});
const isLoadingRecipients = ref(false);
const isLoadingPreview = ref(false);
const isSending = ref(false);
const customContent = ref<AdminManualCommunicationCustomContent>({
    subject: '',
    title: '',
    body: '',
    cta_label: '',
    cta_url: '',
});

let recipientSearchTimeout: ReturnType<typeof setTimeout> | null = null;
let previewTimeout: ReturnType<typeof setTimeout> | null = null;

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('admin.sections.overview'),
        href: adminIndex(),
    },
    {
        title: t('admin.sections.communicationComposer'),
        href: communicationComposerIndex(),
    },
];

const selectedCategory = computed(
    () =>
        props.categories.find(
            (category) => category.uuid === selectedCategoryUuid.value,
        ) ?? null,
);

const categoryOptions = computed(() =>
    props.categories.map((category) => ({
        value: category.uuid,
        label: category.name,
    })),
);

const localeOptions = computed(() =>
    props.locale_options.map((option) => ({
        value: option.value,
        label: option.label,
    })),
);

const availableChannels = computed(
    () => selectedCategory.value?.channel_options ?? [],
);

const selectedRecipientUuids = computed(() =>
    selectedRecipients.value.map((recipient) => recipient.uuid),
);

const canPreview = computed(
    () =>
        Boolean(selectedCategory.value) &&
        selectedChannels.value.length > 0 &&
        selectedRecipients.value.length > 0 &&
        (selectedContentMode.value === 'template' ||
            customContent.value.body.trim() !== ''),
);

const canSend = computed(
    () =>
        canPreview.value &&
        preview.value !== null &&
        !isLoadingPreview.value &&
        !isSending.value,
);

const previewCards = computed(() => preview.value?.previews ?? []);

const previewSampleLabel = computed(
    () =>
        preview.value?.sample_recipient.label ??
        t('admin.communicationComposer.empty.preview'),
);

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function setFeedback(state: FeedbackState | null): void {
    feedback.value = state;
}

function channelIcon(channel: ManualCommunicationChannel) {
    return channel === 'mail' ? Mail : Bell;
}

function toggleChannel(channel: ManualCommunicationChannel): void {
    if (
        !availableChannels.value.some(
            (availableChannel) =>
                availableChannel.value === channel &&
                !availableChannel.is_disabled,
        )
    ) {
        return;
    }

    if (selectedChannels.value.includes(channel)) {
        selectedChannels.value = selectedChannels.value.filter(
            (value) => value !== channel,
        );

        return;
    }

    selectedChannels.value = [...selectedChannels.value, channel];
}

function toggleRecipient(recipient: AdminManualCommunicationRecipient): void {
    if (selectedRecipients.value.some((entry) => entry.uuid === recipient.uuid)) {
        selectedRecipients.value = selectedRecipients.value.filter(
            (entry) => entry.uuid !== recipient.uuid,
        );

        return;
    }

    selectedRecipients.value = [...selectedRecipients.value, recipient];
}

function previewPayload() {
    return {
        category_uuid: selectedCategoryUuid.value,
        channels: selectedChannels.value,
        recipient_uuids: selectedRecipientUuids.value,
        locale: selectedLocale.value,
        content_mode: selectedContentMode.value,
        custom_content:
            selectedContentMode.value === 'custom' ? customContent.value : null,
    };
}

async function loadRecipients(): Promise<void> {
    isLoadingRecipients.value = true;

    try {
        const response = await fetch(
            `${props.recipient_lookup_url}?search=${encodeURIComponent(recipientSearch.value.trim())}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        const payload = (await response.json()) as {
            data?: AdminManualCommunicationRecipient[];
        };

        recipientResults.value = payload.data ?? [];
    } finally {
        isLoadingRecipients.value = false;
    }
}

async function loadPreview(): Promise<void> {
    if (!canPreview.value) {
        preview.value = null;
        previewErrors.value = {};

        return;
    }

    isLoadingPreview.value = true;
    previewErrors.value = {};

    try {
        const response = await fetch(props.preview_url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(previewPayload()),
        });

        const payload = (await response.json()) as {
            data?: AdminManualCommunicationPreview;
            errors?: Record<string, string[]>;
            message?: string;
        };

        if (!response.ok) {
            preview.value = null;
            previewErrors.value = Object.fromEntries(
                Object.entries(payload.errors ?? {}).map(([key, value]) => [
                    key,
                    value[0] ?? '',
                ]),
            );

            if (payload.message) {
                setFeedback({
                    variant: 'destructive',
                    title: t('admin.communicationComposer.feedback.errorTitle'),
                    message: payload.message,
                });
            }

            return;
        }

        preview.value = payload.data ?? null;
    } finally {
        isLoadingPreview.value = false;
    }
}

async function submit(): Promise<void> {
    if (!canSend.value) {
        return;
    }

    isSending.value = true;
    setFeedback(null);

    try {
        const response = await fetch(props.send_url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(previewPayload()),
        });

        const payload = (await response.json()) as {
            data?: AdminManualCommunicationDispatchResult;
            errors?: Record<string, string[]>;
            message?: string;
        };

        if (!response.ok) {
            previewErrors.value = Object.fromEntries(
                Object.entries(payload.errors ?? {}).map(([key, value]) => [
                    key,
                    value[0] ?? '',
                ]),
            );
            setFeedback({
                variant: 'destructive',
                title: t('admin.communicationComposer.feedback.errorTitle'),
                message:
                    payload.message ??
                    t('admin.communicationComposer.feedback.sendFailed'),
            });

            return;
        }

        sendResult.value = payload.data ?? null;
        setFeedback({
            variant: 'default',
            title: t('admin.communicationComposer.feedback.successTitle'),
            message:
                payload.message ??
                t('admin.communicationComposer.feedback.sent'),
        });
    } finally {
        isSending.value = false;
    }
}

watch(
    selectedCategory,
    (category) => {
        if (!category) {
            selectedChannels.value = [];

            return;
        }

        const supportedChannels = category.channel_options
            .filter((channel) => !channel.is_disabled)
            .map((channel) => channel.value);
        selectedChannels.value = selectedChannels.value.filter((channel) =>
            supportedChannels.includes(channel),
        );

        if (category.fixed_channel !== null) {
            selectedChannels.value = [category.fixed_channel];

            return;
        }

        if (selectedChannels.value.length === 0 && category.default_channel !== null) {
            selectedChannels.value = [category.default_channel];
        }
    },
    { immediate: true },
);

watch(
    recipientSearch,
    () => {
        if (recipientSearchTimeout) {
            clearTimeout(recipientSearchTimeout);
        }

        recipientSearchTimeout = setTimeout(() => {
            void loadRecipients();
        }, 250);
    },
    { immediate: false },
);

watch(
    [
        selectedCategoryUuid,
        selectedLocale,
        selectedContentMode,
        selectedRecipientUuids,
        selectedChannels,
        customContent,
    ],
    () => {
        sendResult.value = null;

        if (previewTimeout) {
            clearTimeout(previewTimeout);
        }

        previewTimeout = setTimeout(() => {
            void loadPreview();
        }, 250);
    },
    { deep: true, immediate: true },
);

onMounted(() => {
    void loadRecipients();
});

onUnmounted(() => {
    if (recipientSearchTimeout) {
        clearTimeout(recipientSearchTimeout);
    }

    if (previewTimeout) {
        clearTimeout(previewTimeout);
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.communicationComposer.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-r from-sky-500/10 via-emerald-500/10 to-amber-500/10 px-6 py-6 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                        >
                            <div class="space-y-3">
                                <Badge
                                    class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] tracking-[0.2em] text-sky-900 uppercase dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100"
                                >
                                    {{ t('admin.communicationComposer.eyebrow') }}
                                </Badge>
                                <Heading
                                    variant="small"
                                    :title="t('admin.communicationComposer.title')"
                                    :description="
                                        t('admin.communicationComposer.description')
                                    "
                                />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    class="rounded-2xl"
                                    as-child
                                >
                                    <Link :href="adminIndex()">
                                        {{
                                            t(
                                                'admin.communicationComposer.actions.backToAdmin',
                                            )
                                        }}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <Alert
                            v-if="feedback"
                            :variant="feedback.variant"
                            class="rounded-2xl"
                        >
                            <CircleAlert
                                v-if="feedback.variant === 'destructive'"
                                class="h-4 w-4"
                            />
                            <CheckCircle2 v-else class="h-4 w-4" />
                            <AlertTitle>{{ feedback.title }}</AlertTitle>
                            <AlertDescription>{{ feedback.message }}</AlertDescription>
                        </Alert>

                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                            <div class="space-y-6">
                                <Card class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800">
                                    <CardHeader class="space-y-1">
                                        <CardTitle>
                                            {{ t('admin.communicationComposer.sections.category') }}
                                        </CardTitle>
                                        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {{ t('admin.communicationComposer.sectionDescriptions.category') }}
                                        </p>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label for="category-select">
                                                    {{ t('admin.communicationComposer.fields.category') }}
                                                </Label>
                                                <SearchableSelect
                                                    id="category-select"
                                                    v-model="selectedCategoryUuid"
                                                    :options="categoryOptions"
                                                    :placeholder="t('admin.communicationComposer.placeholders.category')"
                                                    :search-placeholder="t('admin.communicationComposer.placeholders.searchCategory')"
                                                    :empty-label="t('admin.communicationComposer.empty.categories')"
                                                    trigger-class="border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
                                                />
                                                <InputError :message="previewErrors.category_uuid" />
                                            </div>

                                            <div class="space-y-2">
                                                <Label for="locale-select">
                                                    {{ t('admin.communicationComposer.fields.locale') }}
                                                </Label>
                                                <SearchableSelect
                                                    id="locale-select"
                                                    v-model="selectedLocale"
                                                    :options="localeOptions"
                                                    :placeholder="t('admin.communicationComposer.placeholders.locale')"
                                                    :search-placeholder="t('admin.communicationComposer.placeholders.searchLocale')"
                                                    :empty-label="t('admin.communicationComposer.empty.locales')"
                                                    trigger-class="border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
                                                />
                                                <InputError :message="previewErrors.locale" />
                                            </div>
                                        </div>

                                        <div v-if="selectedCategory" class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="space-y-1">
                                                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                                        {{ selectedCategory.name }}
                                                    </p>
                                                    <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                                        {{
                                                            selectedCategory.description ||
                                                            t('admin.communicationComposer.empty.noDescription')
                                                        }}
                                                    </p>
                                                </div>
                                                <Badge variant="secondary" class="rounded-full">
                                                    {{ selectedCategory.context_type }}
                                                </Badge>
                                            </div>
                                        </div>

                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <Label>{{ t('admin.communicationComposer.fields.channels') }}</Label>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ t('admin.communicationComposer.help.channels') }}
                                                </p>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <button
                                                    v-for="channel in availableChannels"
                                                    :key="channel.value"
                                                    type="button"
                                                    class="flex items-center justify-between rounded-2xl border px-4 py-3 text-left transition"
                                                    :class="
                                                        channel.is_disabled
                                                            ? 'cursor-not-allowed border-slate-200/80 bg-slate-50/70 text-slate-400 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-500'
                                                            : selectedChannels.includes(channel.value)
                                                            ? 'border-sky-400 bg-sky-50 text-sky-950 dark:border-sky-500/60 dark:bg-sky-500/10 dark:text-sky-50'
                                                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200'
                                                    "
                                                    :disabled="channel.is_disabled"
                                                    @click="toggleChannel(channel.value)"
                                                >
                                                    <div class="flex items-center gap-3">
                                                        <component :is="channelIcon(channel.value)" class="h-4 w-4" />
                                                        <span class="font-medium">{{ channel.label }}</span>
                                                    </div>
                                                    <Badge v-if="channel.is_fixed" variant="secondary" class="rounded-full">
                                                        {{ t('admin.communicationComposer.labels.fixed') }}
                                                    </Badge>
                                                    <Badge v-else-if="selectedChannels.includes(channel.value)" class="rounded-full">
                                                        {{ t('admin.communicationComposer.labels.selected') }}
                                                    </Badge>
                                                    <Badge v-else-if="channel.is_disabled" variant="outline" class="rounded-full">
                                                        {{ t('admin.communicationComposer.labels.unavailable') }}
                                                    </Badge>
                                                </button>
                                            </div>
                                            <InputError :message="previewErrors.channels ?? previewErrors['channels.0']" />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800">
                                    <CardHeader class="space-y-1">
                                        <CardTitle>
                                            {{ t('admin.communicationComposer.sections.recipient') }}
                                        </CardTitle>
                                        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {{ t('admin.communicationComposer.sectionDescriptions.recipient') }}
                                        </p>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="space-y-2">
                                            <Label for="recipient-search">
                                                {{ t('admin.communicationComposer.fields.searchRecipient') }}
                                            </Label>
                                            <div class="relative">
                                                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                                <Input
                                                    id="recipient-search"
                                                    v-model="recipientSearch"
                                                    class="h-11 rounded-2xl border-slate-200 bg-white pl-10 dark:border-slate-800 dark:bg-slate-950"
                                                    :placeholder="t('admin.communicationComposer.placeholders.searchRecipient')"
                                                />
                                            </div>
                                        </div>

                                        <div v-if="selectedRecipients.length > 0" class="space-y-3">
                                            <div class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                                                <Users class="h-4 w-4" />
                                                {{ t('admin.communicationComposer.labels.selectedRecipients', { count: selectedRecipients.length }) }}
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    v-for="recipient in selectedRecipients"
                                                    :key="recipient.uuid"
                                                    type="button"
                                                    class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                    @click="toggleRecipient(recipient)"
                                                >
                                                    {{ recipient.label }}
                                                </button>
                                            </div>
                                        </div>

                                        <div class="grid gap-3">
                                            <button
                                                v-for="recipient in recipientResults"
                                                :key="recipient.uuid"
                                                type="button"
                                                class="rounded-2xl border p-4 text-left transition"
                                                :class="
                                                    selectedRecipients.some((entry) => entry.uuid === recipient.uuid)
                                                        ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/20'
                                                        : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950'
                                                "
                                                @click="toggleRecipient(recipient)"
                                            >
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50">
                                                            {{ recipient.full_name || recipient.email }}
                                                        </p>
                                                        <p class="truncate text-sm text-slate-600 dark:text-slate-300">
                                                            {{ recipient.email }}
                                                        </p>
                                                    </div>
                                                    <UserPlus2 class="h-4 w-4 shrink-0 text-slate-400" />
                                                </div>
                                            </button>
                                        </div>

                                        <div
                                            v-if="!isLoadingRecipients && recipientResults.length === 0"
                                            class="rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/70 p-5 text-sm leading-6 text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300"
                                        >
                                            {{ t('admin.communicationComposer.empty.recipients') }}
                                        </div>

                                        <InputError :message="previewErrors.recipient_uuids" />
                                    </CardContent>
                                </Card>

                                <Card class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800">
                                    <CardHeader class="space-y-1">
                                        <CardTitle>
                                            {{ t('admin.communicationComposer.sections.content') }}
                                        </CardTitle>
                                        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {{ t('admin.communicationComposer.sectionDescriptions.content') }}
                                        </p>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <button
                                                v-for="mode in props.content_modes"
                                                :key="mode.value"
                                                type="button"
                                                class="rounded-2xl border px-4 py-3 text-left transition"
                                                :class="
                                                    selectedContentMode === mode.value
                                                        ? 'border-sky-400 bg-sky-50 text-sky-950 dark:border-sky-500/60 dark:bg-sky-500/10 dark:text-sky-50'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200'
                                                "
                                                @click="selectedContentMode = mode.value"
                                            >
                                                <p class="font-medium">{{ mode.label }}</p>
                                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                                    {{
                                                        t(
                                                            selectedContentMode === mode.value
                                                                ? 'admin.communicationComposer.help.modeSelected'
                                                                : `admin.communicationComposer.help.mode_${mode.value}`,
                                                        )
                                                    }}
                                                </p>
                                            </button>
                                        </div>

                                        <InputError :message="previewErrors.content_mode" />

                                        <div
                                            v-if="selectedContentMode === 'custom'"
                                            class="grid gap-4 lg:grid-cols-2"
                                        >
                                            <div class="space-y-2">
                                                <Label for="custom-subject">
                                                    {{ t('admin.communicationComposer.fields.subject') }}
                                                </Label>
                                                <Input
                                                    id="custom-subject"
                                                    v-model="customContent.subject"
                                                    class="rounded-2xl"
                                                    :placeholder="t('admin.communicationComposer.placeholders.subject')"
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="custom-title">
                                                    {{ t('admin.communicationComposer.fields.title') }}
                                                </Label>
                                                <Input
                                                    id="custom-title"
                                                    v-model="customContent.title"
                                                    class="rounded-2xl"
                                                    :placeholder="t('admin.communicationComposer.placeholders.title')"
                                                />
                                            </div>
                                            <div class="space-y-2 lg:col-span-2">
                                                <Label for="custom-body">
                                                    {{ t('admin.communicationComposer.fields.body') }}
                                                </Label>
                                                <textarea
                                                    id="custom-body"
                                                    v-model="customContent.body"
                                                    class="min-h-40 w-full rounded-[1.25rem] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm outline-none transition focus:border-sky-400 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                                    :placeholder="t('admin.communicationComposer.placeholders.body')"
                                                />
                                                <InputError :message="previewErrors['custom_content.body']" />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="custom-cta-label">
                                                    {{ t('admin.communicationComposer.fields.ctaLabel') }}
                                                </Label>
                                                <Input
                                                    id="custom-cta-label"
                                                    v-model="customContent.cta_label"
                                                    class="rounded-2xl"
                                                    :placeholder="t('admin.communicationComposer.placeholders.ctaLabel')"
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="custom-cta-url">
                                                    {{ t('admin.communicationComposer.fields.ctaUrl') }}
                                                </Label>
                                                <Input
                                                    id="custom-cta-url"
                                                    v-model="customContent.cta_url"
                                                    class="rounded-2xl"
                                                    :placeholder="t('admin.communicationComposer.placeholders.ctaUrl')"
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <div class="flex flex-col gap-3 rounded-[1.5rem] border border-slate-200/80 bg-white/90 p-5 dark:border-slate-800 dark:bg-slate-950/80">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                                {{ t('admin.communicationComposer.sections.send') }}
                                            </p>
                                            <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                                {{ t('admin.communicationComposer.sectionDescriptions.send') }}
                                            </p>
                                        </div>
                                        <Button class="h-11 rounded-2xl px-5" :disabled="!canSend" @click="submit">
                                            <SendHorizontal class="mr-2 h-4 w-4" />
                                            {{
                                                isSending
                                                    ? t('admin.communicationComposer.actions.sending')
                                                    : t('admin.communicationComposer.actions.send')
                                            }}
                                        </Button>
                                    </div>

                                    <div
                                        v-if="sendResult"
                                        class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                                    >
                                        <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                            {{
                                                t('admin.communicationComposer.result.summary', {
                                                    count: sendResult.outbound_count,
                                                    recipients: sendResult.recipient_count,
                                                    channels: sendResult.channel_count,
                                                })
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <Card class="rounded-[1.5rem] border-slate-200/80 dark:border-slate-800">
                                    <CardHeader class="space-y-1">
                                        <CardTitle class="flex items-center gap-2">
                                            <Sparkles class="h-4 w-4 text-sky-500" />
                                            {{ t('admin.communicationComposer.sections.preview') }}
                                        </CardTitle>
                                        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {{ t('admin.communicationComposer.sectionDescriptions.preview') }}
                                        </p>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div
                                            v-if="isLoadingPreview"
                                            class="space-y-3"
                                        >
                                            <div class="h-5 w-32 animate-pulse rounded-full bg-slate-200 dark:bg-slate-800" />
                                            <div class="h-24 animate-pulse rounded-[1.5rem] bg-slate-100 dark:bg-slate-900" />
                                            <div class="h-40 animate-pulse rounded-[1.5rem] bg-slate-100 dark:bg-slate-900" />
                                        </div>

                                        <div
                                            v-else-if="preview"
                                            class="space-y-4"
                                        >
                                            <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                                                <dl class="space-y-3 text-sm">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <dt class="text-slate-500 dark:text-slate-400">
                                                            {{ t('admin.communicationComposer.labels.locale') }}
                                                        </dt>
                                                        <dd class="text-right font-medium text-slate-950 dark:text-slate-50">
                                                            {{ preview.locale.label }}
                                                        </dd>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-4">
                                                        <dt class="text-slate-500 dark:text-slate-400">
                                                            {{ t('admin.communicationComposer.labels.sampleRecipient') }}
                                                        </dt>
                                                        <dd class="text-right font-medium text-slate-950 dark:text-slate-50">
                                                            {{ previewSampleLabel }}
                                                        </dd>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-4">
                                                        <dt class="text-slate-500 dark:text-slate-400">
                                                            {{ t('admin.communicationComposer.labels.recipientCount') }}
                                                        </dt>
                                                        <dd class="text-right font-medium text-slate-950 dark:text-slate-50">
                                                            {{ preview.recipient_count }}
                                                        </dd>
                                                    </div>
                                                </dl>
                                            </div>

                                            <div v-for="item in previewCards" :key="item.channel.value" class="space-y-4">
                                                <div class="flex flex-wrap gap-2">
                                                    <Badge class="rounded-full">
                                                        {{ item.channel.label }}
                                                    </Badge>
                                                    <Badge variant="secondary" class="rounded-full">
                                                        {{ item.template.name }}
                                                    </Badge>
                                                </div>

                                                <div
                                                    v-if="item.presentation.layout === 'mail'"
                                                    class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_20px_60px_-45px_rgba(15,23,42,0.5)] dark:border-slate-800 dark:bg-slate-950"
                                                >
                                                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900">
                                                        <p class="text-xs font-medium tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400">
                                                            {{ t('admin.communicationComposer.preview.emailSubject') }}
                                                        </p>
                                                        <p class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50">
                                                            {{ item.content.subject || t('admin.communicationComposer.empty.noValue') }}
                                                        </p>
                                                    </div>
                                                    <div class="space-y-4 px-5 py-5">
                                                        <p class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50">
                                                            {{ item.content.title || t('admin.communicationComposer.empty.noValue') }}
                                                        </p>
                                                        <p class="text-sm leading-7 text-slate-600 dark:text-slate-300">
                                                            {{ item.content.body || t('admin.communicationComposer.empty.noValue') }}
                                                        </p>
                                                            <Button
                                                                v-if="item.content.cta_label && item.content.cta_url"
                                                                class="rounded-2xl"
                                                                type="button"
                                                                disabled
                                                        >
                                                            {{ item.content.cta_label }}
                                                        </Button>
                                                    </div>
                                                </div>

                                                <div
                                                    v-else
                                                    class="overflow-hidden rounded-[1.75rem] border border-sky-200/80 bg-[linear-gradient(135deg,rgba(240,249,255,0.96),rgba(236,253,245,0.92))] shadow-[0_20px_60px_-45px_rgba(14,116,144,0.55)] dark:border-sky-900/50 dark:bg-[linear-gradient(135deg,rgba(12,74,110,0.34),rgba(2,6,23,0.92),rgba(6,95,70,0.25))]"
                                                >
                                                    <div class="px-5 py-5">
                                                        <div class="flex items-start gap-3">
                                                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/80 text-sky-700 shadow-sm dark:bg-white/10 dark:text-sky-200">
                                                                <Bell class="h-5 w-5" />
                                                            </div>
                                                            <div class="min-w-0 flex-1">
                                                                <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                                                    {{ item.content.title || t('admin.communicationComposer.empty.noValue') }}
                                                                </p>
                                                                <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-200">
                                                                    {{ item.content.body || t('admin.communicationComposer.empty.noValue') }}
                                                                </p>
                                                                <Button
                                                                    v-if="item.content.cta_label && item.content.cta_url"
                                                                    variant="secondary"
                                                                    class="mt-4 rounded-2xl bg-white/85 text-slate-950 hover:bg-white dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
                                                                    type="button"
                                                                    disabled
                                                                >
                                                                    {{ item.content.cta_label }}
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/70 p-5 text-sm leading-6 text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300"
                                        >
                                            {{ t('admin.communicationComposer.empty.preview') }}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
