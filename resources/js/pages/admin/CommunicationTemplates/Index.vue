<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CommunicationTemplateFilters from '@/components/admin/communication-templates/CommunicationTemplateFilters.vue';
import CommunicationTemplatesList from '@/components/admin/communication-templates/CommunicationTemplatesList.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import { index as communicationTemplatesIndex } from '@/routes/admin/communication-templates';
import { disable as disableGlobalOverride } from '@/routes/admin/communication-templates/global-override';
import type {
    AdminCommunicationTemplateItem,
    AdminCommunicationTemplatesIndexPageProps,
    AdminUserFilterValue,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminCommunicationTemplatesIndexPageProps>();
const { t } = useI18n();

const page = usePage();
const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);
const search = ref(props.filters.search);
const selectedChannel = ref(props.filters.channel ?? 'all');
const selectedTemplateMode = ref(props.filters.template_mode ?? 'all');
const selectedOverrideState = ref(props.filters.override_state ?? 'all');
const selectedLockState = ref(props.filters.lock_state ?? 'all');
const loading = ref(false);
let filterTimeout: ReturnType<typeof setTimeout> | null = null;

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    {
        title: t('admin.sections.communicationTemplates'),
        href: communicationTemplatesIndex(),
    },
];

const templateItems = computed<AdminCommunicationTemplateItem[]>(
    () => props.templates.data,
);

const listSummary = computed(() => {
    if (props.templates.meta.total === 0) {
        return t('admin.communicationTemplates.list.emptySummary');
    }

    return t('admin.communicationTemplates.list.summary', {
        from: props.templates.meta.from ?? 0,
        to: props.templates.meta.to ?? 0,
        total: props.templates.meta.total,
    });
});

const channelOptions = computed<AdminUserFilterValue[]>(() => [
    {
        value: 'all',
        label: t('admin.communicationTemplates.filters.channelPlaceholder'),
    },
    ...props.options.channels.map((channel) => ({
        value: channel,
        label: t(`admin.communicationTemplates.channels.${channel}`),
    })),
]);

const templateModeOptions = computed<AdminUserFilterValue[]>(() => [
    {
        value: 'all',
        label: t(
            'admin.communicationTemplates.filters.templateModePlaceholder',
        ),
    },
    ...props.options.template_modes.map((mode) => ({
        value: mode,
        label: t(`admin.communicationTemplates.modes.${mode}`),
    })),
]);

const overrideStateOptions = computed<AdminUserFilterValue[]>(() => [
    {
        value: 'all',
        label: t(
            'admin.communicationTemplates.filters.overrideStatePlaceholder',
        ),
    },
    {
        value: 'with_override',
        label: t(
            'admin.communicationTemplates.filters.overrideStates.withOverride',
        ),
    },
    {
        value: 'without_override',
        label: t(
            'admin.communicationTemplates.filters.overrideStates.withoutOverride',
        ),
    },
]);

const lockStateOptions = computed<AdminUserFilterValue[]>(() => [
    {
        value: 'all',
        label: t('admin.communicationTemplates.filters.lockStatePlaceholder'),
    },
    {
        value: 'locked',
        label: t('admin.communicationTemplates.filters.lockStates.locked'),
    },
    {
        value: 'editable',
        label: t('admin.communicationTemplates.filters.lockStates.editable'),
    },
]);

const feedback = computed(() => {
    if (flash.value.error) {
        return {
            variant: 'destructive' as const,
            title: t('admin.communicationTemplates.feedback.errorTitle'),
            message: flash.value.error,
        };
    }

    const firstError = Object.values(pageErrors.value)[0];

    if (firstError) {
        return {
            variant: 'destructive' as const,
            title: t('admin.communicationTemplates.feedback.errorTitle'),
            message: firstError,
        };
    }

    if (flash.value.success) {
        return {
            variant: 'default' as const,
            title: t('admin.communicationTemplates.feedback.successTitle'),
            message: flash.value.success,
        };
    }

    return null;
});

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        selectedChannel.value = filters.channel ?? 'all';
        selectedTemplateMode.value = filters.template_mode ?? 'all';
        selectedOverrideState.value = filters.override_state ?? 'all';
        selectedLockState.value = filters.lock_state ?? 'all';
    },
    { deep: true },
);

watch(
    [
        search,
        selectedChannel,
        selectedTemplateMode,
        selectedOverrideState,
        selectedLockState,
    ],
    () => {
        if (filterTimeout) {
            clearTimeout(filterTimeout);
        }

        loading.value = true;

        filterTimeout = setTimeout(() => {
            router.get(
                communicationTemplatesIndex.url({
                    query: {
                        search:
                            search.value.trim() === ''
                                ? null
                                : search.value.trim(),
                        channel:
                            selectedChannel.value === 'all'
                                ? null
                                : selectedChannel.value,
                        template_mode:
                            selectedTemplateMode.value === 'all'
                                ? null
                                : selectedTemplateMode.value,
                        override_state:
                            selectedOverrideState.value === 'all'
                                ? null
                                : selectedOverrideState.value,
                        lock_state:
                            selectedLockState.value === 'all'
                                ? null
                                : selectedLockState.value,
                    },
                }),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    onFinish: () => {
                        loading.value = false;
                    },
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
    selectedChannel.value = 'all';
    selectedTemplateMode.value = 'all';
    selectedOverrideState.value = 'all';
    selectedLockState.value = 'all';
}

function disableFromList(template: AdminCommunicationTemplateItem): void {
    if (
        !confirm(
            t('admin.communicationTemplates.dialogs.disableDescription', {
                template: template.name,
            }),
        )
    ) {
        return;
    }

    router.post(
        disableGlobalOverride({ communicationTemplate: template.uuid }).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['templates'] });
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.communicationTemplates.title')" />

        <AdminLayout>
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
                            <Heading
                                variant="small"
                                :title="t('admin.communicationTemplates.title')"
                                :description="
                                    t(
                                        'admin.communicationTemplates.description',
                                    )
                                "
                            />
                            <Badge
                                class="w-fit rounded-full border px-3 py-1 text-[11px] tracking-[0.18em] uppercase"
                            >
                                {{
                                    t(
                                        'admin.communicationTemplates.index.summary',
                                        { count: props.templates.meta.total },
                                    )
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <Alert v-if="feedback" :variant="feedback.variant">
                            <AlertTitle>{{ feedback.title }}</AlertTitle>
                            <AlertDescription>{{
                                feedback.message
                            }}</AlertDescription>
                        </Alert>

                        <CommunicationTemplateFilters
                            v-model:search="search"
                            v-model:channel="selectedChannel"
                            v-model:template-mode="selectedTemplateMode"
                            v-model:override-state="selectedOverrideState"
                            v-model:lock-state="selectedLockState"
                            :channel-options="channelOptions"
                            :template-mode-options="templateModeOptions"
                            :override-state-options="overrideStateOptions"
                            :lock-state-options="lockStateOptions"
                            @reset="resetFilters"
                        />

                        <div
                            v-if="templateItems.length === 0"
                            class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                        >
                            <h2
                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t(
                                        'admin.communicationTemplates.empty.title',
                                    )
                                }}
                            </h2>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'admin.communicationTemplates.empty.description',
                                    )
                                }}
                            </p>
                        </div>

                        <CommunicationTemplatesList
                            v-else
                            :templates="templateItems"
                            :links="props.templates.meta.links"
                            :summary="listSummary"
                            :current-page="props.templates.meta.current_page"
                            :last-page="props.templates.meta.last_page"
                            :loading="loading"
                            @disable="disableFromList"
                        />
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
