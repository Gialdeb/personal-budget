<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCheck,
    CircleX,
    Copy,
    LoaderCircle,
    PanelTop,
    Sparkles,
    TriangleAlert,
    CalendarDays,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BudgetPlanningGridDesktop from '@/components/budget-planning/BudgetPlanningGridDesktop.vue';
import BudgetPlanningMobileList from '@/components/budget-planning/BudgetPlanningMobileList.vue';
import BudgetSummaryCards from '@/components/budget-planning/BudgetSummaryCards.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    applyBudgetCellUpdate,
    cloneBudgetPlanningData,
} from '@/lib/budget-planning';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { budgetPlanning as budgetPlanningRoute } from '@/routes';
import { copyPreviousYear, updateCell } from '@/routes/budget-planning';
import { edit as editYears } from '@/routes/years';
import type {
    BreadcrumbItem,
    BudgetCellSaveState,
    BudgetPlanningData,
    BudgetPlanningPageProps,
} from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<BudgetPlanningPageProps>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('planning.title'),
        href: budgetPlanningRoute(),
    },
];

const planning = ref<BudgetPlanningData>(
    cloneBudgetPlanningData(props.budgetPlanning),
);
const selectedGroup = ref('all');
const collapsedRows = ref<string[]>([]);
const collapsedSections = ref<string[]>([]);
const cellStates = ref<Record<string, BudgetCellSaveState>>({});
const requestVersions = ref<Record<string, number>>({});
const feedback = ref<FeedbackState | null>(null);
const copyingPreviousYear = ref(false);
const closedYearAlertDismissed = ref(false);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.budgetPlanning,
    (value) => {
        planning.value = cloneBudgetPlanningData(value);
        cellStates.value = {};
        closedYearAlertDismissed.value = false;
    },
);

watch(feedback, (value) => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }

    if (!value) {
        return;
    }

    feedbackTimeout = setTimeout(() => {
        feedback.value = null;
        feedbackTimeout = null;
    }, 4000);
});

onUnmounted(() => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }
});

const currency = computed(() => planning.value.settings.base_currency || 'EUR');
const yearValue = computed(() => String(planning.value.filters.year));
const currentCalendarYear = new Date().getFullYear();
const isCurrentCalendarYear = computed(
    () => planning.value.filters.year === currentCalendarYear,
);
const activeYearNotice = computed(() =>
    isCurrentCalendarYear.value
        ? null
        : t('planning.activeYearNotice', {
              selectedYear: planning.value.filters.year,
              currentYear: currentCalendarYear,
          }),
);
const isClosedYear = computed(() => planning.value.meta.year_is_closed);
const visibleClosedYearAlert = computed(
    () => isClosedYear.value && !closedYearAlertDismissed.value,
);
const visibleSections = computed(() =>
    selectedGroup.value === 'all'
        ? planning.value.sections
        : planning.value.sections.filter(
              (section) => section.key === selectedGroup.value,
          ),
);
const savingCount = computed(
    () =>
        Object.values(cellStates.value).filter((state) => state === 'saving')
            .length,
);
const errorCount = computed(
    () =>
        Object.values(cellStates.value).filter((state) => state === 'error')
            .length,
);
const hasSavedCells = computed(
    () =>
        Object.values(cellStates.value).filter((state) => state === 'saved')
            .length > 0,
);
const saveIndicator = computed(() => {
    if (savingCount.value > 0) {
        return {
            tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            label: t('planning.save.saving'),
            icon: LoaderCircle,
            spinning: true,
        };
    }

    if (errorCount.value > 0) {
        return {
            tone: 'bg-rose-500/12 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            label: t('planning.save.checkErrors'),
            icon: TriangleAlert,
            spinning: false,
        };
    }

    if (hasSavedCells.value) {
        return {
            tone: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            label: t('planning.save.saved'),
            icon: CheckCheck,
            spinning: false,
        };
    }

    return {
        tone: 'bg-slate-900/6 text-slate-600 dark:bg-white/8 dark:text-slate-300',
        label: t('planning.save.autosave'),
        icon: Sparkles,
        spinning: false,
    };
});

