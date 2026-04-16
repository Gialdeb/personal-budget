<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ChevronDown,
    CircleCheckBig,
    Filter,
    FileWarning,
    Files,
    Flame,
    Trash2,
    Pencil,
    RotateCcw,
    ShieldAlert,
    SkipForward,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ImportPayloadList from '@/components/imports/ImportPayloadList.vue';
import ImportRowReviewDialog from '@/components/imports/ImportRowReviewDialog.vue';
import ImportStatusBadge from '@/components/imports/ImportStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import {
    importReady as importReadyRoute,
    index as importsRoute,
    rollback as rollbackRoute,
} from '@/routes/imports';
import type {
    BreadcrumbItem,
    ImportDetail,
    ImportRowItem,
    ImportsShowPageProps,
} from '@/types';

const props = defineProps<ImportsShowPageProps>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('imports.title'),
        href: importsRoute(),
    },
    {
        title: props.importDetail.original_filename,
        href: props.importDetail.show_url,
    },
];

const parseToneClasses: Record<string, string> = {
    parsed: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-300',
    failed: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300',
    pending:
        'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
    skipped:
        'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
};

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const errors = computed(
    () => (page.props.errors ?? {}) as { import?: string | null },
);
const activeAction = ref<'import' | 'rollback' | 'delete' | null>(null);
const activeRowActionUuid = ref<string | null>(null);
const reviewDialogOpen = ref(false);
const reviewRow = ref<ImportRowItem | null>(null);
const rollbackDialogOpen = ref(false);
const skipDialogOpen = ref(false);
const rowPendingSkip = ref<ImportRowItem | null>(null);
const approveDuplicateDialogOpen = ref(false);
const rowPendingDuplicateApproval = ref<ImportRowItem | null>(null);
const deleteImportDialogOpen = ref(false);

type ImportRowFilter =
    | 'all'
    | 'review'
    | 'invalid'
    | 'duplicate'
    | 'ready'
    | 'imported'
    | 'skipped';

const rowFilter = ref<ImportRowFilter>('all');
const rowFilterOptions = computed(() => [
    {
        value: 'all' as ImportRowFilter,
        label: t('imports.list.filters.all'),
        count: props.rows.length,
    },
    {
        value: 'review' as ImportRowFilter,
        label: t('imports.list.filters.review'),
        count: props.rows.filter((row) => row.status === 'needs_review').length,
    },
    {
        value: 'invalid' as ImportRowFilter,
        label: t('imports.list.filters.invalid'),
        count: props.rows.filter((row) =>
            ['invalid', 'blocked_year'].includes(row.status),
        ).length,
    },
    {
        value: 'duplicate' as ImportRowFilter,
        label: t('imports.list.filters.duplicate'),
        count: props.rows.filter((row) =>
            ['duplicate_candidate', 'already_imported'].includes(row.status),
        ).length,
    },
    {
        value: 'ready' as ImportRowFilter,
        label: t('imports.list.filters.ready'),
        count: props.rows.filter((row) => row.status === 'ready').length,
    },
    {
        value: 'imported' as ImportRowFilter,
        label: t('imports.list.filters.imported'),
        count: props.rows.filter((row) => row.status === 'imported').length,
    },
    {
        value: 'skipped' as ImportRowFilter,
        label: t('imports.list.filters.skipped'),
        count: props.rows.filter((row) => row.status === 'skipped').length,
    },
]);
const filteredRows = computed(() => {
    return props.rows.filter((row) => {
        switch (rowFilter.value) {
            case 'review':
                return row.status === 'needs_review';
            case 'invalid':
                return ['invalid', 'blocked_year'].includes(row.status);
            case 'duplicate':
                return ['duplicate_candidate', 'already_imported'].includes(
                    row.status,
                );
            case 'ready':
                return row.status === 'ready';
            case 'imported':
                return row.status === 'imported';
            case 'skipped':
                return row.status === 'skipped';
            default:
                return true;
        }
    });
});

