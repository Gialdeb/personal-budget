<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    CircleCheckBig,
    Filter,
    FileSpreadsheet,
    FileUp,
    Files,
    SearchCheck,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ImportStatusBadge from '@/components/imports/ImportStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as importsRoute, store as storeImport } from '@/routes/imports';
import type { BreadcrumbItem, ImportsIndexPageProps } from '@/types';

const props = defineProps<ImportsIndexPageProps>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('imports.title'),
        href: importsRoute(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const pagination = computed(() => props.imports.pagination);
const deletingImport = ref<{
    uuid: string;
    original_filename: string;
    delete_url: string;
} | null>(null);
const deleteDialogOpen = ref(false);
const currentCalendarYear = new Date().getFullYear();
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    account_uuid: props.options.default_account_uuid ?? '',
    import_format_uuid: props.options.default_format_uuid ?? '',
    file: null as File | null,
});

const summaryCards = computed(() => [
    {
        label: t('imports.summary.total'),
        value: props.imports.summary.total_count,
        icon: Files,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: t('imports.summary.review'),
        value: props.imports.summary.review_required_count,
        icon: SearchCheck,
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        label: t('imports.summary.completed'),
        value: props.imports.summary.completed_count,
        icon: CircleCheckBig,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: t('imports.summary.failed'),
        value: props.imports.summary.failed_count,
        icon: CircleAlert,
        tone: 'text-rose-700 dark:text-rose-300',
    },
]);

const selectedFormat = computed(
    () =>
        props.options.formats.find(
            (format) => format.uuid === form.import_format_uuid,
        ) ?? null,
);
const managementYearLabel = computed(() =>
    t('imports.year.managementLabel', { year: props.importsPage.active_year }),
);
const managementYearNotice = computed(() =>
    t('imports.year.managementNotice', { year: props.importsPage.active_year }),
);
const localizedStatusOptions = computed(() =>
    props.filters.status_options.map((option) => ({
        ...option,
        label:
            t(`imports.index.listSection.statusLabels.${option.value}`) !==
            `imports.index.listSection.statusLabels.${option.value}`
                ? t(`imports.index.listSection.statusLabels.${option.value}`)
                : option.label,
    })),
);
const selectedFileLabel = computed(
    () => form.file?.name ?? t('imports.index.placeholders.noFileSelected'),
);
function localizeStatusBadge(status: string, fallback: string): string {
    const key = `imports.list.statusBadge.${status}`;

    return t(key) !== key ? t(key) : fallback;
}
const selectedFormatDescription = computed(() => {
    if (!selectedFormat.value) {
        return null;
    }

    if (selectedFormat.value.is_generic) {
        return `${t('imports.index.helpers.genericFormatLabel')} · ${t('imports.index.helpers.genericFormatNotes')}`;
    }

    return [
        selectedFormat.value.parser_label,
        selectedFormat.value.bank_name,
        selectedFormat.value.notes,
    ]
        .filter((value) => value && value !== '')
        .join(' · ');
});
const isCurrentCalendarYear = computed(
    () => props.importsPage.active_year === currentCalendarYear,
);
const yearContextLabel = computed(() =>
    isCurrentCalendarYear.value
        ? t('imports.year.current')
        : t('imports.year.other', {
              selectedYear: props.importsPage.active_year,
              currentYear: currentCalendarYear,
          }),
);

const canSubmit = computed(
    () =>
        form.account_uuid !== '' &&
        form.import_format_uuid !== '' &&
        form.file !== null,
);

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const [file] = target.files ?? [];

    form.file = file ?? null;
}

function openFilePicker(): void {
    fileInput.value?.click();
}

