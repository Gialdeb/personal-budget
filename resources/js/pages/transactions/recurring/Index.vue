<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    CalendarDays,
    ChevronDown,
    ChevronUp,
    Filter,
    Pencil,
    Plus,
    Receipt,
    RotateCcw,
    ShieldCheck,
    Undo2,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import RecurringEntryFormSheet from '@/components/recurring/RecurringEntryFormSheet.vue';
import RecurringOccurrencesMobileList from '@/components/recurring/RecurringOccurrencesMobileList.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type {
    Auth,
    BreadcrumbItem,
    RecurringEntriesIndexPageProps,
    RecurringEntryIndexCard,
    RecurringMonthlyCalendarDay,
    RecurringMonthlyOccurrence,
    TransactionsNavigation,
} from '@/types';
import { show as showRecurringEntry } from '@/actions/App/Http/Controllers/RecurringEntryController.ts';
import { convert as convertOccurrence } from '@/actions/App/Http/Controllers/RecurringEntryOccurrenceController.ts';
import { refund as refundTransaction } from '@/actions/App/Http/Controllers/RecurringEntryTransactionController.ts';

type CalendarCell = {
    date: string;
    dayNumber: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    summary: RecurringMonthlyCalendarDay | null;
};

type FilterDirection = 'all' | 'income' | 'expense';
type FilterEntryType = 'all' | 'recurring' | 'installment';
type FilterStatus = 'all' | 'active' | 'cancelled';
type FilterConversion = 'all' | 'converted' | 'unconverted';
type FilterRefund = 'all' | 'refunded' | 'not_refunded';

const props = defineProps<RecurringEntriesIndexPageProps>();
const page = usePage();
const { locale, t } = useI18n();

const calendarStorageKey = 'recurring-index-calendar-collapsed';
const filtersStorageKey = 'recurring-index-filters-collapsed';
const heroStorageKey = 'recurring-index-hero-collapsed';
const isCalendarCollapsed = ref(false);
const isFiltersCollapsed = ref(false);
const isHeroCollapsed = ref(false);
const selectedAnchor = ref<string | null>(
    props.monthlyCalendar.days[0]?.anchor ?? null,
);
const highlightedEntryUuid = ref<string | null>(null);
const formOpen = ref(false);
const selectedEntry = ref<RecurringEntryIndexCard | null>(null);
const statusFilter = ref<FilterStatus>(
    (props.filters.status as FilterStatus | null) ?? 'all',
);
const directionFilter = ref<FilterDirection>(
    (props.filters.direction as FilterDirection | null) ?? 'all',
);
const entryTypeFilter = ref<FilterEntryType>(
    (props.filters.entry_type as FilterEntryType | null) ?? 'all',
);
const conversionFilter = ref<FilterConversion>('all');
const refundFilter = ref<FilterRefund>('all');
const accountFilter = ref<string>(
    props.filters.account_id !== null &&
        props.filters.account_id !== undefined &&
        props.filters.account_id !== ''
        ? String(props.filters.account_id)
        : 'all',
);
const refundDialogOccurrence = ref<RecurringMonthlyOccurrence | null>(null);

const auth = computed(() => page.props.auth as Auth);
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const flashSuccess = computed(() => flash.value.success ?? null);
const navigation = computed(
    () => page.props.transactionsNavigation as TransactionsNavigation | null,
);
const baseCurrency = computed(
    () => auth.value.user?.base_currency_code ?? 'EUR',
);
const currentCalendarYear = new Date().getFullYear();
const currentCalendarMonth = new Date().getMonth() + 1;
const yearSelectValue = computed(() => String(props.activePeriod.year));
const monthSelectValue = computed(() => String(props.activePeriod.month));
const isCurrentPeriod = computed(
    () =>
        props.activePeriod.year === currentCalendarYear &&
        props.activePeriod.month === currentCalendarMonth,
);
const isViewingCurrentCalendarYear = computed(
    () => props.activePeriod.year === currentCalendarYear,
);
const periodNotice = computed(() => {
    if (isCurrentPeriod.value) {
        return null;
    }

    return t('transactions.index.periodNotice', {
        selectedPeriod: props.activePeriod.period_label,
        currentPeriod: `${new Intl.DateTimeFormat(locale.value, { month: 'long' }).format(new Date(currentCalendarYear, currentCalendarMonth - 1, 1))} ${currentCalendarYear}`,
    });
});
const yearStatusLabel = computed(() =>
    isViewingCurrentCalendarYear.value
        ? t('transactions.navigation.periodInProgress')
        : t('transactions.sheet.alerts.periodNotCurrent'),
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('transactions.recurring.title'),
        href: '/recurring-entries',
    },
];

const weekdayLabels = computed(() =>
    Array.from({ length: 7 }, (_, index) =>
        new Intl.DateTimeFormat(locale.value, { weekday: 'short' }).format(
            new Date(Date.UTC(2026, 0, 5 + index)),
        ),
    ),
);

function monthLabel(month: number): string {
    return new Intl.DateTimeFormat(locale.value, { month: 'long' }).format(
        new Date(Date.UTC(props.activePeriod.year, month - 1, 1)),
    );
}

const calendarDayMap = computed(
    () => new Map(props.monthlyCalendar.days.map((day) => [day.date, day])),
);