function handleYearSelection(value: unknown): void {
    const year = Number(value);

    if (!Number.isInteger(year)) {
        return;
    }

    router.get(
        budgetPlanningRoute.url({
            query: {
                year,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['budgetPlanning', 'transactionsNavigation'],
        },
    );
}

function toggleRow(rowUuid: string): void {
    collapsedRows.value = collapsedRows.value.includes(rowUuid)
        ? collapsedRows.value.filter((value) => value !== rowUuid)
        : [...collapsedRows.value, rowUuid];
}

function handleGroupSelection(value: unknown): void {
    selectedGroup.value = String(value);
}

function toggleSection(sectionKey: string): void {
    collapsedSections.value = collapsedSections.value.includes(sectionKey)
        ? collapsedSections.value.filter((value) => value !== sectionKey)
        : [...collapsedSections.value, sectionKey];
}

async function saveCell(payload: {
    categoryUuid: string;
    month: number;
    amount: number;
}): Promise<void> {
    if (isClosedYear.value) {
        feedback.value = {
            variant: 'destructive',
            title: t('planning.closedYear.title'),
            message:
                planning.value.meta.closed_year_message ??
                t('planning.closedYear.fallback'),
        };

        return;
    }

    const key = buildCellKey(payload.categoryUuid, payload.month);
    const currentAmount = findRowAmount(payload.categoryUuid, payload.month);
    const version = (requestVersions.value[key] ?? 0) + 1;

    requestVersions.value[key] = version;
    cellStates.value[key] = 'saving';
    applyBudgetCellUpdate(
        planning.value,
        payload.categoryUuid,
        payload.month,
        payload.amount,
    );

    try {
        const response = await fetch(updateCell.url(), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                year: planning.value.filters.year,
                month: payload.month,
                category_uuid: payload.categoryUuid,
                amount: payload.amount,
            }),
        });

        if (!response.ok) {
            const message = await extractResponseErrorMessage(response);

            if (requestVersions.value[key] !== version) {
                return;
            }

            applyBudgetCellUpdate(
                planning.value,
                payload.categoryUuid,
                payload.month,
                currentAmount,
            );
            cellStates.value[key] = 'error';
            feedback.value = {
                variant: 'destructive',
                title: t('planning.feedback.saveFailedTitle'),
                message:
                    message.trim() !== ''
                        ? message
                        : t('planning.feedback.saveFailedFallback'),
            };
            resetCellState(key, 'error');

            return;
        }

        if (requestVersions.value[key] !== version) {
            return;
        }

        cellStates.value[key] = 'saved';
        resetCellState(key, 'saved');
    } catch (error) {
        if (requestVersions.value[key] !== version) {
            return;
        }

        applyBudgetCellUpdate(
            planning.value,
            payload.categoryUuid,
            payload.month,
            currentAmount,
        );
        cellStates.value[key] = 'error';
        feedback.value = {
            variant: 'destructive',
            title: t('planning.feedback.saveFailedTitle'),
            message:
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : t('planning.feedback.saveFailedFallback'),
        };
        resetCellState(key, 'error');
        console.error('Failed to save budget planning cell.', error);
    }
}

async function copyValuesFromPreviousYear(): Promise<void> {
    if (
        copyingPreviousYear.value ||
        !planning.value.meta.copy_previous_year_available ||
        isClosedYear.value
    ) {
        return;
    }

    copyingPreviousYear.value = true;

    try {
        const response = await fetch(copyPreviousYear.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                year: planning.value.filters.year,
            }),
        });

        if (!response.ok) {
            const message = await extractResponseErrorMessage(response);

            feedback.value = {
                variant: 'destructive',
                title: t('planning.feedback.copyFailedTitle'),
                message:
                    message.trim() !== ''
                        ? message
                        : t('planning.feedback.copyFailedFallback'),
            };

            return;
        }

        const data = (await response.json()) as {
            budgetPlanning: BudgetPlanningData;
            message: string;
        };

        planning.value = cloneBudgetPlanningData(data.budgetPlanning);
        cellStates.value = {};
        feedback.value = {
            variant: 'default',
            title: t('planning.feedback.copiedTitle'),
            message: data.message,
        };
    } catch (error) {
        feedback.value = {
            variant: 'destructive',
            title: t('planning.feedback.copyFailedTitle'),
            message:
                error instanceof Error && error.message.trim() !== ''
                    ? error.message
                    : t('planning.feedback.copyFailedFallback'),
        };
        console.error('Failed to copy previous budget planning year.', error);
    } finally {
        copyingPreviousYear.value = false;
    }
}

function buildCellKey(categoryUuid: string, month: number): string {
    return `${categoryUuid}:${month}`;
}

function findRowAmount(categoryUuid: string, month: number): number {
    for (const section of planning.value.sections) {
        const row = section.flat_rows.find(
            (item) => item.uuid === categoryUuid,
        );

        if (row) {
            return row.monthly_amounts_raw[month - 1] ?? 0;
        }
    }

    return 0;
}

function resetCellState(
    key: string,
    expectedState: Exclude<BudgetCellSaveState, 'idle'>,
): void {
    window.setTimeout(
        () => {
            if (cellStates.value[key] === expectedState) {
                delete cellStates.value[key];
            }
        },
        expectedState === 'error' ? 3500 : 1800,
    );
}

function readCsrfToken(): string {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ?? '';
}

