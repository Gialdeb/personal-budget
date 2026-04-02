<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Archive,
    CalendarRange,
    Download,
    FileJson,
    FileSpreadsheet,
    Landmark,
    Layers3,
    ReceiptText,
    Repeat2,
    Route,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { download, edit } from '@/routes/exports';
import type {
    BreadcrumbItem,
    ExportDatasetDefinition,
    ExportDatasetKey,
    ExportFormatKey,
    ExportPageProps,
    ExportPeriodPresetKey,
} from '@/types';

const props = defineProps<ExportPageProps>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.sections.exports'),
        href: edit(),
    },
];

const datasetIcons: Record<ExportDatasetKey, unknown> = {
    transactions: ReceiptText,
    accounts: Landmark,
    categories: Layers3,
    tracked_items: Route,
    recurring_entries: Repeat2,
    budgets: CalendarRange,
    full_export: Archive,
};

const formatIcons: Record<ExportFormatKey, unknown> = {
    csv: FileSpreadsheet,
    json: FileJson,
};

const selectedDatasetKey = ref<ExportDatasetKey>(
    props.exportPage.defaults.dataset,
);
const selectedFormatKey = ref<ExportFormatKey>(
    props.exportPage.defaults.format,
);
const selectedPeriodPreset = ref<ExportPeriodPresetKey>(
    props.exportPage.defaults.period_preset,
);
const startDate = ref('');
const endDate = ref('');

const selectedDataset = computed<ExportDatasetDefinition>(() => {
    return (
        props.exportPage.datasets.find(
            ({ key }) => key === selectedDatasetKey.value,
        ) ?? props.exportPage.datasets[0]
    );
});

const periodIsApplicable = computed(
    () => selectedDataset.value.supports_period,
);
const customRangeSelected = computed(
    () =>
        periodIsApplicable.value &&
        selectedPeriodPreset.value === 'custom_range',
);
const customRangeReady = computed(
    () => startDate.value !== '' && endDate.value !== '',
);

watch(
    selectedDataset,
    (dataset) => {
        if (!dataset.formats.includes(selectedFormatKey.value)) {
            selectedFormatKey.value = dataset.default_format;
        }

        if (!dataset.supports_period) {
            selectedPeriodPreset.value = 'all_time';
            startDate.value = '';
            endDate.value = '';
        } else if (selectedPeriodPreset.value === 'all_time') {
            selectedPeriodPreset.value =
                props.exportPage.defaults.period_preset;
        }
    },
    { immediate: true },
);

const exportReady = computed(() => {
    if (!periodIsApplicable.value) {
        return true;
    }

    if (!customRangeSelected.value) {
        return true;
    }

    return customRangeReady.value;
});

const selectedDatasetLabel = computed(() =>
    t(
        `export.datasets.${datasetTranslationKey(selectedDatasetKey.value)}.label`,
    ),
);
const selectedFormatLabel = computed(() =>
    t(`export.formats.${selectedFormatKey.value}.label`),
);

const periodLabel = computed(() => {
    if (!periodIsApplicable.value) {
        return t('export.period.labels.allTime');
    }

    if (selectedPeriodPreset.value === 'custom_range') {
        if (!customRangeReady.value) {
            return t('export.period.presets.customRange');
        }

        return t('export.period.labels.customRange', {
            start: startDate.value,
            end: endDate.value,
        });
    }

    return t(
        `export.period.presets.${presetTranslationKey(selectedPeriodPreset.value)}`,
    );
});

const downloadHref = computed(() =>
    download.url({
        query: {
            dataset: selectedDatasetKey.value,
            format: selectedFormatKey.value,
            period_preset: periodIsApplicable.value
                ? selectedPeriodPreset.value
                : 'all_time',
            ...(customRangeSelected.value
                ? {
                      start_date: startDate.value,
                      end_date: endDate.value,
                  }
                : {}),
        },
    }),
);

const actionLabel = computed(() =>
    selectedFormatKey.value === 'csv'
        ? t('export.actions.exportCsv')
        : t('export.actions.exportJson'),
);

function triggerDownload(): void {
    if (!exportReady.value) {
        return;
    }

    window.location.assign(downloadHref.value);
}

function datasetTranslationKey(dataset: ExportDatasetKey): string {
    return {
        transactions: 'transactions',
        accounts: 'accounts',
        categories: 'categories',
        tracked_items: 'trackedItems',
        recurring_entries: 'recurringEntries',
        budgets: 'budgets',
        full_export: 'fullExport',
    }[dataset];
}