function importDetailMetaParts(importDetail: ImportDetail): string[] {
    return [
        importDetail.management_year_label,
        importDetail.account_name ?? t('imports.show.accountUnavailable'),
        importDetail.bank_name,
        importDetail.imported_at_label
            ? t('imports.show.uploadedOn', {
                  date: importDetail.imported_at_label,
              })
            : null,
    ].filter((value): value is string => Boolean(value && value !== ''));
}

function formatImportAmount(
    valueRaw: string | null | undefined,
    fallback: string | null | undefined,
): string {
    if (valueRaw !== null && valueRaw !== undefined && valueRaw !== '') {
        const numericValue = Number(valueRaw);

        if (Number.isFinite(numericValue)) {
            return formatCurrency(numericValue);
        }
    }

    return fallback ?? t('imports.show.rowsSection.unavailable');
}

function localizeImportFeedbackMessage(message: string): string {
    const localizedFeedbackMessages = [
        {
            messages: [
                'La categoria non è valorizzata e richiede revisione.',
                'The category is missing and requires review.',
            ],
            key: 'imports.show.feedbackMessages.categoryMissingReview',
        },
        {
            messages: [
                'La categoria indicata non esiste nel gestionale e la riga richiede revisione.',
                'The specified category does not exist in the app and the row requires review.',
            ],
            key: 'imports.show.feedbackMessages.categoryUnknownReview',
        },
        {
            messages: [
                'Questa riga risulta già importata in precedenza.',
                'This row appears to have already been imported.',
            ],
            key: 'imports.show.feedbackMessages.alreadyImported',
        },
        {
            messages: [
                'Questa riga sembra duplicata nello stesso import.',
                'This row appears duplicated within the same import.',
            ],
            key: 'imports.show.feedbackMessages.duplicateCurrentImport',
        },
        {
            messages: [
                'Riga saltata manualmente dall’utente.',
                'Row skipped manually by the user.',
            ],
            key: 'imports.show.feedbackMessages.skippedManually',
        },
    ] as const;

    const translationKey = localizedFeedbackMessages.find((entry) =>
        (entry.messages as readonly string[]).includes(message),
    )?.key;

    return translationKey ? t(translationKey) : message;
}

function rowStatusFallbackMessages(row: ImportRowItem): string[] {
    if (row.errors.length > 0 || row.warnings.length > 0) {
        return [];
    }

    const statusMessageKeys: Partial<Record<ImportRowItem['status'], string>> =
        {
            ready: 'imports.show.statusMessages.ready',
            imported: 'imports.show.statusMessages.imported',
            needs_review: 'imports.show.statusMessages.needsReview',
            invalid: 'imports.show.statusMessages.invalid',
            blocked_year: 'imports.show.statusMessages.blockedYear',
            duplicate_candidate:
                'imports.show.statusMessages.duplicateCandidate',
            already_imported: 'imports.show.statusMessages.alreadyImported',
            skipped: 'imports.show.statusMessages.skipped',
            rolled_back: 'imports.show.statusMessages.rolledBack',
        };

    const translationKey = statusMessageKeys[row.status];

    return translationKey ? [t(translationKey)] : [];
}

function submitImportReady(): void {
    activeAction.value = 'import';

    router.post(
        importReadyRoute({ import: props.importDetail.uuid }).url,
        {},
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
            onFinish: () => {
                activeAction.value = null;
            },
        },
    );
}

function submitRollback(): void {
    activeAction.value = 'rollback';

    router.post(
        rollbackRoute({ import: props.importDetail.uuid }).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                activeAction.value = null;
                rollbackDialogOpen.value = false;
            },
        },
    );
}

function openReviewDialog(row: ImportRowItem): void {
    reviewRow.value = row;
    reviewDialogOpen.value = true;
}

function handleReviewSaved(): void {
    reviewRow.value = null;
}

function openSkipDialog(row: ImportRowItem): void {
    rowPendingSkip.value = row;
    skipDialogOpen.value = true;
}

function handleSkipDialogOpenChange(open: boolean): void {
    skipDialogOpen.value = open;

    if (!open) {
        rowPendingSkip.value = null;
    }
}

function submitSkip(): void {
    if (!rowPendingSkip.value) {
        return;
    }

    activeRowActionUuid.value = rowPendingSkip.value.uuid;

    router.post(
        rowPendingSkip.value.skip_url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                skipDialogOpen.value = false;
                rowPendingSkip.value = null;
            },
            onFinish: () => {
                activeRowActionUuid.value = null;
            },
        },
    );
}