async function extractResponseErrorMessage(
    response: Response,
): Promise<string> {
    try {
        const payload = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[] | string>;
        };

        const firstError = Object.values(payload.errors ?? {})[0];

        if (Array.isArray(firstError) && firstError[0]) {
            return firstError[0];
        }

        if (typeof firstError === 'string' && firstError !== '') {
            return firstError;
        }

        if (payload.message) {
            return payload.message;
        }
    } catch {
        return '';
    }

    return '';
}
</script>

<template>
    <Head :title="t('planning.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-[28px] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.14),_transparent_38%),linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.16),_transparent_38%),linear-gradient(135deg,rgba(2,6,23,0.95),rgba(15,23,42,0.9))]"
            >
                <div class="space-y-4 p-4 md:hidden">
                    <div class="flex items-center justify-between gap-2">
                        <Badge
                            class="rounded-full bg-sky-500/12 px-3 py-1 text-[11px] text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                        >
                            <PanelTop class="mr-1 size-3" />
                            {{ t('planning.annualBadge') }}
                        </Badge>
                        <Badge
                            :class="
                                cn(
                                    'rounded-full px-3 py-1 text-[11px]',
                                    saveIndicator.tone,
                                )
                            "
                        >
                            <component
                                :is="saveIndicator.icon"
                                :class="
                                    cn(
                                        'mr-1 size-3',
                                        saveIndicator.spinning
                                            ? 'animate-spin'
                                            : '',
                                    )
                                "
                            />
                            {{ saveIndicator.label }}
                        </Badge>
                    </div>

                    <div class="space-y-1">
                        <h1
                            class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ t('planning.heading') }}
                        </h1>
                        <p
                            class="text-xs leading-5 text-slate-600 dark:text-slate-300"
                        >
                            {{ t('planning.description') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <Select
                            :model-value="yearValue"
                            @update:model-value="handleYearSelection"
                        >
                            <SelectTrigger
                                class="h-10 rounded-full border-white/70 bg-white/90 px-3 text-sm dark:border-white/10 dark:bg-slate-950/70"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in planning.filters
                                        .available_years"
                                    :key="option.value"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select
                            :model-value="selectedGroup"
                            @update:model-value="handleGroupSelection"
                        >
                            <SelectTrigger
                                class="h-10 rounded-full border-white/70 bg-white/90 px-3 text-sm dark:border-white/10 dark:bg-slate-950/70"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in planning.filters
                                        .group_options"
                                    :key="option.value"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 rounded-full border-white/70 bg-white/90 text-sm dark:border-white/10 dark:bg-slate-950/70"
                        :disabled="
                            !planning.meta.copy_previous_year_available ||
                            copyingPreviousYear ||
                            isClosedYear
                        "
                        @click="copyValuesFromPreviousYear"
                    >
                        <LoaderCircle
                            v-if="copyingPreviousYear"
                            class="mr-2 size-4 animate-spin"
                        />
                        <Copy v-else class="mr-2 size-4" />
                        {{
                            t('planning.actions.copyPreviousYear', {
                                year: planning.meta.previous_year,
                            })
                        }}
                    </Button>
                </div>

                <div
                    class="hidden gap-6 p-5 md:grid lg:grid-cols-[minmax(0,1fr)_auto] lg:p-7"
                >
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Badge
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                <PanelTop class="mr-1 size-3.5" />
                                {{ t('planning.annualBadge') }}
                            </Badge>
                            <Badge
                                :class="
                                    cn(
                                        'rounded-full px-3 py-1',
                                        saveIndicator.tone,
                                    )
                                "
                            >
                                <component
                                    :is="saveIndicator.icon"
                                    :class="
                                        cn(
                                            'mr-1 size-3.5',
                                            saveIndicator.spinning
                                                ? 'animate-spin'
                                                : '',
                                        )
                                    "
                                />
                                {{ saveIndicator.label }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ t('planning.heading') }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('planning.description') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[400px]">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('planning.filters.year') }}
                            </p>
                            <Select
                                :model-value="yearValue"
                                @update:model-value="handleYearSelection"
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in planning.filters
                                            .available_years"
                                        :key="option.value"
                                        :value="String(option.value)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('planning.filters.macrogroup') }}
                            </p>
                            <Select
                                :model-value="selectedGroup"
                                @update:model-value="handleGroupSelection"
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in planning.filters
                                            .group_options"
                                        :key="option.value"
                                        :value="String(option.value)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 rounded-2xl border-white/70 bg-white/90 sm:col-span-2 dark:border-white/10 dark:bg-slate-950/70"
                            :disabled="
                                !planning.meta.copy_previous_year_available ||
                                copyingPreviousYear ||
                                isClosedYear
                            "
                            @click="copyValuesFromPreviousYear"
                        >
                            <LoaderCircle
                                v-if="copyingPreviousYear"
                                class="mr-2 size-4 animate-spin"
                            />
                            <Copy v-else class="mr-2 size-4" />
                            {{
                                t('planning.actions.copyPreviousYear', {
                                    year: planning.meta.previous_year,
                                })
                            }}
                        </Button>
                    </div>
                </div>
            </section>

            <Alert
                v-if="feedback"
                :class="
                    feedback.variant === 'destructive'
                        ? 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100'
                "
            >
                <AlertTitle>{{ feedback.title }}</AlertTitle>
                <AlertDescription>{{ feedback.message }}</AlertDescription>
            </Alert>

            <Alert
                v-if="activeYearNotice"
                class="border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
            >
                <CalendarDays class="size-4" />
                <AlertTitle>{{
                    t('planning.activeYearAlertTitle')
                }}</AlertTitle>
                <AlertDescription>
                    {{ activeYearNotice }}
                </AlertDescription>
            </Alert>

            <Alert
                v-if="visibleClosedYearAlert"
                class="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <TriangleAlert class="size-4" />
                <AlertTitle>{{ t('planning.closedYear.title') }}</AlertTitle>
                <AlertDescription
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span>
                        {{ planning.meta.closed_year_message }}
                    </span>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl border-amber-300/80 bg-white/80 dark:border-amber-300/20 dark:bg-slate-950/60"
                            @click="router.get(editYears())"
                        >
                            {{ t('planning.actions.goToYears') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            class="h-9 rounded-2xl text-amber-900 hover:bg-amber-100 dark:text-amber-100 dark:hover:bg-amber-500/10"
                            @click="closedYearAlertDismissed = true"
                        >
                            <CircleX class="mr-2 size-4" />
                            {{ t('planning.actions.hide') }}
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>

            <Alert
                v-if="planning.meta.year_suggestion"
                class="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <CalendarDays class="size-4" />
                <AlertTitle>
                    {{ planning.meta.year_suggestion.title }}
                </AlertTitle>
                <AlertDescription
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span>
                        {{ planning.meta.year_suggestion.message }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-2xl border-amber-300/80 bg-white/80 dark:border-amber-300/20 dark:bg-slate-950/60"
                        @click="router.get(editYears())"
                    >
                        {{ t('planning.actions.createYear') }}
                    </Button>
                </AlertDescription>
            </Alert>

            <BudgetSummaryCards
                :cards="planning.summary_cards"
                :currency="currency"
            />

            <Card
                class="overflow-hidden border-white/70 bg-white/85 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
            >
                <CardContent class="space-y-4 p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('planning.overview.monthlyTotals') }}
                            </p>
                            <h2
                                class="text-lg font-semibold text-slate-950 dark:text-white"
                            >
                                {{ t('planning.overview.yearlySummary') }}
                            </h2>
                        </div>
                        <p
                            class="text-right text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{
                                t('planning.overview.editableCategories', {
                                    count: planning.meta.selectable_rows_count,
                                })
                            }}
                        </p>
                    </div>

                    <div class="grid gap-3 overflow-x-auto">
                        <div class="grid min-w-[980px] grid-cols-12 gap-3">
                            <div
                                v-for="(month, index) in planning.months"
                                :key="month.value"
                                class="rounded-2xl border border-slate-200/70 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-slate-900/70"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ month.short_label }}
                                </p>
                                <p
                                    class="mt-2 text-sm font-semibold text-slate-950 dark:text-white"
                                >
                                    <SensitiveValue
                                        :value="
                                            formatCurrency(
                                                planning.column_totals_raw[
                                                    index
                                                ],
                                                currency,
                                            )
                                        "
                                    />
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white dark:bg-white dark:text-slate-950"
                    >
                        {{ t('planning.overview.yearlyTotal') }}
                        <SensitiveValue
                            :value="
                                formatCurrency(
                                    planning.grand_total_raw,
                                    currency,
                                )
                            "
                        />
                    </div>
                </CardContent>
            </Card>

            <BudgetPlanningGridDesktop
                :months="planning.months"
                :sections="visibleSections"
                :currency="currency"
                :collapsed-rows="collapsedRows"
                :collapsed-sections="collapsedSections"
                :cell-states="cellStates"
                :readonly="isClosedYear"
                @toggle-row="toggleRow"
                @toggle-section="toggleSection"
                @save-cell="saveCell"
            />

            <BudgetPlanningMobileList
                :months="planning.months"
                :sections="visibleSections"
                :currency="currency"
                :collapsed-rows="collapsedRows"
                :cell-states="cellStates"
                :readonly="isClosedYear"
                @toggle-row="toggleRow"
                @save-cell="saveCell"
            />
        </div>
    </AppLayout>
</template>
