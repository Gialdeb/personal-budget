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
import { cn } from '@/lib/utils';
import {
    importReady as importReadyRoute,
    index as importsRoute,
    rollback as rollbackRoute,
} from '@/routes/imports';
import type {
    BreadcrumbItem,
    ImportRowItem,
    ImportsShowPageProps,
} from '@/types';

const props = defineProps<ImportsShowPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Importazioni',
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

type ImportRowFilter = 'all' | 'review' | 'invalid' | 'duplicate' | 'ready' | 'imported' | 'skipped';

const rowFilter = ref<ImportRowFilter>('all');
const rowFilterOptions = computed(() => [
    { value: 'all' as ImportRowFilter, label: 'Tutte', count: props.rows.length },
    { value: 'review' as ImportRowFilter, label: 'Da rivedere', count: props.rows.filter((row) => row.status === 'needs_review').length },
    { value: 'invalid' as ImportRowFilter, label: 'Non valide', count: props.rows.filter((row) => ['invalid', 'blocked_year'].includes(row.status)).length },
    { value: 'duplicate' as ImportRowFilter, label: 'Duplicate', count: props.rows.filter((row) => ['duplicate_candidate', 'already_imported'].includes(row.status)).length },
    { value: 'ready' as ImportRowFilter, label: 'Pronte', count: props.rows.filter((row) => row.status === 'ready').length },
    { value: 'imported' as ImportRowFilter, label: 'Importate', count: props.rows.filter((row) => row.status === 'imported').length },
    { value: 'skipped' as ImportRowFilter, label: 'Saltate', count: props.rows.filter((row) => row.status === 'skipped').length },
]);
const filteredRows = computed(() => {
    return props.rows.filter((row) => {
        switch (rowFilter.value) {
            case 'review':
                return row.status === 'needs_review';
            case 'invalid':
                return ['invalid', 'blocked_year'].includes(row.status);
            case 'duplicate':
                return ['duplicate_candidate', 'already_imported'].includes(row.status);
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
    <Head :title="`Importazione · ${props.importDetail.original_filename}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_22rem]">
                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader class="gap-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <ImportStatusBadge
                                        :label="props.importDetail.status_label"
                                        :tone="props.importDetail.status_tone"
                                    />
                                    <span
                                        class="text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{ props.importDetail.parser_label }}
                                    </span>
                                </div>
                                <CardTitle
                                    class="text-2xl text-slate-950 dark:text-slate-50"
                                >
                                    {{ props.importDetail.original_filename }}
                                </CardTitle>
                                <CardDescription class="text-sm leading-6">
                                    {{
                                        props.importDetail.management_year_label
                                    }}
                                    <span> · </span>
                                    {{
                                        props.importDetail.account_name ??
                                        'Conto non disponibile'
                                    }}
                                    <span v-if="props.importDetail.bank_name">
                                        · {{ props.importDetail.bank_name }}
                                    </span>
                                    <span
                                        v-if="
                                            props.importDetail.imported_at_label
                                        "
                                    >
                                        · caricata il
                                        {{
                                            props.importDetail.imported_at_label
                                        }}
                                    </span>
                                </CardDescription>
                            </div>

                            <Button
                                as-child
                                variant="outline"
                                class="rounded-full"
                            >
                                <Link :href="importsRoute()">
                                    <ArrowLeft class="mr-2 size-4" />
                                    Torna alla lista
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
                                Annulla import
                            </Button>
                            <Button
                                v-if="props.importDetail.can_delete"
                                variant="outline"
                                class="rounded-full border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
                                @click="deleteImportDialogOpen = true"
                            >
                                <Trash2 class="mr-2 size-4" />
                                Elimina import
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
                                Righe totali
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
                                Pronte
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
                                Da rivedere
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
                                Non valide
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
                                Duplicate
                            </div>
                            <div
                                class="mt-2 text-3xl font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ props.importDetail.duplicate_rows_count }}
                            </div>
                        </div>
                        <div
                            class="rounded-2xl border border-sky-200 bg-sky-50/80 p-4 dark:border-sky-900/50 dark:bg-sky-950/30"
                        >
                            <div
                                class="text-xs tracking-[0.18em] text-sky-700 uppercase dark:text-sky-300"
                            >
                                Già importate
                            </div>
                            <div
                                class="mt-2 text-3xl font-semibold text-sky-800 dark:text-sky-200"
                            >
                                {{ props.importDetail.imported_rows_count }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader>
                        <CardTitle class="text-lg">Scheda import</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-slate-500 dark:text-slate-400"
                                >Formato</span
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
                            <span class="text-slate-500 dark:text-slate-400"
                                >Completata il</span
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
                            <span class="text-slate-500 dark:text-slate-400"
                                >Fallita il</span
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
                            <span class="text-slate-500 dark:text-slate-400"
                                >Rollback il</span
                            >
                            <span
                                class="text-right font-medium text-slate-950 dark:text-slate-50"
                            >
                                {{ props.importDetail.rolled_back_at_label }}
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
                    Questa importazione è stata validata sull’anno gestionale
                    {{ props.importDetail.management_year }}.
                </AlertDescription>
            </Alert>

            <Alert
                v-if="flash.success"
                class="border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200"
            >
                <CircleCheckBig class="size-4" />
                <AlertTitle>Azione completata</AlertTitle>
                <AlertDescription>{{ flash.success }}</AlertDescription>
            </Alert>

            <Alert
                v-if="errors.import"
                class="border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
            >
                <ShieldAlert class="size-4" />
                <AlertTitle>Importazione non completata</AlertTitle>
                <AlertDescription>{{ errors.import }}</AlertDescription>
            </Alert>

            <Alert
                v-if="props.importDetail.error_message"
                class="border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
            >
                <FileWarning class="size-4" />
                <AlertTitle>Errore importazione</AlertTitle>
                <AlertDescription>{{
                    props.importDetail.error_message
                }}</AlertDescription>
            </Alert>

            <Alert
                v-if="props.importDetail.blocked_year_rows_count > 0"
                class="border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
            >
                <AlertTriangle class="size-4" />
                <AlertTitle>Righe fuori anno gestionale</AlertTitle>
                <AlertDescription>
                    {{ props.importDetail.blocked_year_rows_count }}
                    {{
                        props.importDetail.blocked_year_rows_count === 1
                            ? 'riga non rientra'
                            : 'righe non rientrano'
                    }}
                    nell’anno gestionale
                    {{ props.importDetail.management_year }} e vanno corrette
                    nel file CSV.
                </AlertDescription>
            </Alert>

            <Alert
                v-if="props.importDetail.status === 'rolled_back'"
                class="border-slate-300 bg-slate-100 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            >
                <RotateCcw class="size-4" />
                <AlertTitle>Import annullato</AlertTitle>
                <AlertDescription>
                    Questa importazione è stata annullata. Le righe importate
                    sono state riportate allo stato di rollback.
                </AlertDescription>
            </Alert>

            <section class="space-y-4">
                <div
                    class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/80 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div>
                        <h2
                            class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Righe importate
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Vista operativa compatta delle righe, con dettagli
                            apribili solo quando servono.
                        </p>
                    </div>

                    <div class="flex flex-col items-start gap-2 lg:items-end">
                        <Button
                            v-if="props.importDetail.can_import_ready"
                            class="rounded-full"
                            :disabled="activeAction === 'import'"
                            @click="submitImportReady"
                        >
                            <Upload class="mr-2 size-4" />
                            {{
                                activeAction === 'import'
                                    ? 'Importazione righe in corso...'
                                    : 'Importa righe pronte'
                            }}
                        </Button>
                        <p
                            v-if="props.importDetail.can_import_ready"
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{
                                props.importDetail.ready_rows_count === 1
                                    ? '1 riga pronta da promuovere nelle transazioni.'
                                    : `${props.importDetail.ready_rows_count} righe pronte da promuovere nelle transazioni.`
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                        <Filter class="size-3.5" />
                        Filtra righe
                    </div>
                    <Button
                        v-for="filterOption in rowFilterOptions"
                        :key="filterOption.value"
                        :variant="rowFilter === filterOption.value ? 'default' : 'outline'"
                        size="sm"
                        class="rounded-full"
                        @click="rowFilter = filterOption.value"
                    >
                        {{ filterOption.label }}
                        <span class="ml-2 rounded-full bg-black/10 px-2 py-0.5 text-[11px] dark:bg-white/10">
                            {{ filterOption.count }}
                        </span>
                    </Button>
                </div>

                <div
                    v-if="props.rows.length === 0"
                    class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                >
                    Nessuna riga disponibile per questa importazione.
                </div>

                <div
                    v-else-if="filteredRows.length === 0"
                    class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                >
                    Nessuna riga corrisponde al filtro selezionato.
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
                                            Riga
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
                                            Data
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                        >
                                            {{ row.date ?? 'Non disponibile' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Tipo
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                row.type_label ??
                                                'Non disponibile'
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Importo
                                        </div>
                                        <div
                                            class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                row.amount ?? 'Non disponibile'
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Dettaglio
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                row.description ??
                                                'Dettaglio non disponibile'
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Categoria
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                row.category_label ??
                                                'Da verificare'
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
                                        Pronta per l'import
                                    </Badge>
                                    <Badge
                                        v-if="row.is_imported"
                                        variant="outline"
                                        class="border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-300"
                                    >
                                        Già promossa in transazione
                                    </Badge>
                                    <Badge
                                        v-if="row.is_blocked"
                                        variant="outline"
                                        class="border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
                                    >
                                        Bloccata
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
                                                ] ?? parseToneClasses.pending,
                                            )
                                        "
                                    >
                                        Parsing: {{ row.parse_status_label }}
                                    </Badge>
                                    <Button
                                        v-if="row.approve_duplicate_url"
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-900/50 dark:text-sky-300 dark:hover:bg-sky-950/30"
                                        @click="openApproveDuplicateDialog(row)"
                                    >
                                        <Flame class="mr-2 size-4" />
                                        Forza import
                                    </Button>
                                    <Button
                                        v-if="row.can_edit_review"
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full"
                                        @click="openReviewDialog(row)"
                                    >
                                        <Pencil class="mr-2 size-4" />
                                        Modifica
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
                                                ? 'Salto in corso...'
                                                : 'Salta'
                                        }}
                                    </Button>
                                    <CollapsibleTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="rounded-full"
                                        >
                                            Dettagli
                                            <ChevronDown class="ml-2 size-4" />
                                        </Button>
                                    </CollapsibleTrigger>
                                </div>
                            </div>

                            <CollapsibleContent class="pt-4">
                                <div
                                    class="space-y-4 border-t border-slate-200 pt-4 dark:border-slate-800"
                                >
                                    <div
                                        v-if="row.errors.length > 0"
                                        class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 dark:border-rose-900/50 dark:bg-rose-950/30"
                                    >
                                        <div
                                            class="mb-2 flex items-center gap-2 text-sm font-semibold text-rose-800 dark:text-rose-200"
                                        >
                                            <ShieldAlert class="size-4" />
                                            Errori da gestire
                                        </div>
                                        <ul
                                            class="space-y-1 text-sm text-rose-700 dark:text-rose-200"
                                        >
                                            <li
                                                v-for="error in row.errors"
                                                :key="error"
                                            >
                                                {{ error }}
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
                                            Warning operativi
                                        </div>
                                        <ul
                                            class="space-y-1 text-sm text-amber-700 dark:text-amber-200"
                                        >
                                            <li
                                                v-for="warning in row.warnings"
                                                :key="warning"
                                            >
                                                {{ warning }}
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="grid gap-4 xl:grid-cols-2">
                                        <ImportPayloadList
                                            title="Dati letti dal file"
                                            :items="row.raw_payload"
                                            empty-label="La riga non espone dati raw."
                                        />
                                        <ImportPayloadList
                                            title="Dati normalizzati"
                                            :items="row.normalized_payload"
                                            empty-label="La riga non espone dati normalizzati."
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
                    <DialogTitle>Saltare questa riga?</DialogTitle>
                    <DialogDescription class="leading-6">
                        La riga verrà esclusa dal flusso corrente di import e
                        non sarà importata nelle transazioni finché non verrà
                        eventualmente rivalutata in seguito.
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
                                Data
                            </div>
                            <div class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ rowPendingSkip.date ?? 'Non disponibile' }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Importo
                            </div>
                            <div
                                class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                            >
                                {{ rowPendingSkip.amount ?? 'Non disponibile' }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Riga
                            </div>
                            <div class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ rowPendingSkip.row_index }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div
                            class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            Dettaglio
                        </div>
                        <div class="mt-1 text-slate-950 dark:text-slate-50">
                            {{
                                rowPendingSkip.description ??
                                'Dettaglio non disponibile'
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
                        Annulla
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
                                ? 'Salto in corso...'
                                : 'Salta riga'
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
                    <DialogTitle>Confermare il duplicato candidato?</DialogTitle>
                    <DialogDescription class="leading-6">
                        La riga verrà approvata manualmente e tornerà tra quelle pronte per l'import.
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
                                Data
                            </div>
                            <div class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ rowPendingDuplicateApproval.date ?? 'Non disponibile' }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Importo
                            </div>
                            <div class="mt-1 font-medium text-slate-950 dark:text-slate-50">
                                {{ rowPendingDuplicateApproval.amount ?? 'Non disponibile' }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Riga
                            </div>
                            <div class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ rowPendingDuplicateApproval.row_index }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div
                            class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            Dettaglio
                        </div>
                        <div class="mt-1 text-slate-950 dark:text-slate-50">
                            {{ rowPendingDuplicateApproval.description ?? 'Dettaglio non disponibile' }}
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="handleApproveDuplicateDialogOpenChange(false)"
                    >
                        Annulla
                    </Button>
                    <Button
                        class="rounded-full"
                        :disabled="
                            !rowPendingDuplicateApproval?.approve_duplicate_url ||
                            activeRowActionUuid === rowPendingDuplicateApproval?.uuid
                        "
                        @click="submitApproveDuplicate"
                    >
                        <Flame class="mr-2 size-4" />
                        {{
                            activeRowActionUuid === rowPendingDuplicateApproval?.uuid
                                ? 'Invio in corso...'
                                : 'Forza import'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="rollbackDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Annullare questa importazione?</DialogTitle>
                    <DialogDescription class="leading-6">
                        L'azione elimina le transazioni create da questo import
                        e porta le righe importate allo stato “Annullata”. Usala
                        solo se l'import è stato già promosso e vuoi tornare
                        indietro.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="rollbackDialogOpen = false"
                    >
                        Chiudi
                    </Button>
                    <Button
                        class="rounded-full bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-600"
                        :disabled="activeAction === 'rollback'"
                        @click="submitRollback"
                    >
                        <RotateCcw class="mr-2 size-4" />
                        {{
                            activeAction === 'rollback'
                                ? 'Rollback in corso...'
                                : 'Conferma rollback'
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
                    <DialogTitle>Eliminare questo import?</DialogTitle>
                    <DialogDescription class="leading-6">
                        L'azione rimuove definitivamente dallo storico un import gia rollbackato e senza effetti contabili residui.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="deleteImportDialogOpen = false"
                    >
                        Annulla
                    </Button>
                    <Button
                        class="rounded-full bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-600"
                        :disabled="activeAction === 'delete'"
                        @click="submitDeleteImport"
                    >
                        <Trash2 class="mr-2 size-4" />
                        {{ activeAction === 'delete' ? 'Eliminazione in corso...' : 'Elimina import' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