const entryMap = computed(
    () => new Map(props.recurringEntries.map((entry) => [entry.uuid, entry])),
);
const smartDefaultStartDate = computed(() => {
    const today = new Date();
    const year = props.activePeriod.year;
    const monthIndex = props.activePeriod.month - 1;
    const lastDayOfMonth = new Date(year, monthIndex + 1, 0).getDate();
    const day = Math.min(today.getDate(), lastDayOfMonth);

    return `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
});

const calendarWeeks = computed<CalendarCell[][]>(() => {
    const firstOfMonth = new Date(
        Date.UTC(
            props.monthlyCalendar.year,
            props.monthlyCalendar.month - 1,
            1,
        ),
    );
    const firstWeekday = (firstOfMonth.getUTCDay() + 6) % 7;
    const gridStart = new Date(firstOfMonth);

    gridStart.setUTCDate(gridStart.getUTCDate() - firstWeekday);

    const today = new Date();
    const todayIso = new Date(
        Date.UTC(today.getFullYear(), today.getMonth(), today.getDate()),
    )
        .toISOString()
        .slice(0, 10);

    const cells = Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);

        date.setUTCDate(gridStart.getUTCDate() + index);

        const isoDate = date.toISOString().slice(0, 10);

        return {
            date: isoDate,
            dayNumber: date.getUTCDate(),
            isCurrentMonth:
                date.getUTCMonth() + 1 === props.monthlyCalendar.month,
            isToday: isoDate === todayIso,
            summary: calendarDayMap.value.get(isoDate) ?? null,
        };
    });

    return Array.from({ length: 6 }, (_, weekIndex) =>
        cells.slice(weekIndex * 7, (weekIndex + 1) * 7),
    );
});

const summaryCards = computed(() => [
    {
        key: 'plans',
        label: t('transactions.recurring.labels.plans'),
        value: props.monthlyCalendar.summary.entries_count,
        tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        icon: CalendarDays,
    },
    {
        key: 'occurrences',
        label: t('transactions.recurring.labels.occurrences'),
        value: props.monthlyCalendar.summary.occurrences_count,
        tone: 'bg-slate-900/6 text-slate-700 dark:bg-white/8 dark:text-slate-200',
        icon: Receipt,
    },
    {
        key: 'income',
        label: t('transactions.recurring.labels.plannedIncome'),
        value: formatMoney(props.monthlyCalendar.summary.planned_income_total),
        tone: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        icon: ShieldCheck,
    },
    {
        key: 'expenses',
        label: t('transactions.recurring.labels.plannedExpenses'),
        value: formatMoney(props.monthlyCalendar.summary.planned_expense_total),
        tone: 'bg-rose-500/12 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        icon: Undo2,
    },
]);

const accountFilterOptions = computed(() =>
    props.formOptions.filter_accounts.map((account) => ({
        value: String(account.value),
        label: account.label,
        accountTypeCode: account.account_type_code ?? null,
        badgeLabel: account.is_shared
            ? t('transactions.recurring.form.accountBadges.shared')
            : t('transactions.recurring.form.accountBadges.owner'),
        badgeClass: account.is_shared
            ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    })),
);

const groupedAccountFilterOptions = computed(() => {
    const paymentAccounts = accountFilterOptions.value.filter(
        (account) => account.accountTypeCode !== 'credit_card',
    );
    const creditCards = accountFilterOptions.value.filter(
        (account) => account.accountTypeCode === 'credit_card',
    );

    return [
        {
            key: 'payment_accounts',
            label: t('dashboard.filters.paymentAccountsGroup'),
            options: paymentAccounts,
        },
        {
            key: 'credit_cards',
            label: t('dashboard.filters.creditCardsGroup'),
            options: creditCards,
        },
    ].filter((group) => group.options.length > 0);
});

const selectedAccountFilterOption = computed(
    () =>
        accountFilterOptions.value.find(
            (account) => account.value === accountFilter.value,
        ) ?? null,
);
const filteredGroups = computed(() =>
    props.monthlyCalendar.days
        .map((day) => ({
            ...day,
            occurrences: day.occurrences.filter(filterOccurrence),
        }))
        .filter((day) => day.occurrences.length > 0),
);

const flatFilteredOccurrences = computed(() =>
    filteredGroups.value.flatMap((day) => day.occurrences),
);

onMounted(() => {
    const storedPreference = window.localStorage.getItem(calendarStorageKey);
    const storedFiltersPreference =
        window.localStorage.getItem(filtersStorageKey);
    const storedHeroPreference = window.localStorage.getItem(heroStorageKey);

    isCalendarCollapsed.value = storedPreference === 'true';
    isFiltersCollapsed.value = storedFiltersPreference === 'true';
    isHeroCollapsed.value = storedHeroPreference === 'true';

    window.addEventListener(
        'app:mobile-primary-action',
        handleMobilePrimaryAction as EventListener,
    );

    void focusHighlightedRecurringEntry();
});

onBeforeUnmount(() => {
    window.removeEventListener(
        'app:mobile-primary-action',
        handleMobilePrimaryAction as EventListener,
    );
});

watch(isCalendarCollapsed, (value) => {
    window.localStorage.setItem(calendarStorageKey, value ? 'true' : 'false');
});

watch(isFiltersCollapsed, (value) => {
    window.localStorage.setItem(filtersStorageKey, value ? 'true' : 'false');
});

watch(isHeroCollapsed, (value) => {
    window.localStorage.setItem(heroStorageKey, value ? 'true' : 'false');
});

function formatMoney(value: number, currency?: string | null): string {
    return formatCurrency(
        value,
        currency ?? baseCurrency.value,
        auth.value.user?.format_locale,
    );
}

function formatDayHeading(date: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date(`${date}T00:00:00`));
}

function resetFilters(): void {
    statusFilter.value = 'all';
    directionFilter.value = 'all';
    entryTypeFilter.value = 'all';
    conversionFilter.value = 'all';
    refundFilter.value = 'all';

    if (accountFilter.value !== 'all') {
        accountFilter.value = 'all';
        handleAccountSelection('all');
    }
}

function filterOccurrence(occurrence: RecurringMonthlyOccurrence): boolean {
    if (
        statusFilter.value !== 'all' &&
        occurrence.recurring_entry?.status !== statusFilter.value
    ) {
        return false;
    }

    if (
        directionFilter.value !== 'all' &&
        occurrence.direction !== directionFilter.value
    ) {
        return false;
    }

    if (
        entryTypeFilter.value !== 'all' &&
        occurrence.entry_type !== entryTypeFilter.value
    ) {
        return false;
    }

    const isConverted = occurrence.converted_transaction !== null;
    const isRefunded =
        occurrence.status === 'refunded' ||
        occurrence.converted_transaction?.is_refunded === true;

    if (conversionFilter.value === 'converted' && !isConverted) {
        return false;
    }

    if (conversionFilter.value === 'unconverted' && isConverted) {
        return false;
    }

    if (refundFilter.value === 'refunded' && !isRefunded) {
        return false;
    }

    return refundFilter.value !== 'not_refunded' || !isRefunded;
}

function openCreateForm(): void {
    selectedEntry.value = null;
    formOpen.value = true;
}

function consumeCreateRecurringEntryQuery(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const url = new URL(window.location.href);

    if (url.searchParams.get('create') !== '1') {
        return false;
    }

    url.searchParams.delete('create');
    window.history.replaceState(window.history.state, '', url);

    return true;
}

watch(
    () => page.url,
    () => {
        if (consumeCreateRecurringEntryQuery()) {
            openCreateForm();
        }
    },
    { immediate: true },
);

function handleMobilePrimaryAction(event: Event): void {
    const customEvent = event as CustomEvent<{ kind?: string }>;

    if (customEvent.detail?.kind !== 'recurring') {
        return;
    }

    customEvent.preventDefault();
    openCreateForm();
}

function handleYearSelection(value: string): void {
    const year = Number(value);

    if (!Number.isInteger(year) || year === props.activePeriod.year) {
        return;
    }

    router.get(
        '/recurring-entries',
        {
            year,
            month: props.activePeriod.month,
            account_id:
                accountFilter.value !== 'all' ? accountFilter.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function handleMonthSelection(value: string): void {
    const month = Number(value);

    if (
        !Number.isInteger(month) ||
        month < 1 ||
        month > 12 ||
        month === props.activePeriod.month
    ) {
        return;
    }

    router.get(
        '/recurring-entries',
        {
            year: props.activePeriod.year,
            month,
            account_id:
                accountFilter.value !== 'all' ? accountFilter.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function handleAccountSelection(value: string): void {
    accountFilter.value = value;

    router.get(
        '/recurring-entries',
        {
            year: props.activePeriod.year,
            month: props.activePeriod.month,
            account_id: value !== 'all' ? value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function openEditForm(entryUuid: string): void {
    selectedEntry.value = entryMap.value.get(entryUuid) ?? null;
    formOpen.value = true;
}

function readHighlightedRecurringEntryUuid(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const value = new URLSearchParams(window.location.search).get('highlight');

    return value && value.trim() !== '' ? value : null;
}

function focusHighlightedRecurringEntry(): Promise<void> {
    const entryUuid = readHighlightedRecurringEntryUuid();

    highlightedEntryUuid.value = entryUuid;

    if (!entryUuid) {
        return Promise.resolve();
    }

    return nextTick(() => {
        document
            .querySelector<HTMLElement>(
                `[data-recurring-entry-row="${entryUuid}"]`,
            )
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
    });
}

function jumpToDay(day: RecurringMonthlyCalendarDay): void {
    selectedAnchor.value = day.anchor;
    const targetId =
        typeof window !== 'undefined' &&
        window.matchMedia('(max-width: 1023px)').matches
            ? `${day.anchor}-mobile`
            : day.anchor;

    document.getElementById(targetId)?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function entryTypeLabel(value: string | null): string {
    if (!value) {
        return t('app.common.notAvailable');
    }

    return t(`transactions.recurring.enums.entryType.${value}`);
}

function planStatusLabel(value: string | null): string {
    if (!value) {
        return t('app.common.notAvailable');
    }

    return t(`transactions.recurring.enums.planStatus.${value}`);
}

function directionLabel(value: string | null): string {
    if (!value) {
        return t('app.common.notAvailable');
    }

    return t(`transactions.recurring.enums.direction.${value}`);
}

function recurrenceState(occurrence: RecurringMonthlyOccurrence): {
    label: string;
    tone: string;
} {
    if (
        occurrence.status === 'refunded' ||
        occurrence.converted_transaction?.is_refunded === true
    ) {
        return {
            label: t('transactions.recurring.table.refundedBadge'),
            tone: 'bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        };
    }

    if (occurrence.converted_transaction !== null) {
        return {
            label: t('transactions.recurring.table.convertedBadge'),
            tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        };
    }

    return {
        label: t('transactions.recurring.table.pendingBadge'),
        tone: 'bg-slate-900/6 text-slate-700 dark:bg-white/8 dark:text-slate-300',
    };
}

function convertRow(occurrence: RecurringMonthlyOccurrence): void {
    if (!occurrence.recurring_entry || !occurrence.can_convert) {
        return;
    }

    router.post(
        convertOccurrence.url([
            occurrence.recurring_entry.uuid,
            occurrence.uuid,
        ]),
        {},
        {
            preserveScroll: true,
        },
    );
}

function openRefundDialog(occurrence: RecurringMonthlyOccurrence): void {
    refundDialogOccurrence.value = occurrence;
}

function refundOccurrence(): void {
    const transaction = refundDialogOccurrence.value?.converted_transaction;

    if (!transaction) {
        return;
    }

    router.post(
        refundTransaction.url(transaction.uuid),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                refundDialogOccurrence.value = null;
            },
        },
    );
}

function rowToneClass(occurrence: RecurringMonthlyOccurrence): string {
    if (
        occurrence.status === 'refunded' ||
        occurrence.converted_transaction?.is_refunded
    ) {
        return 'bg-amber-50/80 dark:bg-amber-500/8';
    }

    if (occurrence.converted_transaction) {
        return 'bg-sky-50/70 dark:bg-sky-500/8';
    }

    return 'bg-white dark:bg-slate-950/20';
}

function rowAccentClass(occurrence: RecurringMonthlyOccurrence): string {
    if (
        occurrence.status === 'refunded' ||
        occurrence.converted_transaction?.is_refunded
    ) {
        return 'border-l-4 border-amber-400';
    }

    if (occurrence.converted_transaction) {
        return 'border-l-4 border-sky-400';
    }

    return '';
}

function linkedEntry(
    occurrence: RecurringMonthlyOccurrence,
): RecurringEntryIndexCard | null {
    if (!occurrence.recurring_entry) {
        return null;
    }

    return entryMap.value.get(occurrence.recurring_entry.uuid) ?? null;
}

function filteredOccurrencesCount(day: RecurringMonthlyCalendarDay): number {
    return day.occurrences.filter(filterOccurrence).length;
}
</script>

<template>
    <Head
        :title="`${t('transactions.recurring.title')} · ${props.activePeriod.period_label}`"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-[30px] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_38%),radial-gradient(circle_at_bottom_right,_rgba(34,197,94,0.16),_transparent_34%),linear-gradient(135deg,rgba(255,255,255,0.97),rgba(248,250,252,0.93))] shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.14),_transparent_38%),radial-gradient(circle_at_bottom_right,_rgba(34,197,94,0.14),_transparent_34%),linear-gradient(135deg,rgba(2,6,23,0.95),rgba(15,23,42,0.91))]"
            >
                <div class="space-y-4 p-5 md:hidden">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                <CalendarDays class="mr-1 size-3.5" />
                                {{ t('transactions.recurring.badge') }}
                            </Badge>
                            <Badge
                                class="rounded-full bg-white/80 px-3 py-1 text-slate-700 dark:bg-slate-950/70 dark:text-slate-200"
                            >
                                {{ t('transactions.recurring.activePeriod') }}:
                                {{ props.activePeriod.period_label }}
                            </Badge>
                        </div>

                        <div class="space-y-1">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ t('transactions.recurring.title') }}
                            </h1>
                            <p
                                v-if="!isHeroCollapsed"
                                class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('transactions.recurring.description') }}
                            </p>
                        </div>

                        <Button
                            variant="outline"
                            class="h-11 w-full rounded-2xl px-4"
                            :aria-expanded="!isHeroCollapsed"
                            @click="isHeroCollapsed = !isHeroCollapsed"
                        >
                            <ChevronUp
                                v-if="!isHeroCollapsed"
                                class="mr-2 size-4"
                            />
                            <ChevronDown v-else class="mr-2 size-4" />
                            {{
                                isHeroCollapsed
                                    ? t(
                                          'transactions.recurring.actions.expandOverview',
                                      )
                                    : t(
                                          'transactions.recurring.actions.collapseOverview',
                                      )
                            }}
                        </Button>
                    </div>

                    <div v-if="!isHeroCollapsed" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2">
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ t('transactions.sheet.filters.year') }}
                                </p>
                                <Select
                                    v-if="navigation"
                                    :model-value="yearSelectValue"
                                    @update:model-value="
                                        handleYearSelection(
                                            String($event ?? ''),
                                        )
                                    "
                                >
                                    <SelectTrigger
                                        :class="
                                            cn(
                                                'h-11 rounded-2xl border px-4 text-sm font-medium shadow-sm backdrop-blur-sm transition-all duration-200 ease-out',
                                                isViewingCurrentCalendarYear
                                                    ? 'border-white/70 bg-white/90 text-foreground hover:border-sky-400/35 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-sky-400/45 dark:hover:bg-white/10'
                                                    : 'border-amber-200/80 bg-[linear-gradient(135deg,rgba(255,251,235,0.96),rgba(255,255,255,0.98))] text-amber-950 shadow-[0_12px_30px_-18px_rgba(245,158,11,0.75)] ring-1 ring-amber-300/60 dark:border-amber-400/25 dark:bg-[linear-gradient(135deg,rgba(120,53,15,0.24),rgba(17,24,39,0.92))] dark:text-amber-100 dark:ring-amber-300/25',
                                            )
                                        "
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in navigation.context
                                                .available_years"
                                            :key="option"
                                            :value="String(option)"
                                        >
                                            {{ option }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ t('transactions.sheet.filters.month') }}
                                </p>
                                <Select
                                    :model-value="monthSelectValue"
                                    @update:model-value="
                                        handleMonthSelection(
                                            String($event ?? ''),
                                        )
                                    "
                                >
                                    <SelectTrigger
                                        class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-white/5"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="month in 12"
                                            :key="month"
                                            :value="String(month)"
                                        >
                                            {{ monthLabel(month) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div
                            :class="
                                cn(
                                    'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition-all duration-200',
                                    isViewingCurrentCalendarYear
                                        ? 'bg-white/70 text-muted-foreground dark:bg-white/5'
                                        : 'bg-amber-100/90 text-amber-900 ring-1 ring-amber-200/80 dark:bg-amber-400/10 dark:text-amber-100 dark:ring-amber-300/20',
                                )
                            "
                        >
                            <span
                                :class="
                                    cn(
                                        'size-2 rounded-full',
                                        isViewingCurrentCalendarYear
                                            ? 'bg-emerald-500'
                                            : 'animate-pulse bg-amber-500',
                                    )
                                "
                            />
                            {{ yearStatusLabel }}
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <article
                                v-for="card in summaryCards"
                                :key="`${card.key}-mobile`"
                                class="rounded-[24px] border border-white/70 bg-white/82 p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <p
                                            class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{ card.label }}
                                        </p>
                                        <p
                                            class="text-2xl font-semibold text-slate-950 dark:text-white"
                                        >
                                            <SensitiveValue
                                                v-if="
                                                    [
                                                        'income',
                                                        'expenses',
                                                    ].includes(card.key)
                                                "
                                                variant="veil"
                                                :value="card.value"
                                            />
                                            <template v-else>
                                                {{ card.value }}
                                            </template>
                                        </p>
                                    </div>
                                    <div
                                        class="flex size-10 items-center justify-center rounded-2xl"
                                        :class="card.tone"
                                    >
                                        <component
                                            :is="card.icon"
                                            class="size-5"
                                        />
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>

                <div class="hidden space-y-6 p-5 md:block lg:p-7">
                    <div
                        class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between"
                    >
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                                >
                                    <CalendarDays class="mr-1 size-3.5" />
                                    {{ t('transactions.recurring.badge') }}
                                </Badge>
                                <Badge
                                    class="rounded-full bg-white/80 px-3 py-1 text-slate-700 dark:bg-slate-950/70 dark:text-slate-200"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.activePeriod',
                                        )
                                    }}: {{ props.activePeriod.period_label }}
                                </Badge>
                            </div>

                            <div class="space-y-2">
                                <h1
                                    class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                                >
                                    {{ t('transactions.recurring.title') }}
                                </h1>
                                <p
                                    class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t('transactions.recurring.description')
                                    }}
                                </p>
                            </div>

                            <Button
                                class="hidden h-11 rounded-2xl px-4 md:inline-flex"
                                @click="openCreateForm"
                            >
                                <Plus class="mr-2 size-4" />
                                {{ t('transactions.recurring.actions.create') }}
                            </Button>
                        </div>

                        <div
                            class="flex flex-col items-start gap-4 xl:items-end"
                        >
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{
                                            t('transactions.sheet.filters.year')
                                        }}
                                    </p>
                                    <Select
                                        v-if="navigation"
                                        :model-value="yearSelectValue"
                                        @update:model-value="
                                            handleYearSelection(
                                                String($event ?? ''),
                                            )
                                        "
                                    >
                                        <SelectTrigger
                                            :class="
                                                cn(
                                                    'h-11 w-[168px] rounded-2xl border px-4 text-sm font-medium shadow-sm backdrop-blur-sm transition-all duration-200 ease-out',
                                                    isViewingCurrentCalendarYear
                                                        ? 'border-white/70 bg-white/90 text-foreground hover:border-sky-400/35 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-sky-400/45 dark:hover:bg-white/10'
                                                        : 'border-amber-200/80 bg-[linear-gradient(135deg,rgba(255,251,235,0.96),rgba(255,255,255,0.98))] text-amber-950 shadow-[0_12px_30px_-18px_rgba(245,158,11,0.75)] ring-1 ring-amber-300/60 dark:border-amber-400/25 dark:bg-[linear-gradient(135deg,rgba(120,53,15,0.24),rgba(17,24,39,0.92))] dark:text-amber-100 dark:ring-amber-300/25',
                                                )
                                            "
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in navigation
                                                    .context.available_years"
                                                :key="option"
                                                :value="String(option)"
                                            >
                                                {{ option }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'transactions.sheet.filters.month',
                                            )
                                        }}
                                    </p>
                                    <Select
                                        :model-value="monthSelectValue"
                                        @update:model-value="
                                            handleMonthSelection(
                                                String($event ?? ''),
                                            )
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 w-[168px] rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-white/5"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="month in 12"
                                                :key="month"
                                                :value="String(month)"
                                            >
                                                {{ monthLabel(month) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div
                                :class="
                                    cn(
                                        'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition-all duration-200',
                                        isViewingCurrentCalendarYear
                                            ? 'bg-white/70 text-muted-foreground dark:bg-white/5'
                                            : 'bg-amber-100/90 text-amber-900 ring-1 ring-amber-200/80 dark:bg-amber-400/10 dark:text-amber-100 dark:ring-amber-300/20',
                                    )
                                "
                            >
                                <span
                                    :class="
                                        cn(
                                            'size-2 rounded-full',
                                            isViewingCurrentCalendarYear
                                                ? 'bg-emerald-500'
                                                : 'animate-pulse bg-amber-500',
                                        )
                                    "
                                />
                                {{ yearStatusLabel }}
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.key"
                            class="rounded-[24px] border border-white/70 bg-white/82 p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{ card.label }}
                                    </p>
                                    <p
                                        class="text-2xl font-semibold text-slate-950 dark:text-white"
                                    >
                                        <SensitiveValue
                                            v-if="
                                                ['income', 'expenses'].includes(
                                                    card.key,
                                                )
                                            "
                                            variant="veil"
                                            :value="card.value"
                                        />
                                        <template v-else>
                                            {{ card.value }}
                                        </template>
                                    </p>
                                </div>
                                <div
                                    class="flex size-10 items-center justify-center rounded-2xl"
                                    :class="card.tone"
                                >
                                    <component :is="card.icon" class="size-5" />
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <Alert
                v-if="flashSuccess"
                class="border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
            >
                <CalendarDays class="size-4" />
                <AlertTitle>{{
                    t('transactions.sheet.alerts.operationCompleted')
                }}</AlertTitle>
                <AlertDescription>
                    {{ flashSuccess }}
                </AlertDescription>
            </Alert>

            <Alert
                v-if="periodNotice"
                class="border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
            >
                <Calendar class="size-4" />
                <AlertTitle>{{
                    t('transactions.sheet.alerts.periodNotCurrent')
                }}</AlertTitle>
                <AlertDescription>
                    {{ periodNotice }}
                </AlertDescription>
            </Alert>

            <section
                class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/85"
            >
                <div
                    class="flex flex-col gap-4 border-b border-slate-200/70 p-5 lg:flex-row lg:items-start lg:justify-between lg:p-6 dark:border-white/10"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <h2
                                class="text-xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{
                                    t('transactions.recurring.monthlyCalendar')
                                }}
                            </h2>
                            <Badge
                                class="rounded-full bg-slate-900/6 text-slate-700 dark:bg-white/8 dark:text-slate-300"
                            >
                                {{ props.activePeriod.period_label }}
                            </Badge>
                        </div>
                        <p
                            class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{
                                isCalendarCollapsed
                                    ? t(
                                          'transactions.recurring.collapsedHelper',
                                      )
                                    : t(
                                          'transactions.recurring.monthlyCalendarDescription',
                                      )
                            }}
                        </p>
                    </div>

                    <Button
                        variant="outline"
                        class="h-11 rounded-2xl px-4"
                        :aria-expanded="!isCalendarCollapsed"
                        @click="isCalendarCollapsed = !isCalendarCollapsed"
                    >
                        <ChevronUp
                            v-if="!isCalendarCollapsed"
                            class="mr-2 size-4"
                        />
                        <ChevronDown v-else class="mr-2 size-4" />
                        {{
                            isCalendarCollapsed
                                ? t(
                                      'transactions.recurring.actions.expandCalendar',
                                  )
                                : t(
                                      'transactions.recurring.actions.collapseCalendar',
                                  )
                        }}
                    </Button>
                </div>

                <div v-if="!isCalendarCollapsed" class="space-y-4 p-4 lg:p-6">
                    <div class="space-y-2 lg:hidden">
                        <div
                            class="grid grid-cols-7 gap-1 text-center text-[10px] font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            <div
                                v-for="label in weekdayLabels"
                                :key="`${label}-mobile`"
                                class="rounded-xl bg-slate-100/80 px-1 py-2 dark:bg-white/6"
                            >
                                {{ label }}
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <div
                                v-for="(week, weekIndex) in calendarWeeks"
                                :key="`mobile-week-${weekIndex}`"
                                class="grid grid-cols-7 gap-1.5"
                            >
                                <button
                                    v-for="cell in week"
                                    :key="`mobile-${cell.date}`"
                                    type="button"
                                    class="min-h-[76px] rounded-[18px] border px-1.5 py-2 text-left transition-all"
                                    :class="
                                        cn(
                                            cell.isCurrentMonth
                                                ? 'border-slate-200/80 bg-white hover:border-sky-300 hover:bg-sky-50/70 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-sky-500/40 dark:hover:bg-sky-500/8'
                                                : 'border-dashed border-slate-200/40 bg-slate-100/35 text-slate-300 dark:border-white/6 dark:bg-slate-950/30 dark:text-slate-700',
                                            cell.summary &&
                                                selectedAnchor ===
                                                    cell.summary.anchor
                                                ? 'border-sky-400 ring-2 ring-sky-400/40'
                                                : '',
                                            cell.isToday &&
                                                cell.summary &&
                                                selectedAnchor !==
                                                    cell.summary.anchor
                                                ? 'shadow-[inset_0_0_0_1px_rgba(14,165,233,0.45)]'
                                                : '',
                                            !cell.summary ||
                                                !cell.isCurrentMonth ||
                                                filteredOccurrencesCount(
                                                    cell.summary,
                                                ) === 0
                                                ? 'cursor-default'
                                                : 'cursor-pointer',
                                        )
                                    "
                                    :disabled="
                                        !cell.summary ||
                                        !cell.isCurrentMonth ||
                                        filteredOccurrencesCount(
                                            cell.summary,
                                        ) === 0
                                    "
                                    @click="
                                        cell.summary
                                            ? jumpToDay(cell.summary)
                                            : undefined
                                    "
                                >
                                    <div
                                        class="flex items-start justify-between gap-1"
                                    >
                                        <span
                                            class="text-sm font-semibold"
                                            :class="
                                                cell.isCurrentMonth
                                                    ? selectedAnchor ===
                                                      cell.summary?.anchor
                                                        ? 'text-sky-700 dark:text-sky-300'
                                                        : 'text-slate-950 dark:text-white'
                                                    : 'text-slate-300 dark:text-slate-700'
                                            "
                                        >
                                            {{
                                                cell.isCurrentMonth
                                                    ? cell.dayNumber
                                                    : ''
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                cell.summary?.occurrences_count
                                            "
                                            class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            {{
                                                filteredOccurrencesCount(
                                                    cell.summary,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="
                                            cell.summary && cell.isCurrentMonth
                                        "
                                        class="mt-2 space-y-1"
                                    >
                                        <p
                                            v-if="cell.summary.income_total > 0"
                                            class="truncate text-[10px] leading-none font-semibold text-emerald-600 dark:text-emerald-300"
                                        >
                                            +<SensitiveValue
                                                :value="
                                                    formatMoney(
                                                        cell.summary
                                                            .income_total,
                                                    )
                                                "
                                            />
                                        </p>
                                        <p
                                            v-if="
                                                cell.summary.expense_total > 0
                                            "
                                            class="truncate text-[10px] leading-none font-semibold text-rose-600 dark:text-rose-300"
                                        >
                                            -<SensitiveValue
                                                :value="
                                                    formatMoney(
                                                        cell.summary
                                                            .expense_total,
                                                    )
                                                "
                                            />
                                        </p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="hidden overflow-x-auto pb-1 lg:block">
                        <div class="min-w-[44rem] space-y-2 sm:min-w-0">
                            <div
                                class="grid grid-cols-7 gap-2 text-center text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase sm:text-xs dark:text-slate-400"
                            >
                                <div
                                    v-for="label in weekdayLabels"
                                    :key="label"
                                    class="rounded-2xl bg-slate-100/80 px-2 py-3 dark:bg-white/6"
                                >
                                    {{ label }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <div
                                    v-for="(week, weekIndex) in calendarWeeks"
                                    :key="`week-${weekIndex}`"
                                    class="grid grid-cols-7 gap-2"
                                >
                                    <button
                                        v-for="cell in week"
                                        :key="cell.date"
                                        type="button"
                                        class="min-h-[110px] rounded-[22px] border p-2.5 text-left transition-all sm:min-h-[132px] sm:rounded-[24px] sm:p-3"
                                        :class="
                                            cn(
                                                cell.isCurrentMonth
                                                    ? 'border-slate-200/80 bg-slate-50/90 hover:border-sky-300 hover:bg-sky-50/70 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-sky-500/40 dark:hover:bg-sky-500/8'
                                                    : 'min-h-[92px] border-dashed border-slate-200/40 bg-slate-100/35 text-slate-300 dark:border-white/6 dark:bg-slate-950/30 dark:text-slate-700',
                                                cell.summary &&
                                                    selectedAnchor ===
                                                        cell.summary.anchor
                                                    ? 'ring-2 ring-sky-400/70 ring-offset-2 ring-offset-white dark:ring-offset-slate-950'
                                                    : '',
                                                cell.isToday
                                                    ? 'shadow-[inset_0_0_0_1px_rgba(14,165,233,0.4)]'
                                                    : '',
                                                !cell.summary ||
                                                    !cell.isCurrentMonth
                                                    ? 'cursor-default'
                                                    : 'cursor-pointer',
                                            )
                                        "
                                        :disabled="
                                            !cell.summary ||
                                            !cell.isCurrentMonth ||
                                            filteredOccurrencesCount(
                                                cell.summary,
                                            ) === 0
                                        "
                                        @click="
                                            cell.summary
                                                ? jumpToDay(cell.summary)
                                                : undefined
                                        "
                                    >
                                        <div
                                            class="flex items-start justify-between gap-2"
                                        >
                                            <span
                                                class="text-sm font-semibold sm:text-base"
                                                :class="
                                                    cell.isCurrentMonth
                                                        ? 'text-slate-950 dark:text-white'
                                                        : 'text-slate-300 dark:text-slate-700'
                                                "
                                            >
                                                {{
                                                    cell.isCurrentMonth
                                                        ? cell.dayNumber
                                                        : ''
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    cell.summary
                                                        ?.occurrences_count
                                                "
                                                class="rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-semibold text-slate-700 shadow-sm dark:bg-slate-950/80 dark:text-slate-200"
                                            >
                                                {{
                                                    filteredOccurrencesCount(
                                                        cell.summary,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="
                                                cell.summary &&
                                                cell.isCurrentMonth
                                            "
                                            class="mt-3 space-y-1.5 sm:mt-4 sm:space-y-2"
                                        >
                                            <div
                                                v-if="
                                                    cell.summary.income_total >
                                                    0
                                                "
                                                class="rounded-2xl bg-emerald-500/10 px-2 py-1.5 text-emerald-700 sm:px-2.5 sm:py-2 dark:bg-emerald-500/12 dark:text-emerald-300"
                                            >
                                                <span
                                                    class="block text-[10px] font-semibold tracking-[0.18em] uppercase sm:hidden"
                                                >
                                                    +
                                                </span>
                                                <span
                                                    class="hidden text-[10px] tracking-[0.18em] uppercase sm:block"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.labels.plannedIncome',
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="mt-0.5 block text-xs leading-tight font-semibold sm:mt-1 sm:text-sm"
                                                >
                                                    <SensitiveValue
                                                        :value="
                                                            formatMoney(
                                                                cell.summary
                                                                    .income_total,
                                                            )
                                                        "
                                                    />
                                                </span>
                                            </div>
                                            <div
                                                v-if="
                                                    cell.summary.expense_total >
                                                    0
                                                "
                                                class="rounded-2xl bg-rose-500/10 px-2 py-1.5 text-rose-700 sm:px-2.5 sm:py-2 dark:bg-rose-500/12 dark:text-rose-300"
                                            >
                                                <span
                                                    class="block text-[10px] font-semibold tracking-[0.18em] uppercase sm:hidden"
                                                >
                                                    -
                                                </span>
                                                <span
                                                    class="hidden text-[10px] tracking-[0.18em] uppercase sm:block"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.labels.plannedExpenses',
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="mt-0.5 block text-xs leading-tight font-semibold sm:mt-1 sm:text-sm"
                                                >
                                                    <SensitiveValue
                                                        :value="
                                                            formatMoney(
                                                                cell.summary
                                                                    .expense_total,
                                                            )
                                                        "
                                                    />
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/85"
            >
                <div
                    class="flex flex-col gap-4 border-b border-slate-200/70 p-5 lg:flex-row lg:items-end lg:justify-between lg:p-6 dark:border-white/10"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Filter
                                class="size-4 text-slate-500 dark:text-slate-400"
                            />
                            <h2
                                class="text-xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{ t('transactions.recurring.table.title') }}
                            </h2>
                        </div>
                        <p
                            class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{
                                isFiltersCollapsed
                                    ? t(
                                          'transactions.recurring.collapsedHelper',
                                      )
                                    : t(
                                          'transactions.recurring.table.description',
                                      )
                            }}
                        </p>
                    </div>

                    <Button
                        variant="outline"
                        class="h-11 rounded-2xl px-4 md:hidden"
                        :aria-expanded="!isFiltersCollapsed"
                        @click="isFiltersCollapsed = !isFiltersCollapsed"
                    >
                        <ChevronUp
                            v-if="!isFiltersCollapsed"
                            class="mr-2 size-4"
                        />
                        <ChevronDown v-else class="mr-2 size-4" />
                        {{
                            isFiltersCollapsed
                                ? t(
                                      'transactions.recurring.actions.expandFilters',
                                  )
                                : t(
                                      'transactions.recurring.actions.collapseFilters',
                                  )
                        }}
                    </Button>

                    <div
                        class="gap-3 md:grid md:grid-cols-2 xl:grid-cols-[repeat(7,minmax(0,1fr))]"
                        :class="
                            isFiltersCollapsed
                                ? 'hidden md:grid'
                                : 'grid grid-cols-2'
                        "
                    >
                        <div class="col-span-2 grid gap-2 md:col-span-1">
                            <Label>{{
                                t('transactions.recurring.filters.account')
                            }}</Label>
                            <Select
                                :model-value="accountFilter"
                                @update:model-value="
                                    handleAccountSelection(
                                        String($event ?? 'all'),
                                    )
                                "
                            >
                                <SelectTrigger
                                    class="h-10 rounded-2xl border-slate-200 text-sm md:h-11 dark:border-slate-800"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2 text-sm"
                                    >
                                        <span
                                            class="truncate text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                selectedAccountFilterOption?.label ??
                                                t(
                                                    'transactions.recurring.filters.allAccounts',
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="selectedAccountFilterOption"
                                            :class="
                                                cn(
                                                    'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                    selectedAccountFilterOption.badgeClass,
                                                )
                                            "
                                        >
                                            {{
                                                selectedAccountFilterOption.badgeLabel
                                            }}
                                        </span>
                                    </div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        t(
                                            'transactions.recurring.filters.allAccounts',
                                        )
                                    }}</SelectItem>
                                    <SelectGroup
                                        v-for="group in groupedAccountFilterOptions"
                                        :key="group.key"
                                    >
                                        <SelectLabel>
                                            {{ group.label }}
                                        </SelectLabel>
                                        <SelectItem
                                            v-for="account in group.options"
                                            :key="account.value"
                                            :value="account.value"
                                        >
                                            <div
                                                class="flex min-w-0 items-center gap-2"
                                            >
                                                <span class="truncate">{{
                                                    account.label
                                                }}</span>
                                                <span
                                                    :class="
                                                        cn(
                                                            'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                            account.badgeClass,
                                                        )
                                                    "
                                                >
                                                    {{ account.badgeLabel }}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{
                                t('transactions.recurring.filters.entryType')
                            }}</Label>
                            <Select v-model="entryTypeFilter">
                                <SelectTrigger
                                    class="h-10 rounded-2xl border-slate-200 text-sm md:h-11 dark:border-slate-800"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'transactions.recurring.filters.entryType',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        t(
                                            'transactions.recurring.filters.allEntryTypes',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="recurring">{{
                                        t(
                                            'transactions.recurring.enums.entryType.recurring',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="installment">{{
                                        t(
                                            'transactions.recurring.enums.entryType.installment',
                                        )
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{
                                t('transactions.recurring.filters.status')
                            }}</Label>
                            <Select v-model="statusFilter">
                                <SelectTrigger
                                    class="h-10 rounded-2xl border-slate-200 text-sm md:h-11 dark:border-slate-800"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'transactions.recurring.filters.status',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        t(
                                            'transactions.recurring.filters.allStatuses',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="active">{{
                                        t(
                                            'transactions.recurring.filters.activeStatus',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="cancelled">{{
                                        t(
                                            'transactions.recurring.filters.cancelledStatus',
                                        )
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{
                                t('transactions.recurring.filters.direction')
                            }}</Label>
                            <Select v-model="directionFilter">
                                <SelectTrigger
                                    class="h-10 rounded-2xl border-slate-200 text-sm md:h-11 dark:border-slate-800"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'transactions.recurring.filters.direction',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        t(
                                            'transactions.recurring.filters.allDirections',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="income">{{
                                        t(
                                            'transactions.recurring.filters.incomes',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="expense">{{
                                        t(
                                            'transactions.recurring.filters.expenses',
                                        )
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{
                                t('transactions.recurring.filters.conversion')
                            }}</Label>
                            <Select v-model="conversionFilter">
                                <SelectTrigger
                                    class="h-10 rounded-2xl border-slate-200 text-sm md:h-11 dark:border-slate-800"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'transactions.recurring.filters.conversion',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        t(
                                            'transactions.recurring.filters.allConversions',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="converted">{{
                                        t(
                                            'transactions.recurring.filters.converted',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="unconverted">{{
                                        t(
                                            'transactions.recurring.filters.unconverted',
                                        )
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{
                                t('transactions.recurring.filters.refund')
                            }}</Label>
                            <Select v-model="refundFilter">
                                <SelectTrigger
                                    class="h-10 rounded-2xl border-slate-200 text-sm md:h-11 dark:border-slate-800"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'transactions.recurring.filters.refund',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        t(
                                            'transactions.recurring.filters.allRefunds',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="refunded">{{
                                        t(
                                            'transactions.recurring.filters.refunded',
                                        )
                                    }}</SelectItem>
                                    <SelectItem value="not_refunded">{{
                                        t(
                                            'transactions.recurring.filters.notRefunded',
                                        )
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label class="opacity-0 md:opacity-0">{{
                                t('transactions.recurring.actions.resetFilters')
                            }}</Label>
                            <Button
                                variant="outline"
                                class="h-10 rounded-2xl px-4 md:h-11"
                                @click="resetFilters"
                            >
                                <RotateCcw class="mr-2 size-4" />
                                {{
                                    t(
                                        'transactions.recurring.actions.resetFilters',
                                    )
                                }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="flatFilteredOccurrences.length === 0"
                    class="px-6 py-14 text-center text-sm text-slate-600 dark:text-slate-300"
                >
                    {{ t('transactions.recurring.table.empty') }}
                </div>

                <div v-else class="space-y-6 p-4 lg:p-6">
                    <RecurringOccurrencesMobileList
                        :days="filteredGroups"
                        :base-currency="baseCurrency"
                        :format-locale="auth.user?.format_locale ?? null"
                        :highlighted-entry-uuid="highlightedEntryUuid"
                        @convert="convertRow"
                        @refund="openRefundDialog"
                        @edit="openEditForm"
                    />

                    <section
                        v-for="day in filteredGroups"
                        :id="day.anchor"
                        :key="day.date"
                        class="hidden scroll-mt-28 overflow-hidden rounded-[24px] border border-slate-200/80 lg:block dark:border-white/10"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 bg-slate-50/85 px-4 py-3 dark:border-white/10 dark:bg-slate-900/60"
                        >
                            <div>
                                <h3
                                    class="text-sm font-semibold tracking-[0.12em] text-slate-700 uppercase dark:text-slate-200"
                                >
                                    {{ formatDayHeading(day.date) }}
                                </h3>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    class="rounded-full bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.labels.plannedIncome',
                                        )
                                    }}:
                                    <SensitiveValue
                                        :value="formatMoney(day.income_total)"
                                    />
                                </Badge>
                                <Badge
                                    class="rounded-full bg-rose-500/10 text-rose-700 dark:bg-rose-500/12 dark:text-rose-300"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.labels.plannedExpenses',
                                        )
                                    }}:
                                    <SensitiveValue
                                        :value="formatMoney(day.expense_total)"
                                    />
                                </Badge>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead
                                    class="bg-slate-950/[0.03] text-left text-xs tracking-[0.16em] text-slate-500 uppercase dark:bg-white/[0.04] dark:text-slate-400"
                                >
                                    <tr>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.date',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.title',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.account',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.category',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.trackedItem',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.entryType',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.direction',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.amount',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.occurrenceStatus',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.convertedTransaction',
                                                )
                                            }}
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.actions',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="occurrence in day.occurrences.filter(
                                            filterOccurrence,
                                        )"
                                        :key="occurrence.uuid"
                                        :class="
                                            cn(
                                                'border-t border-slate-200/70 align-top dark:border-white/10',
                                                rowToneClass(occurrence),
                                                rowAccentClass(occurrence),
                                                occurrence.recurring_entry
                                                    ?.uuid ===
                                                    highlightedEntryUuid
                                                    ? 'bg-sky-50/80 dark:bg-sky-500/8'
                                                    : '',
                                            )
                                        "
                                        :data-recurring-entry-row="
                                            occurrence.recurring_entry?.uuid ??
                                            occurrence.uuid
                                        "
                                    >
                                        <td
                                            class="px-4 py-4 text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                occurrence.due_date ??
                                                occurrence.expected_date
                                            }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="space-y-1">
                                                <Link
                                                    v-if="
                                                        occurrence.recurring_entry
                                                    "
                                                    :href="
                                                        showRecurringEntry(
                                                            occurrence
                                                                .recurring_entry
                                                                .uuid,
                                                        )
                                                    "
                                                    class="font-semibold text-slate-950 underline-offset-4 hover:underline dark:text-white"
                                                >
                                                    {{
                                                        occurrence.title ??
                                                        occurrence
                                                            .recurring_entry
                                                            .title
                                                    }}
                                                </Link>
                                                <p
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        occurrence.description ??
                                                        t(
                                                            'transactions.recurring.labels.noDescription',
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </td>
                                        <td
                                            class="px-4 py-4 text-slate-700 dark:text-slate-200"
                                        >
                                            {{
                                                occurrence.recurring_entry
                                                    ?.account?.name ??
                                                t(
                                                    'transactions.recurring.labels.noAccount',
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-4 text-slate-700 dark:text-slate-200"
                                        >
                                            {{
                                                occurrence.recurring_entry
                                                    ?.category?.name ??
                                                t(
                                                    'transactions.recurring.labels.noCategory',
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-4 text-slate-700 dark:text-slate-200"
                                        >
                                            {{
                                                occurrence.recurring_entry
                                                    ?.tracked_item?.name ??
                                                t(
                                                    'transactions.recurring.labels.noTrackedItem',
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <Badge
                                                    class="rounded-full bg-white/85 text-slate-700 dark:bg-slate-950/80 dark:text-slate-300"
                                                >
                                                    {{
                                                        entryTypeLabel(
                                                            occurrence.entry_type,
                                                        )
                                                    }}
                                                </Badge>
                                                <Badge
                                                    v-if="
                                                        occurrence
                                                            .recurring_entry
                                                            ?.status ===
                                                        'cancelled'
                                                    "
                                                    class="rounded-full bg-slate-900/8 text-slate-700 dark:bg-white/10 dark:text-slate-200"
                                                >
                                                    {{
                                                        planStatusLabel(
                                                            'cancelled',
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <Badge
                                                class="rounded-full"
                                                :class="
                                                    occurrence.direction ===
                                                    'income'
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300'
                                                        : 'bg-rose-500/10 text-rose-700 dark:bg-rose-500/12 dark:text-rose-300'
                                                "
                                            >
                                                {{
                                                    directionLabel(
                                                        occurrence.direction,
                                                    )
                                                }}
                                            </Badge>
                                        </td>
                                        <td
                                            class="px-4 py-4 text-right font-semibold"
                                            :class="
                                                occurrence.direction ===
                                                'income'
                                                    ? 'text-emerald-700 dark:text-emerald-300'
                                                    : 'text-rose-700 dark:text-rose-300'
                                            "
                                        >
                                            <SensitiveValue
                                                :value="
                                                    formatMoney(
                                                        occurrence.expected_amount ??
                                                            0,
                                                        occurrence.currency,
                                                    )
                                                "
                                            />
                                        </td>
                                        <td class="px-4 py-4">
                                            <Badge
                                                class="rounded-full"
                                                :class="
                                                    recurrenceState(occurrence)
                                                        .tone
                                                "
                                            >
                                                {{
                                                    recurrenceState(occurrence)
                                                        .label
                                                }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div
                                                v-if="
                                                    occurrence.converted_transaction
                                                "
                                                class="space-y-2"
                                            >
                                                <div
                                                    class="space-y-1 text-xs text-slate-600 dark:text-slate-300"
                                                >
                                                    <p>
                                                        {{
                                                            occurrence
                                                                .converted_transaction
                                                                .transaction_date
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            occurrence
                                                                .converted_transaction
                                                                .refund_transaction
                                                                ?.transaction_date
                                                        "
                                                        class="text-amber-700 dark:text-amber-300"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.recurring.table.refundReference',
                                                                {
                                                                    date: occurrence
                                                                        .converted_transaction
                                                                        .refund_transaction
                                                                        .transaction_date,
                                                                },
                                                            )
                                                        }}
                                                    </p>
                                                    <Link
                                                        v-if="
                                                            occurrence
                                                                .converted_transaction
                                                                .show_url
                                                        "
                                                        :href="
                                                            occurrence
                                                                .converted_transaction
                                                                .show_url
                                                        "
                                                        class="inline-flex items-center text-xs font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.recurring.actions.openTransaction',
                                                            )
                                                        }}
                                                    </Link>
                                                </div>
                                            </div>
                                            <span
                                                v-else
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.recurring.table.noLinkedTransaction',
                                                    )
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div
                                                class="flex flex-wrap justify-end gap-2"
                                            >
                                                <Button
                                                    v-if="
                                                        occurrence.can_convert
                                                    "
                                                    variant="outline"
                                                    class="h-9 rounded-full px-3 text-xs"
                                                    @click="
                                                        convertRow(occurrence)
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.actions.convert',
                                                        )
                                                    }}
                                                </Button>
                                                <Button
                                                    v-if="
                                                        occurrence
                                                            .converted_transaction
                                                            ?.can_refund
                                                    "
                                                    variant="outline"
                                                    class="h-9 rounded-full px-3 text-xs"
                                                    @click="
                                                        openRefundDialog(
                                                            occurrence,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.actions.refund',
                                                        )
                                                    }}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    class="h-9 rounded-full px-3 text-xs"
                                                    @click="
                                                        openEditForm(
                                                            occurrence
                                                                .recurring_entry
                                                                ?.uuid ?? '',
                                                        )
                                                    "
                                                >
                                                    <Pencil
                                                        class="mr-2 size-3.5"
                                                    />
                                                    {{
                                                        t(
                                                            'transactions.recurring.actions.edit',
                                                        )
                                                    }}
                                                </Button>
                                                <Link
                                                    v-if="
                                                        linkedEntry(occurrence)
                                                    "
                                                    :href="
                                                        showRecurringEntry(
                                                            occurrence
                                                                .recurring_entry
                                                                ?.uuid ?? '',
                                                        )
                                                    "
                                                    class="inline-flex h-9 items-center rounded-full px-3 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.actions.openPlan',
                                                        )
                                                    }}
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </div>

        <RecurringEntryFormSheet
            v-model:open="formOpen"
            :entry="selectedEntry"
            :form-options="props.formOptions"
            :date-options="props.dateOptions"
            :default-start-date="smartDefaultStartDate"
            :show-start-month-selector="true"
            :return-to-index="true"
            @saved="formOpen = false"
        />

        <Dialog
            :open="refundDialogOccurrence !== null"
            @update:open="
                (value) => {
                    if (!value) refundDialogOccurrence = null;
                }
            "
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('transactions.recurring.dialogs.refundTitle')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'transactions.recurring.dialogs.refundDescription',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        @click="refundDialogOccurrence = null"
                    >
                        {{ t('app.common.cancel') }}
                    </Button>
                    <Button class="rounded-xl" @click="refundOccurrence">
                        {{ t('transactions.recurring.actions.refund') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