function presetTranslationKey(preset: ExportPeriodPresetKey): string {
    return {
        all_time: 'allTime',
        this_month: 'thisMonth',
        last_month: 'lastMonth',
        this_year: 'thisYear',
        custom_range: 'customRange',
    }[preset];
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.sections.exports')" />

        <SettingsLayout>
            <div class="space-y-5" data-test="settings-export-page">
                <section
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-900 px-5 py-6 text-white md:px-8 md:py-8 dark:border-slate-800"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-[0.24em] text-cyan-100/80 uppercase"
                        >
                            {{ t('export.heroEyebrow') }}
                        </p>
                        <div
                            class="mt-4 flex items-start justify-between gap-4"
                        >
                            <Heading
                                variant="small"
                                class="max-w-2xl text-white"
                                :title="t('export.title')"
                                :description="t('export.description')"
                            />

                            <div
                                class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 md:flex"
                            >
                                <Download class="h-5 w-5" />
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid gap-5 px-4 py-4 md:px-6 md:py-6 xl:grid-cols-[minmax(0,1fr)_320px]"
                    >
                        <div class="space-y-5">
                            <section
                                class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/40"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ t('export.steps.dataset') }}
                                </p>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <button
                                        v-for="dataset in props.exportPage
                                            .datasets"
                                        :key="dataset.key"
                                        type="button"
                                        :class="[
                                            'rounded-[1.4rem] border p-4 text-left transition-all',
                                            selectedDatasetKey === dataset.key
                                                ? 'border-slate-950 bg-slate-950 text-white shadow-[0_18px_40px_-30px_rgba(15,23,42,0.8)] dark:border-slate-100 dark:bg-slate-100 dark:text-slate-950'
                                                : 'border-slate-200 bg-white text-slate-950 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:hover:border-slate-700',
                                        ]"
                                        @click="
                                            selectedDatasetKey = dataset.key
                                        "
                                    >
                                        <div
                                            :class="[
                                                'flex h-10 w-10 items-center justify-center rounded-2xl border',
                                                selectedDatasetKey ===
                                                dataset.key
                                                    ? 'border-white/15 bg-white/10 text-white dark:border-slate-300/40 dark:bg-slate-200 dark:text-slate-950'
                                                    : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
                                            ]"
                                        >
                                            <component
                                                :is="datasetIcons[dataset.key]"
                                                class="h-4 w-4"
                                            />
                                        </div>

                                        <p class="mt-4 text-sm font-semibold">
                                            {{
                                                t(
                                                    `export.datasets.${datasetTranslationKey(dataset.key)}.label`,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-sm leading-5"
                                            :class="
                                                selectedDatasetKey ===
                                                dataset.key
                                                    ? 'text-white/70 dark:text-slate-600'
                                                    : 'text-slate-500 dark:text-slate-400'
                                            "
                                        >
                                            {{
                                                t(
                                                    `export.datasets.${datasetTranslationKey(dataset.key)}.description`,
                                                )
                                            }}
                                        </p>
                                    </button>
                                </div>
                            </section>

                            <section
                                class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/40"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ t('export.steps.period') }}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('export.period.description') }}
                                </p>

                                <div
                                    v-if="periodIsApplicable"
                                    class="mt-4 space-y-4"
                                >
                                    <div class="grid gap-2 md:grid-cols-5">
                                        <button
                                            v-for="preset in props.exportPage
                                                .period_presets"
                                            :key="preset.key"
                                            type="button"
                                            :class="[
                                                'rounded-2xl border px-3 py-3 text-sm font-medium transition-all',
                                                selectedPeriodPreset ===
                                                preset.key
                                                    ? 'border-cyan-500 bg-cyan-500 text-slate-950 shadow-[0_12px_28px_-22px_rgba(6,182,212,0.9)]'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700',
                                            ]"
                                            @click="
                                                selectedPeriodPreset =
                                                    preset.key
                                            "
                                        >
                                            {{
                                                t(
                                                    `export.period.presets.${presetTranslationKey(preset.key)}`,
                                                )
                                            }}
                                        </button>
                                    </div>

                                    <div
                                        v-if="customRangeSelected"
                                        class="grid gap-3 md:grid-cols-2"
                                    >
                                        <label class="space-y-2">
                                            <span
                                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                            >
                                                {{
                                                    t('export.period.startDate')
                                                }}
                                            </span>
                                            <input
                                                v-model="startDate"
                                                type="date"
                                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 transition outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/15 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                            />
                                        </label>

                                        <label class="space-y-2">
                                            <span
                                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                            >
                                                {{ t('export.period.endDate') }}
                                            </span>
                                            <input
                                                v-model="endDate"
                                                type="date"
                                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 transition outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/15 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                            />
                                        </label>
                                    </div>

                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            customRangeSelected
                                                ? t('export.period.customHint')
                                                : periodLabel
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="mt-4 rounded-[1.25rem] border border-dashed border-slate-300 bg-white/75 px-4 py-4 dark:border-slate-700 dark:bg-slate-950/60"
                                >
                                    <p
                                        class="text-sm font-semibold text-slate-900 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'export.period.notApplicableTitle',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'export.period.notApplicableDescription',
                                            )
                                        }}
                                    </p>
                                </div>
                            </section>

                            <section
                                class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/40"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ t('export.steps.format') }}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('export.formats.description') }}
                                </p>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <button
                                        v-for="format in selectedDataset.formats"
                                        :key="format"
                                        type="button"
                                        :class="[
                                            'rounded-[1.4rem] border p-4 text-left transition-all',
                                            selectedFormatKey === format
                                                ? 'border-slate-950 bg-slate-950 text-white shadow-[0_18px_40px_-30px_rgba(15,23,42,0.8)] dark:border-slate-100 dark:bg-slate-100 dark:text-slate-950'
                                                : 'border-slate-200 bg-white text-slate-950 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:hover:border-slate-700',
                                        ]"
                                        @click="selectedFormatKey = format"
                                    >
                                        <div
                                            :class="[
                                                'flex h-10 w-10 items-center justify-center rounded-2xl border',
                                                selectedFormatKey === format
                                                    ? 'border-white/15 bg-white/10 text-white dark:border-slate-300/40 dark:bg-slate-200 dark:text-slate-950'
                                                    : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
                                            ]"
                                        >
                                            <component
                                                :is="formatIcons[format]"
                                                class="h-4 w-4"
                                            />
                                        </div>

                                        <p class="mt-4 text-sm font-semibold">
                                            {{
                                                t(
                                                    `export.formats.${format}.label`,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-sm leading-5"
                                            :class="
                                                selectedFormatKey === format
                                                    ? 'text-white/70 dark:text-slate-600'
                                                    : 'text-slate-500 dark:text-slate-400'
                                            "
                                        >
                                            {{
                                                t(
                                                    `export.formats.${format}.description`,
                                                )
                                            }}
                                        </p>
                                    </button>
                                </div>
                            </section>
                        </div>

                        <aside class="xl:sticky xl:top-6 xl:self-start">
                            <section
                                class="rounded-[1.7rem] border border-slate-200/80 bg-white p-4 shadow-[0_24px_64px_-44px_rgba(15,23,42,0.35)] dark:border-slate-800 dark:bg-slate-950"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ t('export.steps.summary') }}
                                </p>

                                <h2
                                    class="mt-3 text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('export.summary.title') }}
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('export.summary.description') }}
                                </p>

                                <dl class="mt-5 space-y-4 text-sm">
                                    <div
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/50"
                                    >
                                        <dt
                                            class="text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{ t('export.summary.dataset') }}
                                        </dt>
                                        <dd
                                            class="mt-2 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{ selectedDatasetLabel }}
                                        </dd>
                                    </div>

                                    <div
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/50"
                                    >
                                        <dt
                                            class="text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{ t('export.summary.period') }}
                                        </dt>
                                        <dd
                                            class="mt-2 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{ periodLabel }}
                                        </dd>
                                    </div>

                                    <div
                                        class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/50"
                                    >
                                        <dt
                                            class="text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{ t('export.summary.format') }}
                                        </dt>
                                        <dd
                                            class="mt-2 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{ selectedFormatLabel }}
                                        </dd>
                                    </div>
                                </dl>

                                <p
                                    class="mt-5 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('export.summary.machineFriendly') }}
                                </p>

                                <p
                                    v-if="
                                        customRangeSelected && !customRangeReady
                                    "
                                    class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300"
                                >
                                    {{ t('export.validation.customRange') }}
                                </p>

                                <Button
                                    class="mt-5 h-12 w-full rounded-2xl"
                                    :disabled="!exportReady"
                                    @click="triggerDownload"
                                >
                                    {{ actionLabel }}
                                </Button>
                            </section>
                        </aside>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
