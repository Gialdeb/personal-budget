<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    ChevronDown,
    ChevronUp,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch, nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { index as recurringIndex } from '@/actions/App/Http/Controllers/RecurringEntryController';
import {
    end,
    show,
    updateOccurrence,
    explain,
} from '@/actions/App/Http/Controllers/SalaryEmploymentController';
import InputError from '@/components/InputError.vue';
import type { RecurringCalendarCell } from '@/components/recurring/RecurringCalendarGrid.vue';
import RecurringCalendarGrid from '@/components/recurring/RecurringCalendarGrid.vue';
import type { SalaryPaymentConfig } from '@/components/recurring/SalaryEmploymentEditDialog.vue';
import SalaryEmploymentEditDialog from '@/components/recurring/SalaryEmploymentEditDialog.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@/composables/useNotifiedForm';
import { useUiCopy } from '@/i18n/ui-copy';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency } from '@/lib/currency';
import type {
    RecurringEntryFormOptions,
    RecurringMonthlyCalendarDay,
    TransactionsNavigation,
} from '@/types';
type Component = {
    label: string;
    amount: number | string;
    type: string | null;
};
type Detail = {
    id: number;
    label: string;
    kind: string;
    pay_period_start: string;
    pay_period_end: string;
    due_date: string;
    base_expected_amount: number;
    currency: string;
    components: Component[];
    total: number;
    expected_date: string;
    difference: number | null;
    can_explain: boolean;
    actual: number | null;
    status: string;
    can_edit: boolean;
    amount_confirmed: boolean;
    recurring_url: string;
};
type Period = {
    currencies: string[];
    base: number | null;
    additional: number | null;
    components: number | null;
    expected: number | null;
    actual: number | null;
    difference: number | null;
};
const ui = useUiCopy();
const props = defineProps<{
    employment: {
        uuid: string;
        name: string;
        employer: string | null;
        start_date: string;
        end_date: string | null;
        annual_gross_salary: string | null;
        annual_gross_salary_currency: string | null;
        can_end: boolean;
        can_edit: boolean;
        schedules: SalaryPaymentConfig[];
    };
    revisionOptions: RecurringEntryFormOptions;
    selectedDay: string | null;
    calendarDays: RecurringMonthlyCalendarDay[];
    yearSummary: {
        year: number;
        currency: string | null;
        received: number | null;
        average: number | null;
        base: number | null;
        components: number | null;
    };
    details: {
        data: Detail[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    comparison: {
        current: Period | null;
        previous: Period | null;
        period: string;
        previous_period: string;
        base_percentage: number | null;
    };
}>();
const { t, locale } = useI18n();
const page = usePage();
const calendarStorageKey = 'salary-detail-calendar-collapsed';
const isCalendarCollapsed = ref(false);
const selectedYear = computed(() => props.comparison.period.split('-')[0]);
const selectedMonth = computed(() =>
    String(Number(props.comparison.period.split('-')[1])),
);
const availableYears = computed(() => {
    const navigation = page.props
        .transactionsNavigation as TransactionsNavigation | null;

    return [
        ...new Set([
            ...(navigation?.context.available_years ?? []),
            Number(props.employment.start_date.slice(0, 4)),
            Number(selectedYear.value),
            new Date().getFullYear(),
            new Date().getFullYear() + 1,
            ...props.employment.schedules.map((schedule) =>
                Number(schedule.start_date.slice(0, 4)),
            ),
        ]),
    ].sort((a, b) => a - b);
});
onMounted(() => {
    isCalendarCollapsed.value =
        window.localStorage.getItem(calendarStorageKey) === 'true';

    if (
        new URLSearchParams(window.location.search).get('edit') === '1' &&
        props.employment.can_edit
    ) {
        editOpen.value = true;
    }
});
watch(isCalendarCollapsed, (value) => {
    window.localStorage.setItem(calendarStorageKey, String(value));
});
function monthLabel(month: number): string {
    return new Intl.DateTimeFormat(locale.value, {
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(Number(selectedYear.value), month - 1, 1)));
}
const editOpen = ref(false);
const selectedDate = ref<string | null>(props.selectedDay);
const historyElement = ref<HTMLElement | null>(null);
const explainDetail = ref<Detail | null>(null);
const explanation = useForm({ label: '', type: '' });
const ordinary = computed(() =>
    props.employment.schedules.filter((row) => row.kind === 'ordinary'),
);
const visibleDetails = computed(() =>
    props.details.data.filter(
        (row) =>
            row.status !== 'cancelled' &&
            (!selectedDate.value || row.expected_date === selectedDate.value),
    ),
);
watch(
    () => props.selectedDay,
    (day) => {
        selectedDate.value = day;
    },
);
watch(
    () => props.comparison.period,
    () => {
        selectedDate.value = null;
    },
);
const weekdayLabels = computed(() =>
    Array.from({ length: 7 }, (_, index) =>
        new Intl.DateTimeFormat(locale.value, {
            weekday: 'short',
            timeZone: 'UTC',
        }).format(new Date(Date.UTC(2026, 0, 5 + index))),
    ),
);
const calendarWeeks = computed<RecurringCalendarCell[][]>(() => {
    const [year, month] = props.comparison.period.split('-').map(Number);
    const first = new Date(Date.UTC(year, month - 1, 1));
    first.setUTCDate(first.getUTCDate() - ((first.getUTCDay() + 6) % 7));
    const dayMap = new Map(props.calendarDays.map((day) => [day.date, day]));
    const cells = Array.from({ length: 42 }, (_, index) => {
        const date = new Date(first);
        date.setUTCDate(first.getUTCDate() + index);
        const iso = date.toISOString().slice(0, 10);

        return {
            date: iso,
            dayNumber: date.getUTCDate(),
            isCurrentMonth: date.getUTCMonth() === month - 1,
            isToday: iso === today,
            summary: dayMap.get(iso) ?? null,
        };
    });

    return Array.from({ length: 6 }, (_, index) =>
        cells.slice(index * 7, index * 7 + 7),
    );
});
function dayHeading(date: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(date + 'T00:00:00Z'));
}
function selectDay(date: string | null): void {
    const next = selectedDate.value === date ? null : date;
    router.get(
        show.url(props.employment.uuid),
        { period: props.comparison.period, ...(next ? { day: next } : {}) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: async () => {
                selectedDate.value = next;

                if (next) {
                    await nextTick();
                    historyElement.value?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                }
            },
        },
    );
}
function openExplanation(detail: Detail): void {
    explanation.clearErrors();
    explanation.label = t('salary.adjustment');
    explanation.type = '';
    explainDetail.value = detail;
}
function saveExplanation(): void {
    if (!explainDetail.value) {
        return;
    }

    explanation.post(
        explain.url({
            salaryEmployment: props.employment.uuid,
            detail: explainDetail.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                explainDetail.value = null;
            },
        },
    );
}
const selected = ref<Detail | null>(null);
function updatePaymentDialog(open: boolean): void {
    if (!open && !form.processing) {
        selected.value = null;
    }
}
const endOpen = ref(false);
const form = useForm({
    base_expected_amount: '',
    pay_period_start: '',
    pay_period_end: '',
    components: [] as Component[],
});
const endForm = useForm({
    end_date:
        props.employment.end_date ?? new Date().toLocaleDateString('en-CA'),
});
const total = computed(
    () =>
        (Math.round(Number(form.base_expected_amount) * 100) +
            form.components.reduce(
                (sum, row) => sum + Math.round(Number(row.amount) * 100),
                0,
            )) /
        100,
);
const today = new Date().toLocaleDateString('en-CA');
const employmentState = computed(() =>
    !props.employment.end_date
        ? 'active'
        : props.employment.end_date < today
          ? 'ended'
          : 'scheduledEnd',
);
function edit(detail: Detail): void {
    form.defaults({
        base_expected_amount: String(detail.base_expected_amount),
        pay_period_start: detail.pay_period_start,
        pay_period_end: detail.pay_period_end,
        components: detail.components.map((row) => ({ ...row })),
    });
    form.reset();
    form.clearErrors();
    selected.value = detail;
}
function save(): void {
    if (!selected.value) {
        return;
    }

    form.patch(
        updateOccurrence.url({
            salaryEmployment: props.employment.uuid,
            detail: selected.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = null;
            },
        },
    );
}
function terminate(): void {
    endForm.patch(end.url(props.employment.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            endOpen.value = false;
        },
    });
}
function money(amount: number | null, currency: string): string {
    return amount === null ? '—' : formatCurrency(amount, currency);
}
function changePeriod(year: string, month: string): void {
    if (year && month) {
        router.get(
            show.url(props.employment.uuid),
            { period: `${year}-${month.padStart(2, '0')}` },
            { preserveScroll: true, preserveState: true },
        );
    }
}
</script>
<template>
    <Head :title="`${employment.name} · ${t('salary.salary')}`" />
    <AppLayout
        :breadcrumbs="[
            {
                title: t('transactions.recurring.title'),
                href: recurringIndex.url(),
            },
            { title: employment.name, href: show.url(employment.uuid) },
        ]"
    >
        <div class="mx-auto w-full max-w-6xl space-y-6 p-4 pb-24 sm:p-6">
            <header class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 space-y-2">
                        <p
                            class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-300"
                        >
                            <BriefcaseBusiness class="size-5" />{{
                                t('salary.details')
                            }}
                        </p>
                        <h1 class="text-2xl font-semibold break-words">
                            {{ employment.name }}
                        </h1>
                        <p>{{ employment.employer ?? '—' }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ t(`salary.${employmentState}`) }} ·
                            {{ t('salary.start') }} {{ employment.start_date
                            }}<template v-if="employment.end_date">
                                · {{ t('salary.endDate') }}
                                {{ employment.end_date }}</template
                            >
                        </p>
                    </div>
                    <Button
                        v-if="employment.can_edit"
                        variant="outline"
                        @click="editOpen = true"
                        >{{ t('salary.editEmployment') }}</Button
                    >
                    <Button
                        v-if="employment.can_end"
                        variant="outline"
                        @click="endOpen = true"
                        >{{ t('salary.end') }}</Button
                    >
                </div>
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="payment in ordinary"
                        :key="payment.id"
                        class="rounded-2xl bg-muted/40 px-4 py-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ t('salary.ordinary') }} ·
                            {{ t(`salary.${payment.recurrence_type}`) }}
                        </p>
                        <SensitiveValue
                            :value="money(payment.amount, payment.currency)"
                        />
                        <p
                            v-if="payment.valid_from"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('salary.effectiveFrom') }}
                            {{ payment.valid_from }}
                        </p>
                    </div>
                </div>
                <p v-if="employment.annual_gross_salary" class="text-sm">
                    {{ t('salary.gross') }}:
                    <SensitiveValue
                        :value="
                            money(
                                Number(employment.annual_gross_salary),
                                employment.annual_gross_salary_currency ??
                                    'EUR',
                            )
                        "
                    />
                    · {{ t('salary.grossHint') }}
                </p>
            </header>
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="metric in [
                        'received',
                        'average',
                        'base',
                        'components',
                    ] as const"
                    :key="metric"
                    class="rounded-2xl border bg-card p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t(`salary.year.${metric}`) }} ·
                        {{ yearSummary.year }}
                    </p>
                    <p class="mt-2 text-xl font-semibold">
                        <SensitiveValue
                            :value="
                                money(
                                    yearSummary[metric],
                                    yearSummary.currency ?? 'EUR',
                                )
                            "
                        />
                    </p>
                    <p
                        v-if="metric === 'average'"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ t('salary.averageHint') }}
                    </p>
                </article>
            </section>
            <section
                class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/85"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b p-5"
                >
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{ t('transactions.recurring.monthlyCalendar') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t('salary.monthOnly') }}
                        </p>
                    </div>
                    <div
                        class="flex w-full flex-wrap items-end gap-3 lg:w-auto"
                    >
                        <div class="space-y-2">
                            <label
                                for="salary-year"
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >{{
                                    t('transactions.sheet.filters.year')
                                }}</label
                            >
                            <Select
                                :model-value="selectedYear"
                                @update:model-value="
                                    changePeriod(
                                        String($event ?? ''),
                                        selectedMonth,
                                    )
                                "
                            >
                                <SelectTrigger
                                    id="salary-year"
                                    class="h-11 w-[168px] rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-white/5"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent
                                    ><SelectItem
                                        v-for="year in availableYears"
                                        :key="year"
                                        :value="String(year)"
                                        >{{ year }}</SelectItem
                                    ></SelectContent
                                >
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <label
                                for="salary-month"
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                >{{
                                    t('transactions.sheet.filters.month')
                                }}</label
                            >
                            <Select
                                :model-value="selectedMonth"
                                @update:model-value="
                                    changePeriod(
                                        selectedYear,
                                        String($event ?? ''),
                                    )
                                "
                            >
                                <SelectTrigger
                                    id="salary-month"
                                    class="h-11 w-[168px] rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-white/5"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent
                                    ><SelectItem
                                        v-for="month in 12"
                                        :key="month"
                                        :value="String(month)"
                                        >{{ monthLabel(month) }}</SelectItem
                                    ></SelectContent
                                >
                            </Select>
                        </div>
                        <Button
                            variant="outline"
                            class="h-11 rounded-2xl px-4"
                            :aria-expanded="!isCalendarCollapsed"
                            aria-controls="salary-calendar"
                            @click="isCalendarCollapsed = !isCalendarCollapsed"
                        >
                            <ChevronUp
                                v-if="!isCalendarCollapsed"
                                class="mr-2 size-4"
                            /><ChevronDown v-else class="mr-2 size-4" />
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
                </div>
                <RecurringCalendarGrid
                    v-if="!isCalendarCollapsed"
                    id="salary-calendar"
                    :calendar-weeks="calendarWeeks"
                    :weekday-labels="weekdayLabels"
                    :selected-date="selectedDate"
                    :format-money="
                        (value) =>
                            money(
                                value,
                                comparison.current?.currencies[0] ?? 'EUR',
                            )
                    "
                    :format-day-heading="dayHeading"
                    :filtered-occurrences-count="(day) => day.occurrences_count"
                    @select="selectDay"
                />
            </section>
            <section class="space-y-4 rounded-2xl border bg-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold">{{ t('salary.comparison') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="p-2"></th>
                                <th class="p-2">
                                    {{ comparison.previous_period }}
                                </th>
                                <th class="p-2">{{ comparison.period }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="metric in [
                                    'base',
                                    'additional',
                                    'components',
                                    'expected',
                                    'actual',
                                    'difference',
                                ] as const"
                                :key="metric"
                                class="border-b last:border-0"
                            >
                                <th class="p-2 font-medium">
                                    {{
                                        t(
                                            `salary.${metric === 'expected' ? 'total' : metric}`,
                                        )
                                    }}
                                </th>
                                <td
                                    v-for="(period, index) in [
                                        comparison.previous,
                                        comparison.current,
                                    ]"
                                    :key="index"
                                    class="p-2 whitespace-nowrap"
                                >
                                    <SensitiveValue
                                        :value="
                                            period
                                                ? money(
                                                      period[metric],
                                                      period.currencies[0] ??
                                                          'EUR',
                                                  )
                                                : '—'
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('salary.baseChange') }}:
                    {{
                        comparison.base_percentage === null
                            ? t('salary.unavailable')
                            : `${comparison.base_percentage > 0 ? '+' : ''}${comparison.base_percentage}%`
                    }}
                </p>
            </section>
            <section ref="historyElement" class="scroll-mt-24 space-y-4">
                <h2 class="text-lg font-semibold">
                    {{ t('salary.history') }} · {{ comparison.period }}
                </h2>
                <div
                    v-if="selectedDate"
                    class="flex flex-wrap items-center gap-3 text-sm"
                >
                    <span>{{ dayHeading(selectedDate) }}</span
                    ><Button
                        variant="outline"
                        size="sm"
                        @click="selectDay(null)"
                        >{{
                            t('transactions.recurring.actions.showAllDays')
                        }}</Button
                    >
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('salary.historyLocked') }}
                </p>
                <p v-if="!visibleDetails.length">{{ t('salary.empty') }}</p>
                <article
                    v-for="detail in visibleDetails"
                    :key="detail.id"
                    class="space-y-4 rounded-2xl border bg-card p-4"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h3 class="font-medium">{{ detail.label }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ detail.pay_period_start }} →
                                {{ detail.pay_period_end }} ·
                                {{
                                    t(
                                        `salary.statuses.${detail.actual !== null && detail.actual >= detail.total ? 'completed' : detail.status}`,
                                    )
                                }}
                            </p>
                            <p class="text-sm">
                                {{ t('salary.date') }}: {{ detail.due_date }}
                            </p>
                        </div>
                        <Button
                            v-if="detail.can_edit"
                            variant="outline"
                            @click="edit(detail)"
                            >{{ t('salary.edit') }}</Button
                        >
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-muted-foreground">
                                {{ t('salary.base') }}
                            </dt>
                            <dd>
                                <SensitiveValue
                                    :value="
                                        money(
                                            detail.base_expected_amount,
                                            detail.currency,
                                        )
                                    "
                                />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                {{ t('salary.total') }}
                            </dt>
                            <dd>
                                <SensitiveValue
                                    :value="
                                        money(detail.total, detail.currency)
                                    "
                                />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                {{ t('salary.actual') }}
                            </dt>
                            <dd>
                                <SensitiveValue
                                    :value="
                                        money(detail.actual, detail.currency)
                                    "
                                />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">
                                {{ t('salary.difference') }}
                            </dt>
                            <dd
                                class="font-semibold"
                                :class="
                                    detail.difference !== null &&
                                    detail.difference !== 0
                                        ? 'text-amber-700 dark:text-amber-300'
                                        : ''
                                "
                            >
                                <SensitiveValue
                                    :value="
                                        money(
                                            detail.difference,
                                            detail.currency,
                                        )
                                    "
                                />
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-if="detail.actual !== null"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl p-3 text-sm"
                        :class="
                            detail.difference === 0
                                ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                                : 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                        "
                    >
                        <span>{{
                            t(
                                detail.difference === 0
                                    ? 'salary.reconciled'
                                    : 'salary.unattributed',
                            )
                        }}</span
                        ><Button
                            v-if="detail.can_explain"
                            variant="outline"
                            size="sm"
                            @click="openExplanation(detail)"
                            >{{ t('salary.explain') }}</Button
                        >
                    </div>
                    <ul
                        v-if="detail.components.length"
                        class="space-y-1 text-sm"
                    >
                        <li
                            v-for="(component, index) in detail.components"
                            :key="index"
                            class="flex justify-between gap-4"
                        >
                            <span class="break-words"
                                >{{ component.label
                                }}<span
                                    v-if="component.type"
                                    class="text-muted-foreground"
                                >
                                    · {{ component.type }}</span
                                ></span
                            ><SensitiveValue
                                :value="
                                    money(
                                        Number(component.amount),
                                        detail.currency,
                                    )
                                "
                            />
                        </li>
                    </ul>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span v-if="detail.actual === null">{{
                            detail.amount_confirmed
                                ? t('salary.confirmed')
                                : t('salary.total')
                        }}</span
                        ><Link
                            :href="detail.recurring_url"
                            class="font-medium text-emerald-700 underline dark:text-emerald-300"
                            >{{ t('salary.view') }}</Link
                        >
                    </div>
                </article>
                <nav
                    v-if="details.last_page > 1"
                    class="flex items-center justify-between gap-3"
                    :aria-label="ui('Paginazione')"
                >
                    <Link
                        v-if="details.prev_page_url"
                        :href="details.prev_page_url"
                        preserve-scroll
                        >{{ t('salary.previousPage') }}</Link
                    ><span
                        >{{ t('salary.page') }} {{ details.current_page }} /
                        {{ details.last_page }}</span
                    ><Link
                        v-if="details.next_page_url"
                        :href="details.next_page_url"
                        preserve-scroll
                        >{{ t('salary.nextPage') }}</Link
                    >
                </nav>
            </section>
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">
                    {{ t('salary.configuration') }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="schedule in employment.schedules"
                        :key="schedule.url"
                        :href="schedule.url"
                        class="space-y-2 rounded-2xl border bg-card p-4 hover:border-emerald-400"
                        ><p
                            v-if="schedule.valid_from"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('salary.effectiveFrom') }}
                            {{ schedule.valid_from }}
                        </p>
                        <p class="font-medium break-words">
                            {{ schedule.label }} ·
                            {{ t(`salary.${schedule.kind}`) }}
                        </p>
                        <SensitiveValue
                            :value="money(schedule.amount, schedule.currency)"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ schedule.account }} · {{ schedule.category }} ·
                            {{ t(`salary.${schedule.recurrence_type}`) }} ·
                            {{
                                t(
                                    schedule.automatic
                                        ? 'transactions.recurring.labels.autoPosting'
                                        : 'transactions.recurring.labels.manualPosting',
                                )
                            }}
                            ·
                            {{
                                schedule.recurrence_type === 'yearly'
                                    ? dayHeading(schedule.start_date)
                                    : t('salary.start') +
                                      ' ' +
                                      schedule.start_date
                            }}
                            · {{ t('salary.view') }}
                        </p></Link
                    >
                </div>
            </section>
        </div>
        <Dialog :open="selected !== null" @update:open="updatePaymentDialog"
            ><DialogContent
                class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl"
                ><DialogHeader
                    ><DialogTitle
                        >{{ t('salary.edit') }} ·
                        {{ selected?.label }}</DialogTitle
                    ><DialogDescription>{{
                        t('salary.variableHint')
                    }}</DialogDescription></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="save">
                    <label class="grid gap-1 text-sm"
                        >{{ t('salary.base')
                        }}<Input
                            v-model="form.base_expected_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                    /></label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.periodStart')
                            }}<Input
                                v-model="form.pay_period_start"
                                type="date"
                                required /></label
                        ><label class="grid gap-1 text-sm"
                            >{{ t('salary.periodEnd')
                            }}<Input
                                v-model="form.pay_period_end"
                                type="date"
                                required
                        /></label>
                    </div>
                    <div
                        v-for="(component, index) in form.components"
                        :key="index"
                        class="grid gap-2 rounded-xl border p-3"
                    >
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.label')
                            }}<Input
                                v-model="component.label"
                                required
                                maxlength="150"
                        /></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="grid gap-1 text-sm"
                                >{{ t('salary.amount')
                                }}<Input
                                    v-model="component.amount"
                                    type="number"
                                    step="0.01"
                                    required /></label
                            ><label class="grid gap-1 text-sm"
                                >{{ t('salary.type')
                                }}<Input
                                    :model-value="component.type ?? ''"
                                    @update:model-value="
                                        component.type = String($event)
                                    "
                                    maxlength="50"
                            /></label>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="form.components.splice(index, 1)"
                            ><Trash2 class="mr-2 size-4" />{{
                                t('salary.remove')
                            }}</Button
                        >
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        @click="
                            form.components.push({
                                label: '',
                                amount: '',
                                type: null,
                            })
                        "
                        ><Plus class="mr-2 size-4" />{{
                            t('salary.addComponent')
                        }}</Button
                    >
                    <p class="font-semibold">
                        {{ t('salary.total') }}:
                        <SensitiveValue
                            :value="money(total, selected?.currency ?? 'EUR')"
                        />
                    </p>
                    <InputError
                        v-for="(error, key) in form.errors"
                        :key="key"
                        :message="error"
                    /><DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="selected = null"
                            >{{ t('salary.cancel') }}</Button
                        ><Button :disabled="form.processing || total <= 0">{{
                            t('salary.save')
                        }}</Button></DialogFooter
                    >
                </form></DialogContent
            ></Dialog
        >
        <Dialog v-model:open="endOpen"
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{ t('salary.end') }}</DialogTitle
                    ><DialogDescription>{{
                        t('salary.endHint')
                    }}</DialogDescription></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="terminate">
                    <label class="grid gap-2 text-sm"
                        >{{ t('salary.endDate')
                        }}<Input
                            v-model="endForm.end_date"
                            type="date"
                            :min="today"
                            required /></label
                    ><InputError
                        :message="endForm.errors.end_date"
                    /><DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="endForm.processing"
                            @click="endOpen = false"
                            >{{ t('salary.cancel') }}</Button
                        ><Button :disabled="endForm.processing">{{
                            t('salary.end')
                        }}</Button></DialogFooter
                    >
                </form></DialogContent
            ></Dialog
        >
        <SalaryEmploymentEditDialog
            v-model:open="editOpen"
            :employment="employment"
            :options="revisionOptions"
        />
        <Dialog
            :open="explainDetail !== null"
            @update:open="
                (value) => {
                    if (!value) explainDetail = null;
                }
            "
            ><DialogContent
                ><DialogHeader
                    ><DialogTitle>{{ t('salary.explain') }}</DialogTitle
                    ><DialogDescription>{{
                        t('salary.explainHint')
                    }}</DialogDescription></DialogHeader
                >
                <p v-if="explainDetail" class="font-semibold">
                    <SensitiveValue
                        :value="
                            money(
                                explainDetail.difference,
                                explainDetail.currency,
                            )
                        "
                    />
                </p>
                <label class="grid gap-2 text-sm"
                    >{{ t('salary.label')
                    }}<Input
                        v-model="explanation.label"
                        maxlength="150" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ t('salary.type')
                    }}<Input v-model="explanation.type" maxlength="50" /></label
                ><InputError
                    v-for="(message, key) in explanation.errors"
                    :key="key"
                    :message="message"
                /><DialogFooter
                    ><Button variant="outline" @click="explainDetail = null">{{
                        t('salary.cancel')
                    }}</Button
                    ><Button
                        :disabled="explanation.processing"
                        @click="saveExplanation"
                        >{{ t('salary.attributeDifference') }}</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </AppLayout>
</template>