function openApproveDuplicateDialog(row: ImportRowItem): void {
    rowPendingDuplicateApproval.value = row;
    approveDuplicateDialogOpen.value = true;
}

function handleApproveDuplicateDialogOpenChange(open: boolean): void {
    approveDuplicateDialogOpen.value = open;

    if (!open) {
        rowPendingDuplicateApproval.value = null;
    }
}

function submitApproveDuplicate(): void {
    if (!rowPendingDuplicateApproval.value?.approve_duplicate_url) {
        return;
    }

    activeRowActionUuid.value = rowPendingDuplicateApproval.value.uuid;

    router.post(
        rowPendingDuplicateApproval.value.approve_duplicate_url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                approveDuplicateDialogOpen.value = false;
                rowPendingDuplicateApproval.value = null;
            },
            onFinish: () => {
                activeRowActionUuid.value = null;
            },
        },
    );
}

function submitDeleteImport(): void {
    if (!props.importDetail.delete_url) {
        return;
    }

    activeAction.value = 'delete';

    router.delete(props.importDetail.delete_url, {
        preserveScroll: true,
        onFinish: () => {
            activeAction.value = null;
            deleteImportDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head
        :title="
            t('imports.show.metaTitle', {
                filename: props.importDetail.original_filename,
            })
        "
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="space-y-6">
                <section
                    class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_22rem]"
                >
                    <Card
                        class="border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader class="gap-4">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="space-y-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <ImportStatusBadge
                                            :label="
                                                props.importDetail.status_label
                                            "
                                            :tone="
                                                props.importDetail.status_tone
                                            "
                                        />
                                        <span
                                            class="text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                props.importDetail.parser_label
                                            }}
                                        </span>
                                    </div>
                                    <CardTitle
                                        class="text-2xl text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            props.importDetail.original_filename
                                        }}
                                    </CardTitle>
                                    <CardDescription
                                        class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm leading-6 break-words"
                                    >
                                        <template
                                            v-for="(
                                                part, index
                                            ) in importDetailMetaParts(
                                                props.importDetail,
                                            )"
                                            :key="`detail-meta-${index}`"
                                        >
                                            <span
                                                v-if="index > 0"
                                                aria-hidden="true"
                                            >
                                                ·
                                            </span>
                                            <span class="break-words">
                                                {{ part }}
                                            </span>
                                        </template>
                                    </CardDescription>
                                </div>

                                <Button
                                    as-child
                                    variant="outline"
                                    class="rounded-full"
                                >
                                    <Link :href="importsRoute()">
                                        <ArrowLeft class="mr-2 size-4" />
                                        {{ t('imports.show.backToList') }}
                                    </Link>
                                </Button>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-if="props.importDetail.can_rollback"
                                    variant="outline"
                                    class="rounded-full border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                    @click="rollbackDialogOpen = true"
                                >
                                    <RotateCcw class="mr-2 size-4" />
                                    {{
                                        t('imports.show.actions.rollbackImport')
                                    }}
                                </Button>
                                <Button
                                    v-if="props.importDetail.can_delete"
                                    variant="outline"
                                    class="rounded-full border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
                                    @click="deleteImportDialogOpen = true"
                                >
                                    <Trash2 class="mr-2 size-4" />
                                    {{ t('imports.show.actions.deleteImport') }}
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent
                            class="grid gap-3 md:grid-cols-2 xl:grid-cols-6"
                        >
                            <div
                                class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <div
                                    class="text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ t('imports.show.metrics.totalRows') }}
                                </div>
                                <div
                                    class="mt-2 text-3xl font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ props.importDetail.rows_count }}
                                </div>
                            </div>
                            <div
                                class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/30"
                            >
                                <div
                                    class="text-xs tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300"
                                >
                                    {{ t('imports.show.metrics.ready') }}
                                </div>
                                <div
                                    class="mt-2 text-3xl font-semibold text-emerald-800 dark:text-emerald-200"
                                >
                                    {{ props.importDetail.ready_rows_count }}
                                </div>
                            </div>
                            <div
                                class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 dark:border-amber-900/50 dark:bg-amber-950/30"
                            >
                                <div
                                    class="text-xs tracking-[0.18em] text-amber-700 uppercase dark:text-amber-300"
                                >
                                    {{ t('imports.show.metrics.review') }}
                                </div>
                                <div
                                    class="mt-2 text-3xl font-semibold text-amber-800 dark:text-amber-200"
                                >
                                    {{ props.importDetail.review_rows_count }}
                                </div>
                            </div>
                            <div
                                class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 dark:border-rose-900/50 dark:bg-rose-950/30"
                            >
                                <div
                                    class="text-xs tracking-[0.18em] text-rose-700 uppercase dark:text-rose-300"
                                >
                                    {{ t('imports.show.metrics.invalid') }}
                                </div>
                                <div
                                    class="mt-2 text-3xl font-semibold text-rose-800 dark:text-rose-200"
                                >
                                    {{ props.importDetail.invalid_rows_count }}
                                </div>
                            </div>
                            <div
                                class="rounded-2xl border border-slate-200 bg-slate-100/90 p-4 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div
                                    class="text-xs tracking-[0.18em] text-slate-600 uppercase dark:text-slate-300"
                                >
                                    {{ t('imports.show.metrics.duplicate') }}
                                </div>
                                <div
                                    class="mt-2 text-3xl font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        props.importDetail.duplicate_rows_count
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-2xl border border-sky-200 bg-sky-50/80 p-4 dark:border-sky-900/50 dark:bg-sky-950/30"
                            >
                                <div
                                    class="text-xs tracking-[0.18em] text-sky-700 uppercase dark:text-sky-300"
                                >
                                    {{ t('imports.show.metrics.imported') }}
                                </div>
                                <div
                                    class="mt-2 text-3xl font-semibold text-sky-800 dark:text-sky-200"
                                >
                                    {{ props.importDetail.imported_rows_count }}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader>
                            <CardTitle class="text-lg">{{
                                t('imports.show.infoCard.title')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                    >{{
                                        t('imports.show.infoCard.format')
                                    }}</span
                                >
                                <span
                                    class="text-right font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{ props.importDetail.parser_label }}
                                </span>
                            </div>
                            <div
                                v-if="props.importDetail.completed_at_label"
                                class="flex items-start justify-between gap-3"
                            >
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                    >{{
                                        t('imports.show.infoCard.completedAt')
                                    }}</span
                                >
                                <span
                                    class="text-right font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{ props.importDetail.completed_at_label }}
                                </span>
                            </div>
                            <div
                                v-if="props.importDetail.failed_at_label"
                                class="flex items-start justify-between gap-3"
                            >
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                    >{{
                                        t('imports.show.infoCard.failedAt')
                                    }}</span
                                >
                                <span
                                    class="text-right font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{ props.importDetail.failed_at_label }}
                                </span>
                            </div>
                            <div
                                v-if="props.importDetail.rolled_back_at_label"
                                class="flex items-start justify-between gap-3"
                            >
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                    >{{
                                        t('imports.show.infoCard.rolledBackAt')
                                    }}</span
                                >
                                <span
                                    class="text-right font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        props.importDetail.rolled_back_at_label
                                    }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <Alert
                    class="border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-200"
                >
                    <Files class="size-4" />
                    <AlertTitle>{{
                        props.importDetail.management_year_label
                    }}</AlertTitle>
                    <AlertDescription>
                        {{
                            t('imports.show.alerts.yearValidated', {
                                year: props.importDetail.management_year,
                            })
                        }}
                    </AlertDescription>
                </Alert>

                <Alert
                    v-if="flash.success"
                    class="border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200"
                >
                    <CircleCheckBig class="size-4" />
                    <AlertTitle>{{
                        t('imports.show.alerts.actionCompleted')
                    }}</AlertTitle>
                    <AlertDescription>{{ flash.success }}</AlertDescription>
                </Alert>

                <Alert
                    v-if="errors.import"
                    class="border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
                >
                    <ShieldAlert class="size-4" />
                    <AlertTitle>{{
                        t('imports.show.alerts.notCompleted')
                    }}</AlertTitle>
                    <AlertDescription>{{ errors.import }}</AlertDescription>
                </Alert>

                <Alert
                    v-if="props.importDetail.error_message"
                    class="border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
                >
                    <FileWarning class="size-4" />
                    <AlertTitle>{{
                        t('imports.show.alerts.importError')
                    }}</AlertTitle>
                    <AlertDescription>{{
                        props.importDetail.error_message
                    }}</AlertDescription>
                </Alert>

                <Alert
                    v-if="props.importDetail.blocked_year_rows_count > 0"
                    class="border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    <AlertTriangle class="size-4" />
                    <AlertTitle>{{
                        t('imports.show.alerts.rowsOutsideYear')
                    }}</AlertTitle>
                    <AlertDescription>
                        {{
                            t(
                                'imports.show.alerts.rowsOutsideYearDescription',
                                {
                                    count: props.importDetail
                                        .blocked_year_rows_count,
                                    rowLabel:
                                        props.importDetail
                                            .blocked_year_rows_count === 1
                                            ? t(
                                                  'imports.show.singularRowOutsideYear',
                                              )
                                            : t(
                                                  'imports.show.pluralRowsOutsideYear',
                                              ),
                                    year: props.importDetail.management_year,
                                },
                            )
                        }}
                    </AlertDescription>
                </Alert>

                <Alert
                    v-if="props.importDetail.status === 'rolled_back'"
                    class="border-slate-300 bg-slate-100 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <RotateCcw class="size-4" />
                    <AlertTitle>{{
                        t('imports.show.alerts.importRolledBack')
                    }}</AlertTitle>
                    <AlertDescription>
                        {{
                            t('imports.show.alerts.importRolledBackDescription')
                        }}
                    </AlertDescription>
                </Alert>

                <section class="space-y-4">
                    <div
                        class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm lg:flex-row lg:items-start lg:justify-between dark:border-slate-800 dark:bg-slate-950/80"
                    >
                        <div>
                            <h2
                                class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('imports.show.rowsSection.title') }}
                            </h2>
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('imports.show.rowsSection.description') }}
                            </p>
                        </div>

                        <div
                            class="flex flex-col items-start gap-2 lg:items-end"
                        >
                            <Button
                                v-if="props.importDetail.can_import_ready"
                                class="rounded-full"
                                :disabled="activeAction === 'import'"
                                @click="submitImportReady"
                            >
                                <Upload class="mr-2 size-4" />
                                {{
                                    activeAction === 'import'
                                        ? t(
                                              'imports.show.actions.importingReady',
                                          )
                                        : t('imports.show.actions.importReady')
                                }}
                            </Button>
                            <p
                                v-if="props.importDetail.can_import_ready"
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    props.importDetail.ready_rows_count === 1
                                        ? t(
                                              'imports.show.rowsSection.readyToPromoteOne',
                                          )
                                        : t(
                                              'imports.show.rowsSection.readyToPromoteMany',
                                              {
                                                  count: props.importDetail
                                                      .ready_rows_count,
                                              },
                                          )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <Filter class="size-3.5" />
                            {{ t('imports.show.rowsSection.filterRows') }}
                        </div>
                        <Button
                            v-for="filterOption in rowFilterOptions"
                            :key="filterOption.value"
                            :variant="
                                rowFilter === filterOption.value
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            class="rounded-full"
                            @click="rowFilter = filterOption.value"
                        >
                            {{ filterOption.label }}
                            <span
                                class="ml-2 rounded-full bg-black/10 px-2 py-0.5 text-[11px] dark:bg-white/10"
                            >
                                {{ filterOption.count }}
                            </span>
                        </Button>
                    </div>

                    <div
                        v-if="props.rows.length === 0"
                        class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                    >
                        {{ t('imports.show.rowsSection.empty') }}
                    </div>

                    <div
                        v-else-if="filteredRows.length === 0"
                        class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                    >
                        {{ t('imports.show.rowsSection.emptyFiltered') }}
                    </div>

                    <div v-else class="space-y-3">
                        <Collapsible
                            v-for="row in filteredRows"
                            :key="row.uuid"
                            class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div class="p-4 sm:p-5">
                                <div
                                    class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
                                >
                                    <div
                                        class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[6rem_8rem_8rem_10rem_minmax(0,1.4fr)_10rem]"
                                    >
                                        <div>
                                            <div
                                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'imports.show.rowsSection.columns.row',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ row.row_index }}
                                            </div>
                                        </div>
                                        <div>
                                            <div
                                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'imports.show.rowsSection.columns.date',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    row.date ??
                                                    t(
                                                        'imports.show.rowsSection.unavailable',
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div>
                                            <div
                                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'imports.show.rowsSection.columns.type',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    row.type_label ??
                                                    t(
                                                        'imports.show.rowsSection.unavailable',
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div>
                                            <div
                                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'imports.show.rowsSection.columns.amount',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    formatImportAmount(
                                                        row.amount_value_raw,
                                                        row.amount,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div>
                                            <div
                                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'imports.show.rowsSection.columns.detail',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    row.description ??
                                                    t(
                                                        'imports.show.rowsSection.detailUnavailable',
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <div>
                                            <div
                                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'imports.show.rowsSection.columns.category',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    row.category_label ??
                                                    t(
                                                        'imports.show.rowsSection.categoryToReview',
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-wrap items-center gap-2 xl:justify-end"
                                    >
                                        <Badge
                                            v-if="row.is_ready"
                                            variant="outline"
                                            class="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300"
                                        >
                                            {{
                                                t(
                                                    'imports.show.rowsSection.readyBadge',
                                                )
                                            }}
                                        </Badge>
                                        <Badge
                                            v-if="row.is_imported"
                                            variant="outline"
                                            class="border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-300"
                                        >
                                            {{
                                                t(
                                                    'imports.show.rowsSection.importedBadge',
                                                )
                                            }}
                                        </Badge>
                                        <Badge
                                            v-if="row.is_blocked"
                                            variant="outline"
                                            class="border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
                                        >
                                            {{
                                                t(
                                                    'imports.show.rowsSection.blockedBadge',
                                                )
                                            }}
                                        </Badge>
                                        <ImportStatusBadge
                                            :label="row.status_label"
                                            :tone="row.status_tone"
                                        />
                                        <Badge
                                            variant="outline"
                                            :class="
                                                cn(
                                                    'font-medium',
                                                    parseToneClasses[
                                                        row.parse_status
                                                    ] ??
                                                        parseToneClasses.pending,
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'imports.show.rowsSection.parsing',
                                                    {
                                                        status: row.parse_status_label,
                                                    },
                                                )
                                            }}
                                        </Badge>
                                        <Button
                                            v-if="row.approve_duplicate_url"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-full border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-900/50 dark:text-sky-300 dark:hover:bg-sky-950/30"
                                            @click="
                                                openApproveDuplicateDialog(row)
                                            "
                                        >
                                            <Flame class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'imports.show.actions.forceImport',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="row.can_edit_review"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-full"
                                            @click="openReviewDialog(row)"
                                        >
                                            <Pencil class="mr-2 size-4" />
                                            {{ t('imports.show.actions.edit') }}
                                        </Button>
                                        <Button
                                            v-if="row.can_skip"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-full"
                                            :disabled="
                                                activeRowActionUuid === row.uuid
                                            "
                                            @click="openSkipDialog(row)"
                                        >
                                            <SkipForward class="mr-2 size-4" />
                                            {{
                                                activeRowActionUuid === row.uuid
                                                    ? t(
                                                          'imports.show.actions.skipping',
                                                      )
                                                    : t(
                                                          'imports.show.actions.skip',
                                                      )
                                            }}
                                        </Button>
                                        <CollapsibleTrigger as-child>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="rounded-full"
                                            >
                                                {{
                                                    t(
                                                        'imports.show.actions.details',
                                                    )
                                                }}
                                                <ChevronDown
                                                    class="ml-2 size-4"
                                                />
                                            </Button>
                                        </CollapsibleTrigger>
                                    </div>
                                </div>

                                <CollapsibleContent class="pt-4">
                                    <div
                                        class="space-y-4 border-t border-slate-200 pt-4 dark:border-slate-800"
                                    >
                                        <div
                                            v-if="
                                                rowStatusFallbackMessages(row)
                                                    .length > 0 ||
                                                row.errors.length > 0
                                            "
                                            class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 dark:border-rose-900/50 dark:bg-rose-950/30"
                                        >
                                            <div
                                                class="mb-2 flex items-center gap-2 text-sm font-semibold text-rose-800 dark:text-rose-200"
                                            >
                                                <ShieldAlert class="size-4" />
                                                {{
                                                    row.errors.length > 0
                                                        ? t(
                                                              'imports.show.rowsSection.errorsTitle',
                                                          )
                                                        : t(
                                                              'imports.show.rowsSection.feedbackTitle',
                                                          )
                                                }}
                                            </div>
                                            <ul
                                                class="space-y-1 text-sm text-rose-700 dark:text-rose-200"
                                            >
                                                <li
                                                    v-for="message in row.errors
                                                        .length > 0
                                                        ? row.errors.map(
                                                              localizeImportFeedbackMessage,
                                                          )
                                                        : rowStatusFallbackMessages(
                                                              row,
                                                          )"
                                                    :key="message"
                                                >
                                                    {{ message }}
                                                </li>
                                            </ul>
                                        </div>

                                        <div
                                            v-if="row.warnings.length > 0"
                                            class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 dark:border-amber-900/50 dark:bg-amber-950/30"
                                        >
                                            <div
                                                class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-200"
                                            >
                                                <AlertTriangle class="size-4" />
                                                {{
                                                    t(
                                                        'imports.show.rowsSection.warningsTitle',
                                                    )
                                                }}
                                            </div>
                                            <ul
                                                class="space-y-1 text-sm text-amber-700 dark:text-amber-200"
                                            >
                                                <li
                                                    v-for="warning in row.warnings"
                                                    :key="warning"
                                                >
                                                    {{
                                                        localizeImportFeedbackMessage(
                                                            warning,
                                                        )
                                                    }}
                                                </li>
                                            </ul>
                                        </div>

                                        <div class="grid gap-4 xl:grid-cols-2">
                                            <ImportPayloadList
                                                :title="
                                                    t(
                                                        'imports.show.rowsSection.rawData',
                                                    )
                                                "
                                                :items="row.raw_payload"
                                                :empty-label="
                                                    t(
                                                        'imports.show.rowsSection.rawEmpty',
                                                    )
                                                "
                                            />
                                            <ImportPayloadList
                                                :title="
                                                    t(
                                                        'imports.show.rowsSection.normalizedData',
                                                    )
                                                "
                                                :items="row.normalized_payload"
                                                :empty-label="
                                                    t(
                                                        'imports.show.rowsSection.normalizedEmpty',
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </div>
                        </Collapsible>
                    </div>
                </section>
            </div>

            <Dialog
                :open="skipDialogOpen"
                @update:open="handleSkipDialogOpenChange"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            t('imports.show.skipDialog.title')
                        }}</DialogTitle>
                        <DialogDescription class="leading-6">
                            {{ t('imports.show.skipDialog.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="rowPendingSkip"
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-sm dark:border-slate-800 dark:bg-slate-900/60"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <div
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'imports.show.rowsSection.columns.date',
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        rowPendingSkip.date ??
                                        t(
                                            'imports.show.rowsSection.unavailable',
                                        )
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'imports.show.rowsSection.columns.amount',
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        formatImportAmount(
                                            rowPendingSkip.amount_value_raw,
                                            rowPendingSkip.amount,
                                        )
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'imports.show.rowsSection.columns.row',
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-slate-950 dark:text-slate-50"
                                >
                                    {{ rowPendingSkip.row_index }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t('imports.show.rowsSection.columns.detail')
                                }}
                            </div>
                            <div class="mt-1 text-slate-950 dark:text-slate-50">
                                {{
                                    rowPendingSkip.description ??
                                    t(
                                        'imports.show.rowsSection.detailUnavailable',
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            class="rounded-full"
                            @click="handleSkipDialogOpenChange(false)"
                        >
                            {{ t('imports.show.actions.cancel') }}
                        </Button>
                        <Button
                            class="rounded-full bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-600"
                            :disabled="
                                !rowPendingSkip ||
                                activeRowActionUuid === rowPendingSkip?.uuid
                            "
                            @click="submitSkip"
                        >
                            <SkipForward class="mr-2 size-4" />
                            {{
                                activeRowActionUuid === rowPendingSkip?.uuid
                                    ? t('imports.show.actions.skipping')
                                    : t('imports.show.actions.skipRow')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="approveDuplicateDialogOpen"
                @update:open="handleApproveDuplicateDialogOpenChange"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            t('imports.show.duplicateDialog.title')
                        }}</DialogTitle>
                        <DialogDescription class="leading-6">
                            {{ t('imports.show.duplicateDialog.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="rowPendingDuplicateApproval"
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-sm dark:border-slate-800 dark:bg-slate-900/60"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <div
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'imports.show.rowsSection.columns.date',
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        rowPendingDuplicateApproval.date ??
                                        t(
                                            'imports.show.rowsSection.unavailable',
                                        )
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'imports.show.rowsSection.columns.amount',
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        formatImportAmount(
                                            rowPendingDuplicateApproval.amount_value_raw,
                                            rowPendingDuplicateApproval.amount,
                                        )
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'imports.show.rowsSection.columns.row',
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-slate-950 dark:text-slate-50"
                                >
                                    {{ rowPendingDuplicateApproval.row_index }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t('imports.show.rowsSection.columns.detail')
                                }}
                            </div>
                            <div class="mt-1 text-slate-950 dark:text-slate-50">
                                {{
                                    rowPendingDuplicateApproval.description ??
                                    t(
                                        'imports.show.rowsSection.detailUnavailable',
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            class="rounded-full"
                            @click="
                                handleApproveDuplicateDialogOpenChange(false)
                            "
                        >
                            {{ t('imports.show.actions.cancel') }}
                        </Button>
                        <Button
                            class="rounded-full"
                            :disabled="
                                !rowPendingDuplicateApproval?.approve_duplicate_url ||
                                activeRowActionUuid ===
                                    rowPendingDuplicateApproval?.uuid
                            "
                            @click="submitApproveDuplicate"
                        >
                            <Flame class="mr-2 size-4" />
                            {{
                                activeRowActionUuid ===
                                rowPendingDuplicateApproval?.uuid
                                    ? t('imports.show.actions.sending')
                                    : t('imports.show.actions.forceImport')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="rollbackDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            t('imports.show.rollbackDialog.title')
                        }}</DialogTitle>
                        <DialogDescription class="leading-6">
                            {{ t('imports.show.rollbackDialog.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            class="rounded-full"
                            @click="rollbackDialogOpen = false"
                        >
                            {{ t('imports.show.actions.close') }}
                        </Button>
                        <Button
                            class="rounded-full bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-600"
                            :disabled="activeAction === 'rollback'"
                            @click="submitRollback"
                        >
                            <RotateCcw class="mr-2 size-4" />
                            {{
                                activeAction === 'rollback'
                                    ? t(
                                          'imports.show.actions.rollbackInProgress',
                                      )
                                    : t('imports.show.actions.confirmRollback')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ImportRowReviewDialog
                v-model:open="reviewDialogOpen"
                :row="reviewRow"
                :destination-accounts="props.destination_accounts"
                :categories="props.categories"
                @saved="handleReviewSaved"
            />

            <Dialog v-model:open="deleteImportDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            t('imports.show.deleteDialog.title')
                        }}</DialogTitle>
                        <DialogDescription class="leading-6">
                            {{ t('imports.show.deleteDialog.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            class="rounded-full"
                            @click="deleteImportDialogOpen = false"
                        >
                            {{ t('imports.show.actions.cancel') }}
                        </Button>
                        <Button
                            class="rounded-full bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-600"
                            :disabled="activeAction === 'delete'"
                            @click="submitDeleteImport"
                        >
                            <Trash2 class="mr-2 size-4" />
                            {{
                                activeAction === 'delete'
                                    ? t('imports.show.actions.deleteInProgress')
                                    : t('imports.show.actions.deleteImport')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