function submit(): void {
    form.post(storeImport.url(), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function filterUrl(status: string): string {
    return importsRoute({
        query: {
            status: status === 'all' ? null : status,
        },
    }).url;
}

function handleYearSelection(value: unknown): void {
    const normalizedYear =
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'bigint'
            ? String(value)
            : String(props.importsPage.active_year);

    router.get(
        importsRoute({
            query: {
                year: normalizedYear,
                status:
                    props.filters.current_status === 'all'
                        ? null
                        : props.filters.current_status,
            },
        }).url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function openDeleteDialog(item: {
    uuid: string;
    original_filename: string;
    delete_url: string | null;
}): void {
    if (!item.delete_url) {
        return;
    }

    deletingImport.value = {
        uuid: item.uuid,
        original_filename: item.original_filename,
        delete_url: item.delete_url,
    };
    deleteDialogOpen.value = true;
}

function submitDeleteImport(): void {
    if (!deletingImport.value) {
        return;
    }

    router.delete(deletingImport.value.delete_url, {
        preserveScroll: true,
        onFinish: () => {
            deleteDialogOpen.value = false;
            deletingImport.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('imports.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="space-y-6">
                <section
                    class="overflow-hidden rounded-4xl border border-slate-200/70 bg-[linear-gradient(135deg,rgba(248,250,252,0.98),rgba(255,255,255,0.96))] shadow-sm dark:border-slate-800/80 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.92),rgba(2,6,23,0.96))]"
                >
                    <div
                        class="flex flex-col gap-6 px-6 py-6 lg:flex-row lg:items-start lg:justify-between lg:px-8"
                    >
                        <div class="space-y-3">
                            <div
                                class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-sky-700 uppercase dark:bg-sky-950/40 dark:text-sky-300"
                            >
                                {{ t('imports.badge') }}
                            </div>
                            <div class="space-y-2">
                                <h1
                                    class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('imports.title') }}
                                </h1>
                                <p
                                    class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{ t('imports.description') }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex flex-col items-start gap-4 lg:items-end"
                        >
                            <Select
                                :model-value="
                                    String(props.importsPage.active_year)
                                "
                                @update:model-value="handleYearSelection"
                            >
                                <SelectTrigger
                                    class="h-11 w-42 rounded-full border px-4 text-sm font-medium shadow-sm backdrop-blur-sm transition-all duration-200 ease-out"
                                    :class="
                                        isCurrentCalendarYear
                                            ? 'border-white/70 bg-white/90 text-foreground hover:border-sky-300/50 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-sky-400/40 dark:hover:bg-white/10'
                                            : 'border-amber-200/80 bg-[linear-gradient(135deg,rgba(255,251,235,0.96),rgba(255,255,255,0.98))] text-amber-950 shadow-[0_12px_30px_-18px_rgba(245,158,11,0.75)] ring-1 ring-amber-300/60 dark:border-amber-400/25 dark:bg-[linear-gradient(135deg,rgba(120,53,15,0.24),rgba(17,24,39,0.92))] dark:text-amber-100 dark:ring-amber-300/25'
                                    "
                                >
                                    <SelectValue
                                        :placeholder="t('imports.year.label')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in props.importsPage
                                            .available_years"
                                        :key="option.value"
                                        :value="String(option.value)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <div
                                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition-all duration-200"
                                :class="
                                    isCurrentCalendarYear
                                        ? 'bg-white/70 text-slate-600 dark:bg-white/5 dark:text-slate-300'
                                        : 'bg-amber-100/90 text-amber-900 ring-1 ring-amber-200/80 dark:bg-amber-400/10 dark:text-amber-100 dark:ring-amber-300/20'
                                "
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        isCurrentCalendarYear
                                            ? 'bg-emerald-500'
                                            : 'animate-pulse bg-amber-500'
                                    "
                                />
                                {{ yearContextLabel }}
                            </div>

                            <div
                                class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
                            >
                                <CalendarClock class="size-4" />
                                {{ managementYearLabel }}
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_24rem]"
                >
                    <Card
                        class="border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader class="gap-3">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="space-y-2">
                                    <CardTitle
                                        class="text-2xl text-slate-950 dark:text-slate-50"
                                    >
                                        {{ t('imports.index.historyTitle') }}
                                    </CardTitle>
                                    <CardDescription
                                        class="max-w-2xl text-sm leading-6"
                                    >
                                        {{
                                            t(
                                                'imports.index.historyDescription',
                                            )
                                        }}
                                    </CardDescription>
                                </div>

                                <Button
                                    as-child
                                    variant="outline"
                                    class="rounded-full"
                                >
                                    <a
                                        :href="
                                            props.importsPage
                                                .template_download_url
                                        "
                                    >
                                        <FileSpreadsheet class="mr-2 size-4" />
                                        {{
                                            t('imports.index.downloadTemplate')
                                        }}
                                    </a>
                                </Button>
                            </div>

                            <div
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ managementYearNotice }}
                            </div>
                        </CardHeader>
                        <CardContent
                            class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                        >
                            <div
                                v-for="card in summaryCards"
                                :key="card.label"
                                class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="text-sm font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        {{ card.label }}
                                    </div>
                                    <component
                                        :is="card.icon"
                                        class="size-4 text-slate-400 dark:text-slate-500"
                                    />
                                </div>
                                <div
                                    class="mt-3 text-3xl font-semibold tracking-tight"
                                    :class="card.tone"
                                >
                                    {{ card.value }}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="border-slate-200 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <FileUp
                                    class="size-5 text-sky-600 dark:text-sky-300"
                                />
                                {{ t('imports.index.newImportTitle') }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t('imports.index.newImportDescription', {
                                        year: managementYearLabel,
                                    })
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Alert
                                class="border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-200"
                            >
                                <FileSpreadsheet class="size-4" />
                                <AlertTitle>{{
                                    managementYearLabel
                                }}</AlertTitle>
                                <AlertDescription>
                                    {{ managementYearNotice }}
                                </AlertDescription>
                            </Alert>

                            <div class="space-y-2">
                                <Label for="import-account">{{
                                    t('imports.index.fields.account')
                                }}</Label>
                                <Select v-model="form.account_uuid">
                                    <SelectTrigger id="import-account">
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'imports.index.placeholders.account',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="account in props.options
                                                .accounts"
                                            :key="account.uuid"
                                            :value="account.uuid"
                                        >
                                            {{ account.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('imports.index.helpers.account') }}
                                </p>
                                <p
                                    v-if="form.errors.account_uuid"
                                    class="text-sm text-rose-600 dark:text-rose-300"
                                >
                                    {{ form.errors.account_uuid }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="import-format">{{
                                    t('imports.index.fields.importFormat')
                                }}</Label>
                                <div
                                    v-if="
                                        props.options
                                            .has_single_active_format &&
                                        selectedFormat
                                    "
                                    class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/60"
                                >
                                    <div
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ selectedFormatDescription }}
                                    </div>
                                    <p
                                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'imports.index.helpers.singleFormat',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Select
                                    v-else
                                    v-model="form.import_format_uuid"
                                >
                                    <SelectTrigger id="import-format">
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'imports.index.placeholders.genericFormat',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="format in props.options
                                                .formats"
                                            :key="format.uuid"
                                            :value="format.uuid"
                                        >
                                            {{ format.parser_label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="selectedFormat"
                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ selectedFormatDescription }}
                                </p>
                                <p
                                    v-if="form.errors.import_format_uuid"
                                    class="text-sm text-rose-600 dark:text-rose-300"
                                >
                                    {{ form.errors.import_format_uuid }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="import-file">{{
                                    t('imports.index.fields.csvFile')
                                }}</Label>
                                <input
                                    id="import-file"
                                    ref="fileInput"
                                    type="file"
                                    accept=".csv,text/csv,.txt"
                                    class="hidden"
                                    @change="handleFileChange"
                                />
                                <div
                                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="rounded-full"
                                        @click="openFilePicker"
                                    >
                                        {{
                                            t(
                                                'imports.index.placeholders.chooseFile',
                                            )
                                        }}
                                    </Button>
                                    <span
                                        class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{ selectedFileLabel }}
                                    </span>
                                </div>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'imports.index.helpers.supportedHeaders',
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t('imports.index.helpers.yearOnly', {
                                            year: managementYearLabel.toLowerCase(),
                                        })
                                    }}
                                </p>
                                <p
                                    v-if="form.errors.file"
                                    class="text-sm text-rose-600 dark:text-rose-300"
                                >
                                    {{ form.errors.file }}
                                </p>
                            </div>

                            <Button
                                class="w-full rounded-full"
                                :disabled="
                                    form.processing ||
                                    !canSubmit ||
                                    props.options.formats.length === 0
                                "
                                @click="submit"
                            >
                                <FileUp class="mr-2 size-4" />
                                {{
                                    form.processing
                                        ? t('imports.index.actions.uploading')
                                        : t('imports.index.actions.upload')
                                }}
                            </Button>

                            <p
                                v-if="props.options.formats.length === 0"
                                class="text-sm text-amber-700 dark:text-amber-300"
                            >
                                {{ t('imports.index.helpers.noActiveFormat') }}
                            </p>
                        </CardContent>
                    </Card>
                </section>

                <Alert
                    v-if="flash.success"
                    class="border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200"
                >
                    <CircleCheckBig class="size-4" />
                    <AlertTitle>{{
                        t('imports.index.alerts.updated')
                    }}</AlertTitle>
                    <AlertDescription>{{ flash.success }}</AlertDescription>
                </Alert>

                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2
                                class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('imports.index.listSection.title') }}
                            </h2>
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('imports.index.listSection.description') }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                            >
                                <Filter class="size-3.5" />
                                {{
                                    t('imports.index.listSection.statusFilter')
                                }}
                            </div>
                            <Button
                                v-for="statusOption in localizedStatusOptions"
                                :key="statusOption.value"
                                :variant="
                                    props.filters.current_status ===
                                    statusOption.value
                                        ? 'default'
                                        : 'outline'
                                "
                                size="sm"
                                class="rounded-full"
                                as-child
                            >
                                <Link
                                    :href="filterUrl(statusOption.value)"
                                    preserve-scroll
                                >
                                    {{ statusOption.label }}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="props.imports.data.length === 0"
                        class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
                    >
                        {{ t('imports.index.listSection.empty') }}
                    </div>

                    <div v-else class="space-y-3">
                        <Card
                            v-for="item in props.imports.data"
                            :key="item.uuid"
                            class="border-slate-200 transition-colors hover:border-sky-300 dark:border-slate-800 dark:hover:border-sky-700"
                        >
                            <CardContent class="p-5">
                                <div
                                    class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
                                >
                                    <div class="space-y-3">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <ImportStatusBadge
                                                :label="
                                                    localizeStatusBadge(
                                                        item.status,
                                                        item.status_label,
                                                    )
                                                "
                                                :tone="item.status_tone"
                                            />
                                            <span
                                                class="text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{ item.parser_label }}
                                            </span>
                                        </div>

                                        <div>
                                            <div
                                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ item.original_filename }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                {{ item.management_year_label }}
                                                <span> · </span>
                                                {{
                                                    item.account_name ??
                                                    t(
                                                        'imports.index.listSection.accountUnavailable',
                                                    )
                                                }}
                                                <span v-if="item.bank_name">
                                                    · {{ item.bank_name }}
                                                </span>
                                                <span
                                                    v-if="
                                                        item.imported_at_label
                                                    "
                                                >
                                                    ·
                                                    {{ item.imported_at_label }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="grid gap-2 text-sm sm:grid-cols-2 xl:min-w-100 xl:grid-cols-3"
                                    >
                                        <div
                                            class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900"
                                        >
                                            <div
                                                class="text-xs tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'imports.index.listSection.rows',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ item.rows_count }}
                                            </div>
                                        </div>
                                        <div
                                            class="rounded-xl bg-emerald-50 px-3 py-2 dark:bg-emerald-950/30"
                                        >
                                            <div
                                                class="text-xs tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300"
                                            >
                                                {{
                                                    t(
                                                        'imports.index.listSection.ready',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 font-semibold text-emerald-800 dark:text-emerald-200"
                                            >
                                                {{ item.ready_rows_count }}
                                            </div>
                                        </div>
                                        <div
                                            class="rounded-xl bg-amber-50 px-3 py-2 dark:bg-amber-950/30"
                                        >
                                            <div
                                                class="text-xs tracking-[0.18em] text-amber-700 uppercase dark:text-amber-300"
                                            >
                                                {{
                                                    t(
                                                        'imports.index.listSection.review',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 font-semibold text-amber-800 dark:text-amber-200"
                                            >
                                                {{ item.review_rows_count }}
                                            </div>
                                        </div>
                                        <div
                                            class="rounded-xl bg-rose-50 px-3 py-2 dark:bg-rose-950/30"
                                        >
                                            <div
                                                class="text-xs tracking-[0.18em] text-rose-700 uppercase dark:text-rose-300"
                                            >
                                                {{
                                                    t(
                                                        'imports.index.listSection.invalid',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 font-semibold text-rose-800 dark:text-rose-200"
                                            >
                                                {{ item.invalid_rows_count }}
                                            </div>
                                        </div>
                                        <div
                                            class="rounded-xl bg-slate-100 px-3 py-2 dark:bg-slate-800"
                                        >
                                            <div
                                                class="text-xs tracking-[0.18em] text-slate-600 uppercase dark:text-slate-300"
                                            >
                                                {{
                                                    t(
                                                        'imports.index.listSection.duplicates',
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="mt-1 font-semibold text-slate-900 dark:text-slate-50"
                                            >
                                                {{ item.duplicate_rows_count }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 flex justify-end">
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            v-if="
                                                item.can_delete &&
                                                item.delete_url
                                            "
                                            variant="outline"
                                            class="rounded-full border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
                                            @click="openDeleteDialog(item)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'imports.index.actions.deleteImport',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            as-child
                                            variant="outline"
                                            class="rounded-full"
                                        >
                                            <Link :href="item.show_url">{{
                                                t(
                                                    'imports.index.actions.openDetail',
                                                )
                                            }}</Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div
                            v-if="pagination.has_pages"
                            class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950"
                        >
                            <div
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'imports.index.listSection.importsRange',
                                        {
                                            from: pagination.from ?? 0,
                                            to: pagination.to ?? 0,
                                            total: pagination.total,
                                        },
                                    )
                                }}
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-if="pagination.previous_page_url"
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="rounded-full"
                                >
                                    <Link
                                        :href="pagination.previous_page_url"
                                        preserve-scroll
                                    >
                                        <ChevronLeft class="mr-1 size-4" />
                                        {{
                                            t('imports.index.actions.previous')
                                        }}
                                    </Link>
                                </Button>

                                <Button
                                    v-for="pageLink in pagination.pages"
                                    :key="pageLink.label"
                                    as-child
                                    :variant="
                                        pageLink.active ? 'default' : 'outline'
                                    "
                                    size="sm"
                                    class="min-w-10 rounded-full"
                                >
                                    <Link :href="pageLink.url" preserve-scroll>
                                        {{ pageLink.label }}
                                    </Link>
                                </Button>

                                <Button
                                    v-if="pagination.next_page_url"
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="rounded-full"
                                >
                                    <Link
                                        :href="pagination.next_page_url"
                                        preserve-scroll
                                    >
                                        {{ t('imports.index.actions.next') }}
                                        <ChevronRight class="ml-1 size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            t('imports.index.deleteDialog.title')
                        }}</DialogTitle>
                        <DialogDescription class="leading-6">
                            {{ t('imports.index.deleteDialog.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deletingImport"
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-200"
                    >
                        {{ deletingImport.original_filename }}
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            class="rounded-full"
                            @click="deleteDialogOpen = false"
                        >
                            {{ t('imports.index.actions.cancel') }}
                        </Button>
                        <Button
                            class="rounded-full bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-600"
                            @click="submitDeleteImport"
                        >
                            <Trash2 class="mr-2 size-4" />
                            {{ t('imports.index.actions.deleteImport') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
