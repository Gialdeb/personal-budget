<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Bot, PlayCircle } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AutomationFilters from '@/components/admin/automation/AutomationFilters.vue';
import AutomationPipelineOverview from '@/components/admin/automation/AutomationPipelineOverview.vue';
import AutomationRunsTable from '@/components/admin/automation/AutomationRunsTable.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as automationIndex,
    retry as retryAutomationRun,
    run as runAutomationPipeline,
} from '@/routes/admin/automation';
import type {
    AdminAutomationIndexPageProps,
    AutomationPipelineOption,
    AutomationRunItem,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminAutomationIndexPageProps>();
const { t, te } = useI18n();

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

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('admin.sections.overview'),
        href: adminIndex(),
    },
    {
        title: t('admin.sections.automation'),
        href: automationIndex(),
    },
];

const ALL_OPTION = 'all';

const selectedPipeline = ref(props.filters.pipeline ?? ALL_OPTION);
const selectedStatus = ref(props.filters.status ?? ALL_OPTION);
const selectedTriggerType = ref(props.filters.trigger_type ?? ALL_OPTION);
const busyPipelineKey = ref<string | null>(null);
const retryDialogOpen = ref(false);
const selectedRetryRun = ref<AutomationRunItem | null>(null);
let filterTimeout: ReturnType<typeof setTimeout> | null = null;
let refreshPollingInterval: ReturnType<typeof setInterval> | null = null;
let refreshPollingTimeout: ReturnType<typeof setTimeout> | null = null;

const refreshOnly = ['runs', 'statuses'];

function pipelineLabel(key: string): string {
    const translationKey = `admin.automation.pipelines.${key}`;

    return te(translationKey)
        ? t(translationKey)
        : key
              .replaceAll('_', ' ')
              .replace(/\b\w/g, (character) => character.toUpperCase());
}

function statusLabel(status: string): string {
    const translationKey = `admin.automation.statuses.${status}`;

    return te(translationKey) ? t(translationKey) : status.replaceAll('_', ' ');
}

function triggerLabel(triggerType: string): string {
    const translationKey = `admin.automation.triggers.${triggerType}`;

    return te(translationKey) ? t(translationKey) : triggerType;
}

const feedback = computed(() => {
    if (flash.value.error) {
        return {
            variant: 'destructive' as const,
            title: t('admin.automation.flash.errorTitle'),
            message: flash.value.error,
        };
    }

    const errorMessage =
        pageErrors.value.pipeline ??
        pageErrors.value.run ??
        pageErrors.value.automation;

    if (errorMessage) {
        return {
            variant: 'destructive' as const,
            title: t('admin.automation.flash.errorTitle'),
            message: errorMessage,
        };
    }

    if (flash.value.success) {
        return {
            variant: 'default' as const,
            title: t('admin.automation.flash.successTitle'),
            message: flash.value.success,
        };
    }

    return null;
});

const pipelineOptions = computed<AutomationPipelineOption[]>(() => [
    {
        value: ALL_OPTION,
        label: t('admin.automation.filters.pipelinePlaceholder'),
    },
    ...props.options.pipelines.map((pipeline) => ({
        value: pipeline,
        label: pipelineLabel(pipeline),
    })),
]);

const statusOptions = computed<AutomationPipelineOption[]>(() => [
    {
        value: ALL_OPTION,
        label: t('admin.automation.filters.statusPlaceholder'),
    },
    ...props.options.statuses.map((status) => ({
        value: status,
        label: statusLabel(status),
    })),
]);

const triggerOptions = computed<AutomationPipelineOption[]>(() => [
    {
        value: ALL_OPTION,
        label: t('admin.automation.filters.triggerPlaceholder'),
    },
    ...props.options.trigger_types.map((triggerType) => ({
        value: triggerType,
        label: triggerLabel(triggerType),
    })),
]);

const listSummary = computed(() => {
    if (props.runs.meta.total === 0) {
        return t('admin.automation.list.emptySummary');
    }

    return t('admin.automation.list.summary', {
        from: props.runs.meta.from ?? 0,
        to: props.runs.meta.to ?? 0,
        total: props.runs.meta.total,
    });
});

const retryDialogDescription = computed(() => {
    if (!selectedRetryRun.value) {
        return '';
    }

    return t('admin.automation.dialogs.retryDescription', {
        run: selectedRetryRun.value.uuid,
        pipeline: pipelineLabel(selectedRetryRun.value.automation_key),
    });
});

watch(
    () => props.filters,
    (filters) => {
        selectedPipeline.value = filters.pipeline ?? ALL_OPTION;
        selectedStatus.value = filters.status ?? ALL_OPTION;
        selectedTriggerType.value = filters.trigger_type ?? ALL_OPTION;
    },
    { deep: true },
);

watch([selectedPipeline, selectedStatus, selectedTriggerType], () => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }

    filterTimeout = setTimeout(() => {
        router.get(
            automationIndex.url({
                query: {
                    pipeline:
                        selectedPipeline.value === ALL_OPTION
                            ? null
                            : selectedPipeline.value,
                    status:
                        selectedStatus.value === ALL_OPTION
                            ? null
                            : selectedStatus.value,
                    trigger_type:
                        selectedTriggerType.value === ALL_OPTION
                            ? null
                            : selectedTriggerType.value,
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
});

onUnmounted(() => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }

    stopRefreshPolling();
});

function resetFilters(): void {
    selectedPipeline.value = ALL_OPTION;
    selectedStatus.value = ALL_OPTION;
    selectedTriggerType.value = ALL_OPTION;
}

function reloadAutomationData(): void {
    router.reload({
        only: refreshOnly,
    });
}

function stopRefreshPolling(): void {
    if (refreshPollingInterval) {
        clearInterval(refreshPollingInterval);
        refreshPollingInterval = null;
    }

    if (refreshPollingTimeout) {
        clearTimeout(refreshPollingTimeout);
        refreshPollingTimeout = null;
    }
}

function startRefreshPolling(): void {
    stopRefreshPolling();

    refreshPollingInterval = setInterval(() => {
        reloadAutomationData();
    }, 2500);

    refreshPollingTimeout = setTimeout(() => {
        stopRefreshPolling();
    }, 15000);
}

function dispatchPipeline(pipelineKey: string): void {
    busyPipelineKey.value = pipelineKey;

    router.post(
        runAutomationPipeline.url({ pipeline: pipelineKey }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                reloadAutomationData();
                startRefreshPolling();
            },
            onFinish: () => {
                busyPipelineKey.value = null;
            },
        },
    );
}

function openRetryDialog(run: AutomationRunItem): void {
    selectedRetryRun.value = run;
    retryDialogOpen.value = true;
}

function submitRetry(): void {
    if (!selectedRetryRun.value) {
        return;
    }

    router.post(
        retryAutomationRun.url({ automationRun: selectedRetryRun.value.uuid }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                reloadAutomationData();
                startRefreshPolling();
            },
            onFinish: () => {
                retryDialogOpen.value = false;
                selectedRetryRun.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.automation.title')" />

        <AdminLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-sky-500/10 via-emerald-500/10 to-amber-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] tracking-[0.2em] text-sky-900 uppercase dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100"
                            >
                                <Bot class="mr-1.5 h-3.5 w-3.5" />
                                {{ t('admin.sections.automation') }}
                            </Badge>
                            <Heading
                                variant="small"
                                :title="t('admin.automation.title')"
                                :description="t('admin.automation.description')"
                            />
                        </div>
                        <div
                            class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300"
                        >
                            <PlayCircle class="h-4 w-4 text-emerald-500" />
                            <span>{{ props.runs.meta.total }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6 md:px-8 md:py-8">
                    <Alert
                        v-if="feedback"
                        :variant="feedback.variant"
                        class="rounded-[1.5rem]"
                    >
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>{{ feedback.title }}</AlertTitle>
                        <AlertDescription>{{
                            feedback.message
                        }}</AlertDescription>
                    </Alert>

                    <AutomationPipelineOverview
                        :statuses="props.statuses"
                        :busy-pipeline-key="busyPipelineKey"
                        @run="dispatchPipeline"
                    />

                    <AutomationFilters
                        :pipeline="selectedPipeline"
                        :status="selectedStatus"
                        :trigger-type="selectedTriggerType"
                        :pipeline-options="pipelineOptions"
                        :status-options="statusOptions"
                        :trigger-options="triggerOptions"
                        @update:pipeline="selectedPipeline = $event"
                        @update:status="selectedStatus = $event"
                        @update:trigger-type="selectedTriggerType = $event"
                        @reset="resetFilters"
                    />

                    <AutomationRunsTable
                        :runs="props.runs.data"
                        :links="props.runs.meta.links"
                        :summary="listSummary"
                        :current-page="props.runs.meta.current_page"
                        :last-page="props.runs.meta.last_page"
                        @retry="openRetryDialog"
                    />
                </div>
            </section>

            <Dialog v-model:open="retryDialogOpen">
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{
                            t('admin.automation.dialogs.retryTitle')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{ retryDialogDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            class="rounded-xl"
                            @click="retryDialogOpen = false"
                        >
                            {{ t('admin.automation.actions.close') }}
                        </Button>
                        <Button class="rounded-xl" @click="submitRetry">
                            {{ t('admin.automation.actions.confirmRetry') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    </AppLayout>
</template>
