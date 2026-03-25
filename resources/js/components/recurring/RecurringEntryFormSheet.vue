<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Calendar } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/RecurringEntryController';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type {
    Auth,
    RecurringEntryFormOptions,
    RecurringEntryIndexCard,
    RecurringFormOption,
} from '@/types';

type PlanType = 'recurring' | 'installment';
type RepeatPreset =
    | 'daily'
    | 'weekly'
    | 'monthly'
    | 'quarterly'
    | 'yearly'
    | 'custom';
type EndMode = 'never' | 'after_occurrences' | 'until_date';

const NONE_VALUE = '__none__';
const weekdayOptions = [
    'mon',
    'tue',
    'wed',
    'thu',
    'fri',
    'sat',
    'sun',
] as const;
const ordinalOptions = ['first', 'second', 'third', 'fourth', 'last'] as const;
const quickInstallmentCounts = [3, 6, 12, 24];

const props = defineProps<{
    open: boolean;
    entry?: RecurringEntryIndexCard | null;
    formOptions: RecurringEntryFormOptions;
    defaultStartDate: string;
    returnToIndex?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
}>();

const { t } = useI18n();
const page = usePage();

const form = useForm({
    title: '',
    account_uuid: '',
    scope_uuid: NONE_VALUE,
    category_uuid: '',
    tracked_item_uuid: NONE_VALUE,
    merchant_uuid: NONE_VALUE,
    description: '',
    notes: '',
    direction: 'expense',
    currency: 'EUR',
    entry_type: 'recurring',
    status: 'active',
    recurrence_type: 'monthly',
    recurrence_interval: 1,
    start_date: '',
    end_date: '',
    end_mode: 'never',
    occurrences_limit: '',
    expected_amount: '',
    total_amount: '',
    installments_count: '',
    auto_generate_occurrences: true,
    auto_create_transaction: false,
    is_active: true,
});

const trackedItemCatalog = ref<RecurringFormOption[]>([]);
const creatingTrackedItem = ref(false);
const advancedOpen = ref(false);
const repeatPreset = ref<RepeatPreset>('monthly');
const customRecurrenceType = ref<
    'daily' | 'weekly' | 'monthly' | 'quarterly' | 'yearly'
>('monthly');
const customRecurrenceInterval = ref('1');
const weeklyWeekdays = ref<string[]>(['mon']);
const monthlyMode = ref<'day_of_month' | 'ordinal_weekday'>('day_of_month');
const monthlyDay = ref('1');
const ordinal = ref<'first' | 'second' | 'third' | 'fourth' | 'last'>('first');
const ordinalWeekday = ref<(typeof weekdayOptions)[number]>('mon');
const yearlyMode = ref<'month_day' | 'ordinal_weekday'>('month_day');
const yearlyMonth = ref('1');
const yearlyDay = ref('1');
const startDateInput = ref<HTMLInputElement | null>(null);
const endDateInput = ref<HTMLInputElement | null>(null);

const auth = computed(() => page.props.auth as Auth);
const formatLocale = computed(() =>
    String(auth.value.user?.format_locale ?? 'it-IT'),
);
const isEditing = computed(
    () => props.entry !== null && props.entry !== undefined,
);
const structuralLocked = computed(
    () => (props.entry?.stats.converted_occurrences ?? 0) > 0,
);

const accountOptions = computed(() =>
    props.formOptions.accounts.map((option: RecurringFormOption) => ({
        value: String(option.value),
        label: option.label,
    })),
);
const filteredCategoryOptions = computed(() =>
    props.formOptions.categories
        .filter(
            (category: RecurringFormOption) =>
                category.direction_type === null ||
                category.direction_type === undefined ||
                category.direction_type === form.direction,
        )
        .map((option: RecurringFormOption) => ({
            value: String(option.value),
            label: option.label,
        })),
);
const trackedItemOptions = computed(() =>
    trackedItemCatalog.value.map((option: RecurringFormOption) => ({
        value: String(option.value),
        label: option.label,
    })),
);
const scopeOptions = computed(() =>
    props.formOptions.scopes.map((option: RecurringFormOption) => ({
        value: String(option.value),
        label: option.label,
    })),
);

const selectedAccount = computed(
    () =>
        props.formOptions.accounts.find(
            (account: RecurringFormOption) =>
                account.value === form.account_uuid,
        ) ?? null,
);
const selectedAccountCurrency = computed(
    () =>
        selectedAccount.value?.currency ??
        auth.value.user?.base_currency_code ??
        'EUR',
);
const selectedPlanType = computed(() => form.entry_type as PlanType);
const directionAccentClass = computed(() =>
    form.direction === 'income'
        ? '!border-emerald-300 focus:!border-emerald-400 focus:!shadow-[0_0_0_3px_rgba(16,185,129,0.12)] !text-emerald-900 dark:!border-emerald-500/40 dark:!text-emerald-100'
        : '!border-rose-300 focus:!border-rose-400 focus:!shadow-[0_0_0_3px_rgba(244,63,94,0.12)] !text-rose-900 dark:!border-rose-500/40 dark:!text-rose-100',
);
const primaryAmountLabel = computed(() =>
    selectedPlanType.value === 'installment'
        ? t('transactions.recurring.form.labels.totalAmount')
        : t('transactions.recurring.form.labels.expectedAmount'),
);
const sheetTitle = computed(() =>
    isEditing.value
        ? t('transactions.recurring.form.titleEdit')
        : t('transactions.recurring.form.titleCreate'),
);
const sheetDescription = computed(() =>
    isEditing.value
        ? t('transactions.recurring.form.descriptionEdit')
        : t('transactions.recurring.form.descriptionCreate'),
);

const recurringPresetOptions = computed(() => [
    {
        value: 'daily' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.daily'),
    },
    {
        value: 'weekly' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.weekly'),
    },
    {
        value: 'monthly' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.monthly'),
    },
    {
        value: 'yearly' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.yearly'),
    },
    {
        value: 'custom' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.custom'),
    },
]);
const installmentPresetOptions = computed(() => [
    {
        value: 'monthly' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.monthly'),
    },
    {
        value: 'quarterly' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.quarterly'),
    },
    {
        value: 'yearly' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.yearly'),
    },
    {
        value: 'custom' as RepeatPreset,
        label: t('transactions.recurring.form.repeatPresets.custom'),
    },
]);
const repeatPresetOptions = computed(() =>
    selectedPlanType.value === 'installment'
        ? installmentPresetOptions.value
        : recurringPresetOptions.value,
);
const customRecurrenceTypeOptions = computed(() => [
    {
        value: 'daily',
        label: t('transactions.recurring.form.customUnits.daily'),
    },
    {
        value: 'weekly',
        label: t('transactions.recurring.form.customUnits.weekly'),
    },
    {
        value: 'monthly',
        label: t('transactions.recurring.form.customUnits.monthly'),
    },
    {
        value: 'quarterly',
        label: t('transactions.recurring.form.customUnits.quarterly'),
    },
    {
        value: 'yearly',
        label: t('transactions.recurring.form.customUnits.yearly'),
    },
]);
const installmentPreview = computed(() => {
    if (
        selectedPlanType.value !== 'installment' ||
        form.total_amount === '' ||
        form.installments_count === ''
    ) {
        return null;
    }

    const totalAmount = Number(form.total_amount);
    const installmentsCount = Number(form.installments_count);

    if (
        !Number.isFinite(totalAmount) ||
        !Number.isFinite(installmentsCount) ||
        installmentsCount <= 0
    ) {
        return null;
    }

    return (Math.floor((totalAmount / installmentsCount) * 100) / 100).toFixed(
        2,
    );
});
const recurrenceConfigurationError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return (
        errors.recurrence_type ||
        errors.recurrence_interval ||
        errors.recurrence_rule ||
        ''
    );
});
const repetitionLimitOptions = computed(() => {
    if (selectedPlanType.value !== 'recurring' || form.start_date === '') {
        return [];
    }

    const startDate = parseLocalDate(form.start_date);

    if (startDate === null) {
        return [];
    }

    const config = resolveRecurrenceConfig();

    return Array.from({ length: 11 }, (_, index) => {
        const repetitionsCount = index + 2;
        let projectedDate = startDate;

        for (let step = 1; step < repetitionsCount; step += 1) {
            projectedDate = nextOccurrenceDate(projectedDate, config);
        }

        return {
            value: String(repetitionsCount),
            label: t('transactions.recurring.form.repetitionOption', {
                count: repetitionsCount,
                date: formatLocalDateLabel(projectedDate),
            }),
        };
    });
});

watch(
    () => [props.open, props.entry] as const,
    ([open, entry]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        trackedItemCatalog.value = [...props.formOptions.tracked_items];
        advancedOpen.value = false;

        if (entry) {
            const primaryDescription = entry.description?.trim() || entry.title;

            form.defaults({
                title: entry.title,
                account_uuid: entry.account?.uuid ?? '',
                scope_uuid: entry.scope?.uuid ?? NONE_VALUE,
                category_uuid: entry.category?.uuid ?? '',
                tracked_item_uuid: entry.tracked_item?.uuid ?? NONE_VALUE,
                merchant_uuid: entry.merchant?.uuid ?? NONE_VALUE,
                description: primaryDescription,
                notes: entry.notes ?? '',
                direction: entry.direction ?? 'expense',
                currency: entry.currency ?? entry.account?.currency ?? 'EUR',
                entry_type: entry.entry_type ?? 'recurring',
                status: entry.status ?? 'active',
                recurrence_type: entry.recurrence_type ?? 'monthly',
                recurrence_interval: entry.recurrence_interval ?? 1,
                start_date: entry.start_date ?? props.defaultStartDate,
                end_date: entry.end_date ?? '',
                end_mode: entry.end_mode ?? 'never',
                occurrences_limit: entry.occurrences_limit
                    ? String(entry.occurrences_limit)
                    : '',
                expected_amount:
                    entry.expected_amount !== null
                        ? String(entry.expected_amount)
                        : '',
                total_amount:
                    entry.total_amount !== null
                        ? String(entry.total_amount)
                        : '',
                installments_count:
                    entry.installments_count !== null
                        ? String(entry.installments_count)
                        : '',
                auto_generate_occurrences: entry.auto_generate_occurrences,
                auto_create_transaction: entry.auto_create_transaction,
                is_active: entry.is_active,
            });
            form.reset();
            hydrateRuleState(
                entry.entry_type ?? 'recurring',
                entry.recurrence_type,
                entry.recurrence_interval,
                entry.recurrence_rule ?? {},
                entry.start_date ?? props.defaultStartDate,
            );

            return;
        }

        form.defaults({
            title: '',
            account_uuid: props.formOptions.accounts[0]
                ? String(props.formOptions.accounts[0].value)
                : '',
            scope_uuid: NONE_VALUE,
            category_uuid: '',
            tracked_item_uuid: NONE_VALUE,
            merchant_uuid: NONE_VALUE,
            description: '',
            notes: '',
            direction: 'expense',
            currency: props.formOptions.accounts[0]?.currency ?? 'EUR',
            entry_type: 'recurring',
            status: 'active',
            recurrence_type: 'monthly',
            recurrence_interval: 1,
            start_date: props.defaultStartDate,
            end_date: '',
            end_mode: 'never',
            occurrences_limit: '',
            expected_amount: '',
            total_amount: '',
            installments_count: '',
            auto_generate_occurrences: true,
            auto_create_transaction: false,
            is_active: true,
        });
        form.reset();
        hydrateRuleState('recurring', 'monthly', 1, {}, props.defaultStartDate);
    },
    { immediate: true },
);

watch(
    () => form.account_uuid,
    (accountUuid) => {
        const account = props.formOptions.accounts.find(
            (option: RecurringFormOption) => option.value === accountUuid,
        );

        if (account?.currency) {
            form.currency = account.currency;
        }
    },
);

watch(
    () => form.direction,
    () => {
        if (
            form.category_uuid !== '' &&
            !filteredCategoryOptions.value.some(
                (category: { value: string; label: string }) =>
                    category.value === form.category_uuid,
            )
        ) {
            form.category_uuid = '';
        }
    },
);

watch(
    () => form.start_date,
    (value) => {
        if (!value) {
            return;
        }

        const currentStartDate = new Date(`${value}T00:00:00`);

        if (
            repeatPreset.value === 'weekly' &&
            selectedPlanType.value === 'recurring'
        ) {
            weeklyWeekdays.value = [weekdayFromDate(currentStartDate)];
        }

        if (
            repeatPreset.value === 'monthly' ||
            repeatPreset.value === 'quarterly' ||
            (repeatPreset.value === 'custom' &&
                ['monthly', 'quarterly'].includes(customRecurrenceType.value))
        ) {
            monthlyDay.value = String(currentStartDate.getDate());
        }

        if (
            repeatPreset.value === 'yearly' ||
            (repeatPreset.value === 'custom' &&
                customRecurrenceType.value === 'yearly')
        ) {
            yearlyMonth.value = String(currentStartDate.getMonth() + 1);
            yearlyDay.value = String(currentStartDate.getDate());
        }
    },
);

watch(
    () => form.entry_type,
    (entryType, previousType) => {
        if (entryType === previousType) {
            return;
        }

        if (entryType === 'installment') {
            if (form.total_amount === '' && form.expected_amount !== '') {
                form.total_amount = form.expected_amount;
            }

            if (
                form.installments_count === '' &&
                form.occurrences_limit !== ''
            ) {
                form.installments_count = form.occurrences_limit;
            }

            if (
                !['monthly', 'quarterly', 'yearly', 'custom'].includes(
                    repeatPreset.value,
                )
            ) {
                repeatPreset.value = 'monthly';
            }

            form.end_mode = 'after_occurrences';
            form.end_date = '';

            return;
        }

        if (form.expected_amount === '' && form.total_amount !== '') {
            form.expected_amount = form.total_amount;
        }

        if (repeatPreset.value === 'quarterly') {
            customRecurrenceType.value = 'quarterly';
            customRecurrenceInterval.value = '1';
            repeatPreset.value = 'custom';
        }
    },
);

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function setPlanType(entryType: PlanType): void {
    form.entry_type = entryType;
}

function openDatePicker(target: 'start' | 'end'): void {
    const input =
        target === 'start' ? startDateInput.value : endDateInput.value;

    if (!input) {
        return;
    }

    if (typeof input.showPicker === 'function') {
        input.showPicker();

        return;
    }

    input.focus();
}

function parseLocalDate(value: string): Date | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function formatLocalDateLabel(date: Date): string {
    return new Intl.DateTimeFormat(formatLocale.value, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function daysInMonth(year: number, monthIndex: number): number {
    return new Date(year, monthIndex + 1, 0).getDate();
}

function clampDay(year: number, monthIndex: number, day: number): number {
    return Math.min(day, daysInMonth(year, monthIndex));
}

function addDays(date: Date, amount: number): Date {
    const nextDate = new Date(date);

    nextDate.setDate(nextDate.getDate() + amount);

    return nextDate;
}

function nthWeekdayOfMonth(
    year: number,
    monthIndex: number,
    weekdayCode: (typeof weekdayOptions)[number],
    ordinalCode: (typeof ordinalOptions)[number],
): Date {
    const targetWeekday = weekdayOptions.indexOf(weekdayCode);
    const firstDay = new Date(year, monthIndex, 1);
    const firstWeekday = (firstDay.getDay() + 6) % 7;
    const offset = (targetWeekday - firstWeekday + 7) % 7;

    if (ordinalCode === 'last') {
        const lastDayNumber = daysInMonth(year, monthIndex);
        const lastDate = new Date(year, monthIndex, lastDayNumber);
        const lastWeekday = (lastDate.getDay() + 6) % 7;
        const lastOffset = (lastWeekday - targetWeekday + 7) % 7;

        return new Date(year, monthIndex, lastDayNumber - lastOffset);
    }

    const ordinalIndex = ordinalOptions.indexOf(ordinalCode);

    return new Date(year, monthIndex, 1 + offset + ordinalIndex * 7);
}

function addMonthsForRule(
    date: Date,
    stepMonths: number,
    rule: Record<string, unknown>,
): Date {
    const targetYear = date.getFullYear();
    const targetMonthIndex = date.getMonth() + stepMonths;
    const normalizedTarget = new Date(targetYear, targetMonthIndex, 1);
    const mode = String(rule.mode ?? 'day_of_month');

    if (mode === 'ordinal_weekday') {
        const ordinalCode = isOrdinalValue(
            String(rule.ordinal ?? ordinal.value),
        )
            ? (String(
                  rule.ordinal ?? ordinal.value,
              ) as (typeof ordinalOptions)[number])
            : 'first';
        const weekdayCode = isWeekdayValue(
            String(rule.weekday ?? ordinalWeekday.value),
        )
            ? (String(
                  rule.weekday ?? ordinalWeekday.value,
              ) as (typeof weekdayOptions)[number])
            : weekdayFromDate(date);

        return nthWeekdayOfMonth(
            normalizedTarget.getFullYear(),
            normalizedTarget.getMonth(),
            weekdayCode,
            ordinalCode,
        );
    }

    const targetDay = Number(rule.day ?? date.getDate());

    return new Date(
        normalizedTarget.getFullYear(),
        normalizedTarget.getMonth(),
        clampDay(
            normalizedTarget.getFullYear(),
            normalizedTarget.getMonth(),
            targetDay,
        ),
    );
}

function nextWeeklyDate(
    date: Date,
    interval: number,
    rule: Record<string, unknown>,
): Date {
    const weekdays = Array.isArray(rule.weekdays)
        ? rule.weekdays.filter(
              (value): value is string => typeof value === 'string',
          )
        : [weekdayFromDate(date)];
    const normalizedWeekdays = weekdays
        .filter((value): value is (typeof weekdayOptions)[number] =>
            isWeekdayValue(value),
        )
        .sort(
            (first, second) =>
                weekdayOptions.indexOf(first) - weekdayOptions.indexOf(second),
        );
    const currentWeekdayIndex = weekdayOptions.indexOf(weekdayFromDate(date));

    for (const weekday of normalizedWeekdays) {
        const targetIndex = weekdayOptions.indexOf(weekday);

        if (targetIndex > currentWeekdayIndex) {
            return addDays(date, targetIndex - currentWeekdayIndex);
        }
    }

    const firstWeekday = normalizedWeekdays[0] ?? weekdayFromDate(date);
    const firstWeekdayIndex = weekdayOptions.indexOf(firstWeekday);
    const daysToNextCycle =
        (interval - 1) * 7 + (7 - currentWeekdayIndex) + firstWeekdayIndex;

    return addDays(date, daysToNextCycle);
}

function nextYearlyDate(
    date: Date,
    interval: number,
    rule: Record<string, unknown>,
): Date {
    const targetYear = date.getFullYear() + interval;
    const mode = String(rule.mode ?? 'month_day');
    const targetMonth = Number(rule.month ?? date.getMonth() + 1);
    const targetMonthIndex = Math.max(0, Math.min(11, targetMonth - 1));

    if (mode === 'ordinal_weekday') {
        const ordinalCode = isOrdinalValue(
            String(rule.ordinal ?? ordinal.value),
        )
            ? (String(
                  rule.ordinal ?? ordinal.value,
              ) as (typeof ordinalOptions)[number])
            : 'first';
        const weekdayCode = isWeekdayValue(
            String(rule.weekday ?? ordinalWeekday.value),
        )
            ? (String(
                  rule.weekday ?? ordinalWeekday.value,
              ) as (typeof weekdayOptions)[number])
            : weekdayFromDate(date);

        return nthWeekdayOfMonth(
            targetYear,
            targetMonthIndex,
            weekdayCode,
            ordinalCode,
        );
    }

    const targetDay = Number(rule.day ?? date.getDate());

    return new Date(
        targetYear,
        targetMonthIndex,
        clampDay(targetYear, targetMonthIndex, targetDay),
    );
}

function nextOccurrenceDate(
    date: Date,
    config: ReturnType<typeof resolveRecurrenceConfig>,
): Date {
    if (config.recurrenceType === 'daily') {
        return addDays(date, config.recurrenceInterval);
    }

    if (config.recurrenceType === 'weekly') {
        return nextWeeklyDate(
            date,
            config.recurrenceInterval,
            config.recurrenceRule,
        );
    }

    if (config.recurrenceType === 'monthly') {
        return addMonthsForRule(
            date,
            config.recurrenceInterval,
            config.recurrenceRule,
        );
    }

    if (config.recurrenceType === 'quarterly') {
        return addMonthsForRule(
            date,
            config.recurrenceInterval * 3,
            config.recurrenceRule,
        );
    }

    return nextYearlyDate(
        date,
        config.recurrenceInterval,
        config.recurrenceRule,
    );
}

function setDirection(direction: 'income' | 'expense'): void {
    form.direction = direction;
}

function setRepeatPreset(preset: RepeatPreset): void {
    repeatPreset.value = preset;

    if (preset !== 'custom') {
        customRecurrenceInterval.value = '1';
    }
}

function setEndMode(endMode: EndMode): void {
    form.end_mode = endMode;

    if (endMode !== 'until_date') {
        form.end_date = '';
    }

    if (endMode !== 'after_occurrences') {
        form.occurrences_limit = '';
    }
}

function updateCustomRecurrenceType(value: string): void {
    if (isCustomRecurrenceType(value)) {
        customRecurrenceType.value = value;
    }
}

function updateMonthlyMode(value: string): void {
    monthlyMode.value =
        value === 'ordinal_weekday' ? 'ordinal_weekday' : 'day_of_month';
}

function updateOrdinal(value: string): void {
    if (isOrdinalValue(value)) {
        ordinal.value = value;
    }
}

function updateOrdinalWeekday(value: string): void {
    if (isWeekdayValue(value)) {
        ordinalWeekday.value = value;
    }
}

function updateYearlyMode(value: string): void {
    yearlyMode.value =
        value === 'ordinal_weekday' ? 'ordinal_weekday' : 'month_day';
}

function hydrateRuleState(
    entryType: string | null,
    recurrenceType: string | null,
    recurrenceInterval: number | null,
    rule: Record<string, unknown>,
    startDate: string,
): void {
    const normalizedEntryType =
        entryType === 'installment' ? 'installment' : 'recurring';
    const normalizedRecurrenceType = recurrenceType ?? 'monthly';
    const start = new Date(`${startDate}T00:00:00`);
    const weekdays = Array.isArray(rule.weekdays)
        ? rule.weekdays.map((value) => String(value))
        : [weekdayFromDate(start)];

    weeklyWeekdays.value =
        weekdays.length > 0 ? weekdays : [weekdayFromDate(start)];
    monthlyMode.value =
        rule.mode === 'ordinal_weekday' ? 'ordinal_weekday' : 'day_of_month';
    monthlyDay.value = String(rule.day ?? start.getDate());
    ordinal.value = isOrdinalValue(String(rule.ordinal ?? 'first'))
        ? (String(rule.ordinal) as typeof ordinal.value)
        : 'first';
    ordinalWeekday.value = isWeekdayValue(
        String(rule.weekday ?? weekdayFromDate(start)),
    )
        ? (String(
              rule.weekday ?? weekdayFromDate(start),
          ) as typeof ordinalWeekday.value)
        : 'mon';
    yearlyMode.value =
        rule.mode === 'ordinal_weekday' ? 'ordinal_weekday' : 'month_day';
    yearlyMonth.value = String(rule.month ?? start.getMonth() + 1);
    yearlyDay.value = String(rule.day ?? start.getDate());
    customRecurrenceType.value = isCustomRecurrenceType(
        normalizedRecurrenceType,
    )
        ? normalizedRecurrenceType
        : 'monthly';
    customRecurrenceInterval.value = String(recurrenceInterval ?? 1);
    repeatPreset.value = inferRepeatPreset(
        normalizedEntryType,
        normalizedRecurrenceType,
        recurrenceInterval ?? 1,
        rule,
        start,
    );
}

function inferRepeatPreset(
    entryType: string,
    recurrenceType: string,
    recurrenceInterval: number,
    rule: Record<string, unknown>,
    startDate: Date,
): RepeatPreset {
    if (recurrenceInterval !== 1) {
        return 'custom';
    }

    const startWeekday = weekdayFromDate(startDate);
    const ruleMode = String(rule.mode ?? '');
    const ruleDay = Number(rule.day ?? startDate.getDate());
    const ruleMonth = Number(rule.month ?? startDate.getMonth() + 1);
    const ruleWeekdays = Array.isArray(rule.weekdays)
        ? rule.weekdays.map((value) => String(value))
        : [];

    if (entryType === 'installment') {
        if (
            recurrenceType === 'monthly' &&
            (ruleMode === '' ||
                (ruleMode === 'day_of_month' &&
                    ruleDay === startDate.getDate()))
        ) {
            return 'monthly';
        }

        if (
            recurrenceType === 'quarterly' &&
            (ruleMode === '' ||
                (ruleMode === 'day_of_month' &&
                    ruleDay === startDate.getDate()))
        ) {
            return 'quarterly';
        }

        if (
            recurrenceType === 'yearly' &&
            (ruleMode === '' ||
                (ruleMode === 'month_day' &&
                    ruleMonth === startDate.getMonth() + 1 &&
                    ruleDay === startDate.getDate()))
        ) {
            return 'yearly';
        }

        return 'custom';
    }

    if (recurrenceType === 'daily') {
        return 'daily';
    }

    if (
        recurrenceType === 'weekly' &&
        (ruleWeekdays.length === 0 ||
            (ruleWeekdays.length === 1 && ruleWeekdays[0] === startWeekday))
    ) {
        return 'weekly';
    }

    if (
        recurrenceType === 'monthly' &&
        (ruleMode === '' ||
            (ruleMode === 'day_of_month' && ruleDay === startDate.getDate()))
    ) {
        return 'monthly';
    }

    if (
        recurrenceType === 'yearly' &&
        (ruleMode === '' ||
            (ruleMode === 'month_day' &&
                ruleMonth === startDate.getMonth() + 1 &&
                ruleDay === startDate.getDate()))
    ) {
        return 'yearly';
    }

    return 'custom';
}

function isCustomRecurrenceType(
    value: string,
): value is 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'yearly' {
    return ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'].includes(
        value,
    );
}

function isOrdinalValue(
    value: string,
): value is (typeof ordinalOptions)[number] {
    return ordinalOptions.includes(value as (typeof ordinalOptions)[number]);
}

function isWeekdayValue(
    value: string,
): value is (typeof weekdayOptions)[number] {
    return weekdayOptions.includes(value as (typeof weekdayOptions)[number]);
}

function weekdayFromDate(date: Date): (typeof weekdayOptions)[number] {
    return weekdayOptions[(date.getDay() + 6) % 7];
}

function toggleWeekday(code: string): void {
    weeklyWeekdays.value = weeklyWeekdays.value.includes(code)
        ? weeklyWeekdays.value.filter((value) => value !== code)
        : [...weeklyWeekdays.value, code];
}

function setBooleanField(
    field: 'auto_generate_occurrences' | 'is_active',
    checked: boolean | 'indeterminate',
): void {
    form[field] = checked === true;
}

function closeSheet(): void {
    form.clearErrors();
    emit('update:open', false);
}

function errorMessage(
    ...messages: Array<string | undefined>
): string | undefined {
    return messages.find(
        (message) => typeof message === 'string' && message.length > 0,
    );
}

function formError(...keys: string[]): string | undefined {
    const errors = form.errors as Record<string, string | undefined>;

    return errorMessage(...keys.map((key) => errors[key]));
}

function hasFieldError(...messages: Array<string | undefined>): boolean {
    return errorMessage(...messages) !== undefined;
}

function hasFormError(...keys: string[]): boolean {
    return formError(...keys) !== undefined;
}

function fieldErrorClass(hasError: boolean): string {
    return hasError
        ? 'border-rose-300 ring-1 ring-rose-200 dark:border-rose-500/50 dark:ring-rose-500/20'
        : 'border-slate-200 dark:border-slate-800';
}

function createTrackedItemPayload(name: string): Record<string, unknown> {
    const categoryUuids = form.category_uuid !== '' ? [form.category_uuid] : [];

    return {
        name,
        parent_uuid: null,
        type: null,
        is_active: true,
        category_uuids: categoryUuids,
        settings: {
            transaction_group_keys: [form.direction],
            transaction_category_uuids: categoryUuids,
        },
    };
}

async function createTrackedItemFromContext(name: string): Promise<void> {
    creatingTrackedItem.value = true;

    try {
        const response = await fetch('/settings/tracked-items', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(createTrackedItemPayload(name)),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const firstError = payload?.errors
                ? Object.values(payload.errors)[0]
                : null;

            form.setError(
                'tracked_item_uuid',
                Array.isArray(firstError)
                    ? String(firstError[0])
                    : t(
                          'transactions.recurring.form.errors.createTrackedItemFailed',
                      ),
            );

            return;
        }

        const payload = await response.json();
        const option = payload.item as {
            value?: string;
            label: string;
            uuid?: string;
        };
        const optionValue = option.value ?? option.uuid;

        if (!optionValue) {
            form.setError(
                'tracked_item_uuid',
                t('transactions.recurring.form.errors.createTrackedItemFailed'),
            );

            return;
        }

        trackedItemCatalog.value = [
            ...trackedItemCatalog.value.filter(
                (trackedItem: RecurringFormOption) =>
                    trackedItem.value !== optionValue,
            ),
            {
                value: optionValue,
                label: option.label,
                uuid: option.uuid,
            },
        ].sort((first, second) =>
            first.label.localeCompare(second.label, 'it'),
        );
        form.tracked_item_uuid = optionValue;
        form.clearErrors('tracked_item_uuid');
    } catch (error) {
        form.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : t(
                      'transactions.recurring.form.errors.createTrackedItemFailed',
                  ),
        );
    } finally {
        creatingTrackedItem.value = false;
    }
}

function normalizedPrimaryDescription(): string {
    return form.description.trim();
}

function normalizeMoneyField(
    field: 'expected_amount' | 'total_amount',
): number | null {
    const rawValue = form[field];

    if (rawValue === '') {
        form.clearErrors(field);

        return null;
    }

    const parsedValue = Number(rawValue);

    if (!Number.isFinite(parsedValue) || parsedValue <= 0) {
        form.setError(
            field,
            t('transactions.recurring.form.errors.amountPositive'),
        );

        return null;
    }

    form[field] = String(parsedValue);
    form.clearErrors(field);

    return parsedValue;
}

function buildRecurrenceRule(recurrenceType: string): Record<string, unknown> {
    if (recurrenceType === 'weekly') {
        return {
            weekdays:
                weeklyWeekdays.value.length > 0
                    ? weeklyWeekdays.value
                    : ['mon'],
        };
    }

    if (recurrenceType === 'monthly' || recurrenceType === 'quarterly') {
        if (monthlyMode.value === 'ordinal_weekday') {
            return {
                mode: 'ordinal_weekday',
                ordinal: ordinal.value,
                weekday: ordinalWeekday.value,
            };
        }

        return {
            mode: 'day_of_month',
            day: Number(monthlyDay.value || 1),
        };
    }

    if (recurrenceType === 'yearly') {
        if (yearlyMode.value === 'ordinal_weekday') {
            return {
                mode: 'ordinal_weekday',
                month: Number(yearlyMonth.value || 1),
                ordinal: ordinal.value,
                weekday: ordinalWeekday.value,
            };
        }

        return {
            mode: 'month_day',
            month: Number(yearlyMonth.value || 1),
            day: Number(yearlyDay.value || 1),
        };
    }

    return {};
}

function resolveRecurrenceConfig(): {
    recurrenceType: string;
    recurrenceInterval: number;
    recurrenceRule: Record<string, unknown>;
} {
    const startDate = new Date(`${form.start_date}T00:00:00`);

    if (repeatPreset.value !== 'custom') {
        if (repeatPreset.value === 'daily') {
            return {
                recurrenceType: 'daily',
                recurrenceInterval: 1,
                recurrenceRule: {},
            };
        }

        if (repeatPreset.value === 'weekly') {
            return {
                recurrenceType: 'weekly',
                recurrenceInterval: 1,
                recurrenceRule: {
                    weekdays: [weekdayFromDate(startDate)],
                },
            };
        }

        if (
            repeatPreset.value === 'monthly' ||
            repeatPreset.value === 'quarterly'
        ) {
            return {
                recurrenceType: repeatPreset.value,
                recurrenceInterval: 1,
                recurrenceRule: {
                    mode: 'day_of_month',
                    day: startDate.getDate(),
                },
            };
        }

        return {
            recurrenceType: 'yearly',
            recurrenceInterval: 1,
            recurrenceRule: {
                mode: 'month_day',
                month: startDate.getMonth() + 1,
                day: startDate.getDate(),
            },
        };
    }

    const recurrenceType = customRecurrenceType.value;

    return {
        recurrenceType,
        recurrenceInterval: Number(customRecurrenceInterval.value || 1),
        recurrenceRule: buildRecurrenceRule(recurrenceType),
    };
}

function submit(): void {
    const primaryDescription = normalizedPrimaryDescription();

    form.clearErrors();

    if (form.account_uuid === '') {
        form.setError(
            'account_uuid',
            t('transactions.recurring.form.errors.accountRequired'),
        );
    }

    if (form.category_uuid === '') {
        form.setError(
            'category_uuid',
            t('transactions.recurring.form.errors.categoryRequired'),
        );
    }

    if (form.start_date === '') {
        form.setError(
            'start_date',
            t('transactions.recurring.form.errors.startDateRequired'),
        );
    }

    if (primaryDescription === '') {
        form.setError(
            'description',
            t('transactions.recurring.form.errors.descriptionRequired'),
        );
    }

    if (
        selectedPlanType.value === 'installment' &&
        form.installments_count === ''
    ) {
        form.setError(
            'installments_count',
            t('transactions.recurring.form.errors.installmentsCountRequired'),
        );
    }

    if (
        selectedPlanType.value === 'recurring' &&
        form.end_mode === 'after_occurrences' &&
        form.occurrences_limit === ''
    ) {
        form.setError(
            'occurrences_limit',
            t('transactions.recurring.form.errors.repetitionsCountRequired'),
        );
    }

    if (
        selectedPlanType.value === 'recurring' &&
        form.end_mode === 'until_date' &&
        form.end_date === ''
    ) {
        form.setError(
            'end_date',
            t('transactions.recurring.form.errors.endDateRequired'),
        );
    }

    if (
        form.start_date !== '' &&
        form.end_date !== '' &&
        form.end_date < form.start_date
    ) {
        form.setError(
            'end_date',
            t('transactions.recurring.form.errors.endDateBeforeStartDate'),
        );
    }

    if (form.hasErrors) {
        return;
    }

    const amountField =
        selectedPlanType.value === 'installment'
            ? 'total_amount'
            : 'expected_amount';
    const normalizedAmount = normalizeMoneyField(amountField);

    if (normalizedAmount === null) {
        return;
    }

    const { recurrenceType, recurrenceInterval, recurrenceRule } =
        resolveRecurrenceConfig();
    const payload = {
        ...form.data(),
        redirect_to: props.returnToIndex ? 'index' : null,
        title: primaryDescription.slice(0, 150),
        account_uuid: form.account_uuid,
        scope_uuid: form.scope_uuid === NONE_VALUE ? null : form.scope_uuid,
        category_uuid: form.category_uuid,
        tracked_item_uuid:
            form.tracked_item_uuid === NONE_VALUE
                ? null
                : form.tracked_item_uuid,
        merchant_uuid:
            form.merchant_uuid === NONE_VALUE ? null : form.merchant_uuid,
        description: primaryDescription,
        notes: form.notes.trim() || null,
        currency: selectedAccountCurrency.value,
        recurrence_type: recurrenceType,
        recurrence_interval: recurrenceInterval,
        recurrence_rule: recurrenceRule,
        end_date: form.end_date || null,
        end_mode:
            selectedPlanType.value === 'installment'
                ? 'after_occurrences'
                : form.end_mode,
        occurrences_limit:
            selectedPlanType.value === 'installment'
                ? form.installments_count !== ''
                    ? Number(form.installments_count)
                    : null
                : form.occurrences_limit !== ''
                  ? Number(form.occurrences_limit)
                  : null,
        expected_amount:
            selectedPlanType.value === 'recurring' ? normalizedAmount : null,
        total_amount:
            selectedPlanType.value === 'installment' ? normalizedAmount : null,
        installments_count:
            selectedPlanType.value === 'installment'
                ? form.installments_count !== ''
                    ? Number(form.installments_count)
                    : null
                : null,
    };

    if (isEditing.value && props.entry) {
        form.transform(() => payload).patch(update.url(props.entry.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved', t('transactions.recurring.feedback.updated'));
                closeSheet();
            },
        });

        return;
    }

    form.transform(() => payload).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', t('transactions.recurring.feedback.created'));
            closeSheet();
        },
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full border-l p-0 sm:max-w-3xl">
            <div class="flex h-full flex-col">
                <SheetHeader
                    class="border-b border-slate-200/80 px-6 py-6 dark:border-slate-800"
                >
                    <SheetTitle>{{ sheetTitle }}</SheetTitle>
                    <SheetDescription>
                        {{ sheetDescription }}
                    </SheetDescription>
                </SheetHeader>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div
                            v-if="structuralLocked"
                            class="rounded-[24px] border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-100"
                        >
                            <p class="font-semibold">
                                {{
                                    t(
                                        'transactions.recurring.form.locked.title',
                                    )
                                }}
                            </p>
                            <p
                                class="mt-1 text-sm/6 text-amber-800 dark:text-amber-200"
                            >
                                {{
                                    t(
                                        'transactions.recurring.form.locked.description',
                                    )
                                }}
                            </p>
                        </div>

                        <section class="space-y-4">
                            <div class="space-y-2">
                                <p
                                    class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.form.sections.planType',
                                        )
                                    }}
                                </p>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <button
                                        type="button"
                                        class="rounded-[24px] border px-4 py-4 text-left transition-all"
                                        :class="
                                            cn(
                                                selectedPlanType === 'recurring'
                                                    ? 'border-sky-300 bg-sky-50 text-sky-950 shadow-sm dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-50'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200',
                                                structuralLocked
                                                    ? 'cursor-not-allowed opacity-60'
                                                    : '',
                                            )
                                        "
                                        :disabled="structuralLocked"
                                        @click="setPlanType('recurring')"
                                    >
                                        <p class="text-sm font-semibold">
                                            {{
                                                t(
                                                    'transactions.recurring.enums.entryType.recurring',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.planTypes.recurring',
                                                )
                                            }}
                                        </p>
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-[24px] border px-4 py-4 text-left transition-all"
                                        :class="
                                            cn(
                                                selectedPlanType ===
                                                    'installment'
                                                    ? 'border-sky-300 bg-sky-50 text-sky-950 shadow-sm dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-50'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200',
                                                structuralLocked
                                                    ? 'cursor-not-allowed opacity-60'
                                                    : '',
                                            )
                                        "
                                        :disabled="structuralLocked"
                                        @click="setPlanType('installment')"
                                    >
                                        <p class="text-sm font-semibold">
                                            {{
                                                t(
                                                    'transactions.recurring.enums.entryType.installment',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.planTypes.installment',
                                                )
                                            }}
                                        </p>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p
                                    class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.form.labels.direction',
                                        )
                                    }}
                                </p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        class="rounded-[22px] border px-4 py-3 text-left transition-all"
                                        :class="
                                            cn(
                                                form.direction === 'expense'
                                                    ? 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-100'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200',
                                                structuralLocked
                                                    ? 'cursor-not-allowed opacity-60'
                                                    : '',
                                            )
                                        "
                                        :disabled="structuralLocked"
                                        @click="setDirection('expense')"
                                    >
                                        {{
                                            t(
                                                'transactions.recurring.enums.direction.expense',
                                            )
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-[22px] border px-4 py-3 text-left transition-all"
                                        :class="
                                            cn(
                                                form.direction === 'income'
                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200',
                                                structuralLocked
                                                    ? 'cursor-not-allowed opacity-60'
                                                    : '',
                                            )
                                        "
                                        :disabled="structuralLocked"
                                        @click="setDirection('income')"
                                    >
                                        {{
                                            t(
                                                'transactions.recurring.enums.direction.income',
                                            )
                                        }}
                                    </button>
                                </div>
                                <InputError :message="form.errors.direction" />
                            </div>
                        </section>

                        <section
                            class="grid gap-5 md:grid-cols-[minmax(0,1fr)_220px]"
                        >
                            <div class="grid gap-2">
                                <Label>{{
                                    t(
                                        'transactions.recurring.form.labels.account',
                                    )
                                }}</Label>
                                <SearchableSelect
                                    v-model="form.account_uuid"
                                    :options="accountOptions"
                                    :placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.selectAccount',
                                        )
                                    "
                                    :search-placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.searchAccount',
                                        )
                                    "
                                    :empty-label="
                                        t(
                                            'transactions.recurring.form.placeholders.noSearchResults',
                                        )
                                    "
                                    :disabled="structuralLocked"
                                    :teleport="false"
                                    :trigger-class="
                                        cn(
                                            'h-11 rounded-2xl',
                                            fieldErrorClass(
                                                hasFormError(
                                                    'account_uuid',
                                                    'account_id',
                                                ),
                                            ),
                                        )
                                    "
                                    content-class="z-[260]"
                                />
                                <InputError
                                    :message="
                                        formError('account_uuid', 'account_id')
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t(
                                        'transactions.recurring.form.labels.currency',
                                    )
                                }}</Label>
                                <div
                                    class="flex h-11 items-center rounded-2xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-100"
                                >
                                    <span>{{ selectedAccountCurrency }}</span>
                                    <span
                                        class="ml-auto text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'transactions.recurring.form.helper.accountCurrencyReadonly',
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section
                            class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
                        >
                            <div class="grid gap-2">
                                <MoneyInput
                                    v-if="selectedPlanType === 'installment'"
                                    id="recurring-primary-amount"
                                    v-model="form.total_amount"
                                    :label="primaryAmountLabel"
                                    :format-locale="formatLocale"
                                    :currency-code="selectedAccountCurrency"
                                    :disabled="structuralLocked"
                                    :placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.amount',
                                        )
                                    "
                                    :class="
                                        cn(
                                            'h-11 rounded-2xl',
                                            directionAccentClass,
                                            hasFieldError(
                                                form.errors.total_amount,
                                            )
                                                ? fieldErrorClass(true)
                                                : '',
                                        )
                                    "
                                    @blur="normalizeMoneyField('total_amount')"
                                />
                                <MoneyInput
                                    v-else
                                    id="recurring-primary-amount"
                                    v-model="form.expected_amount"
                                    :label="primaryAmountLabel"
                                    :format-locale="formatLocale"
                                    :currency-code="selectedAccountCurrency"
                                    :disabled="structuralLocked"
                                    :placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.amount',
                                        )
                                    "
                                    :class="
                                        cn(
                                            'h-11 rounded-2xl',
                                            directionAccentClass,
                                            hasFieldError(
                                                form.errors.expected_amount,
                                            )
                                                ? fieldErrorClass(true)
                                                : '',
                                        )
                                    "
                                    @blur="
                                        normalizeMoneyField('expected_amount')
                                    "
                                />
                                <InputError
                                    :message="
                                        selectedPlanType === 'installment'
                                            ? form.errors.total_amount
                                            : form.errors.expected_amount
                                    "
                                />
                            </div>

                            <div
                                v-if="selectedPlanType === 'installment'"
                                class="grid gap-2"
                            >
                                <Label for="installments-count">{{
                                    t(
                                        'transactions.recurring.form.labels.installmentsCount',
                                    )
                                }}</Label>
                                <Input
                                    id="installments-count"
                                    v-model="form.installments_count"
                                    type="number"
                                    min="1"
                                    :disabled="structuralLocked"
                                    :class="
                                        cn(
                                            'h-11 rounded-2xl',
                                            fieldErrorClass(
                                                hasFieldError(
                                                    form.errors
                                                        .installments_count,
                                                ),
                                            ),
                                        )
                                    "
                                />
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="count in quickInstallmentCounts"
                                        :key="count"
                                        type="button"
                                        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                        :class="
                                            form.installments_count ===
                                            String(count)
                                                ? 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200'
                                                : 'border-slate-200 bg-white text-slate-600 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-300'
                                        "
                                        :disabled="structuralLocked"
                                        @click="
                                            form.installments_count =
                                                String(count)
                                        "
                                    >
                                        {{ count }}
                                        {{
                                            t(
                                                'transactions.recurring.form.quickActions.installments',
                                            )
                                        }}
                                    </button>
                                </div>
                                <p
                                    v-if="installmentPreview"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.form.helper.installmentPreview',
                                            {
                                                amount: installmentPreview,
                                                currency:
                                                    selectedAccountCurrency,
                                            },
                                        )
                                    }}
                                </p>
                                <InputError
                                    :message="form.errors.installments_count"
                                />
                            </div>
                        </section>

                        <section class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="recurring-description">{{
                                    t(
                                        'transactions.recurring.form.labels.descriptionPrimary',
                                    )
                                }}</Label>
                                <Input
                                    id="recurring-description"
                                    v-model="form.description"
                                    :placeholder="
                                        selectedPlanType === 'installment'
                                            ? t(
                                                  'transactions.recurring.form.placeholders.installmentDescription',
                                              )
                                            : t(
                                                  'transactions.recurring.form.placeholders.recurringDescription',
                                              )
                                    "
                                    :class="
                                        cn(
                                            'h-11 rounded-2xl',
                                            fieldErrorClass(
                                                hasFieldError(
                                                    form.errors.description,
                                                    form.errors.title,
                                                ),
                                            ),
                                        )
                                    "
                                />
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.form.helper.descriptionPrimary',
                                        )
                                    }}
                                </p>
                                <InputError
                                    :message="
                                        form.errors.description ||
                                        form.errors.title
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t(
                                        'transactions.recurring.form.labels.category',
                                    )
                                }}</Label>
                                <SearchableSelect
                                    v-model="form.category_uuid"
                                    :options="filteredCategoryOptions"
                                    :placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.selectCategory',
                                        )
                                    "
                                    :search-placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.searchCategory',
                                        )
                                    "
                                    :empty-label="
                                        t(
                                            'transactions.recurring.form.placeholders.noSearchResults',
                                        )
                                    "
                                    :disabled="structuralLocked"
                                    :teleport="false"
                                    :trigger-class="
                                        cn(
                                            'h-11 rounded-2xl',
                                            fieldErrorClass(
                                                hasFormError(
                                                    'category_uuid',
                                                    'category_id',
                                                ),
                                            ),
                                        )
                                    "
                                    content-class="z-[260]"
                                />
                                <InputError
                                    :message="
                                        formError(
                                            'category_uuid',
                                            'category_id',
                                        )
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t(
                                        'transactions.recurring.form.labels.trackedItem',
                                    )
                                }}</Label>
                                <SearchableSelect
                                    v-model="form.tracked_item_uuid"
                                    :options="[
                                        {
                                            value: NONE_VALUE,
                                            label: t(
                                                'transactions.recurring.form.placeholders.none',
                                            ),
                                        },
                                        ...trackedItemOptions,
                                    ]"
                                    :placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.selectTrackedItem',
                                        )
                                    "
                                    :search-placeholder="
                                        t(
                                            'transactions.recurring.form.placeholders.searchTrackedItem',
                                        )
                                    "
                                    :empty-label="
                                        t(
                                            'transactions.recurring.form.placeholders.noSearchResults',
                                        )
                                    "
                                    :disabled="structuralLocked"
                                    clearable
                                    :clear-value="NONE_VALUE"
                                    creatable
                                    :creating="creatingTrackedItem"
                                    :create-label="
                                        t(
                                            'transactions.recurring.form.actions.createTrackedItem',
                                        )
                                    "
                                    :teleport="false"
                                    :trigger-class="
                                        cn(
                                            'h-11 rounded-2xl',
                                            fieldErrorClass(
                                                hasFormError(
                                                    'tracked_item_uuid',
                                                    'tracked_item_id',
                                                ),
                                            ),
                                        )
                                    "
                                    content-class="z-[260]"
                                    @create-option="
                                        createTrackedItemFromContext
                                    "
                                />
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.form.helper.trackedItem',
                                        )
                                    }}
                                </p>
                                <InputError
                                    :message="
                                        formError(
                                            'tracked_item_uuid',
                                            'tracked_item_id',
                                        )
                                    "
                                />
                            </div>
                        </section>

                        <section class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="recurring-start-date">{{
                                    t(
                                        'transactions.recurring.form.labels.startDate',
                                    )
                                }}</Label>
                                <div
                                    :class="
                                        cn(
                                            'flex items-center gap-2 rounded-2xl border bg-white px-3 dark:bg-slate-950/70',
                                            fieldErrorClass(
                                                hasFieldError(
                                                    form.errors.start_date,
                                                ),
                                            ),
                                        )
                                    "
                                >
                                    <Input
                                        id="recurring-start-date"
                                        ref="startDateInput"
                                        v-model="form.start_date"
                                        type="date"
                                        :disabled="structuralLocked"
                                        class="h-11 border-0 px-0 shadow-none focus-visible:ring-0 dark:border-0"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-9 rounded-full text-slate-500"
                                        :disabled="structuralLocked"
                                        :aria-label="
                                            t(
                                                'transactions.recurring.form.actions.openDatePicker',
                                            )
                                        "
                                        @click="openDatePicker('start')"
                                    >
                                        <Calendar class="size-4" />
                                    </Button>
                                </div>
                                <InputError :message="form.errors.start_date" />
                            </div>

                            <div class="space-y-2">
                                <p
                                    class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.form.labels.postingMode',
                                        )
                                    }}
                                </p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        class="rounded-[22px] border px-4 py-3 text-left transition-all"
                                        :class="
                                            form.auto_create_transaction
                                                ? 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                                                : 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-100'
                                        "
                                        @click="
                                            form.auto_create_transaction = false
                                        "
                                    >
                                        <p class="text-sm font-semibold">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.manualPosting',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.helper.postingManual',
                                                )
                                            }}
                                        </p>
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-[22px] border px-4 py-3 text-left transition-all"
                                        :class="
                                            form.auto_create_transaction
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100'
                                                : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                                        "
                                        @click="
                                            form.auto_create_transaction = true
                                        "
                                    >
                                        <p class="text-sm font-semibold">
                                            {{
                                                t(
                                                    'transactions.recurring.labels.autoPosting',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.helper.postingAutomatic',
                                                )
                                            }}
                                        </p>
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section
                            class="space-y-4 rounded-[28px] border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40"
                        >
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        selectedPlanType === 'installment'
                                            ? t(
                                                  'transactions.recurring.form.sections.installmentCadence',
                                              )
                                            : t(
                                                  'transactions.recurring.form.sections.repeat',
                                              )
                                    }}
                                </p>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        selectedPlanType === 'installment'
                                            ? t(
                                                  'transactions.recurring.form.helper.installmentCadence',
                                              )
                                            : t(
                                                  'transactions.recurring.form.helper.repeat',
                                              )
                                    }}
                                </p>
                            </div>

                            <div
                                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
                            >
                                <button
                                    v-for="option in repeatPresetOptions"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-[20px] border px-4 py-3 text-left transition-all"
                                    :class="
                                        repeatPreset === option.value
                                            ? 'border-sky-300 bg-white text-sky-950 shadow-sm dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-50'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                                    "
                                    :disabled="structuralLocked"
                                    @click="setRepeatPreset(option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>

                            <div
                                v-if="repeatPreset === 'custom'"
                                class="grid gap-5 rounded-[24px] border border-slate-200/80 bg-white p-4 md:grid-cols-2 dark:border-slate-800 dark:bg-slate-950/50"
                            >
                                <div class="grid gap-2">
                                    <Label>{{
                                        t(
                                            'transactions.recurring.form.labels.recurrenceInterval',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="customRecurrenceInterval"
                                        type="number"
                                        min="1"
                                        :disabled="structuralLocked"
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label>{{
                                        t(
                                            'transactions.recurring.form.labels.customUnit',
                                        )
                                    }}</Label>
                                    <Select
                                        :model-value="customRecurrenceType"
                                        :disabled="structuralLocked"
                                        @update:model-value="
                                            updateCustomRecurrenceType(
                                                String($event),
                                            )
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in customRecurrenceTypeOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div
                                    v-if="customRecurrenceType === 'weekly'"
                                    class="grid gap-3 md:col-span-2"
                                >
                                    <Label>{{
                                        t(
                                            'transactions.recurring.form.labels.weekdays',
                                        )
                                    }}</Label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="weekday in weekdayOptions"
                                            :key="weekday"
                                            type="button"
                                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                            :class="
                                                weeklyWeekdays.includes(weekday)
                                                    ? 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200'
                                                    : 'border-slate-200 bg-white text-slate-600 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-300'
                                            "
                                            :disabled="structuralLocked"
                                            @click="toggleWeekday(weekday)"
                                        >
                                            {{
                                                t(
                                                    `transactions.recurring.form.weekdays.${weekday}`,
                                                )
                                            }}
                                        </button>
                                    </div>
                                </div>

                                <template
                                    v-if="
                                        ['monthly', 'quarterly'].includes(
                                            customRecurrenceType,
                                        )
                                    "
                                >
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t(
                                                'transactions.recurring.form.labels.monthlyMode',
                                            )
                                        }}</Label>
                                        <Select
                                            :model-value="monthlyMode"
                                            :disabled="structuralLocked"
                                            @update:model-value="
                                                updateMonthlyMode(
                                                    String($event),
                                                )
                                            "
                                        >
                                            <SelectTrigger
                                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    value="day_of_month"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.monthlyModes.day_of_month',
                                                        )
                                                    }}
                                                </SelectItem>
                                                <SelectItem
                                                    value="ordinal_weekday"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.monthlyModes.ordinal_weekday',
                                                        )
                                                    }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div
                                        v-if="monthlyMode === 'day_of_month'"
                                        class="grid gap-2"
                                    >
                                        <Label>{{
                                            t(
                                                'transactions.recurring.form.labels.dayOfMonth',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="monthlyDay"
                                            type="number"
                                            min="1"
                                            max="31"
                                            :disabled="structuralLocked"
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        />
                                    </div>

                                    <template v-else>
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t(
                                                    'transactions.recurring.form.labels.ordinal',
                                                )
                                            }}</Label>
                                            <Select
                                                :model-value="ordinal"
                                                :disabled="structuralLocked"
                                                @update:model-value="
                                                    updateOrdinal(
                                                        String($event),
                                                    )
                                                "
                                            >
                                                <SelectTrigger
                                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="option in ordinalOptions"
                                                        :key="option"
                                                        :value="option"
                                                    >
                                                        {{
                                                            t(
                                                                `transactions.recurring.form.ordinals.${option}`,
                                                            )
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t(
                                                    'transactions.recurring.form.labels.weekday',
                                                )
                                            }}</Label>
                                            <Select
                                                :model-value="ordinalWeekday"
                                                :disabled="structuralLocked"
                                                @update:model-value="
                                                    updateOrdinalWeekday(
                                                        String($event),
                                                    )
                                                "
                                            >
                                                <SelectTrigger
                                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="weekday in weekdayOptions"
                                                        :key="weekday"
                                                        :value="weekday"
                                                    >
                                                        {{
                                                            t(
                                                                `transactions.recurring.form.weekdays.${weekday}`,
                                                            )
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </template>
                                </template>

                                <template
                                    v-if="customRecurrenceType === 'yearly'"
                                >
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t(
                                                'transactions.recurring.form.labels.yearlyMode',
                                            )
                                        }}</Label>
                                        <Select
                                            :model-value="yearlyMode"
                                            :disabled="structuralLocked"
                                            @update:model-value="
                                                updateYearlyMode(String($event))
                                            "
                                        >
                                            <SelectTrigger
                                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="month_day">
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.yearlyModes.month_day',
                                                        )
                                                    }}
                                                </SelectItem>
                                                <SelectItem
                                                    value="ordinal_weekday"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.yearlyModes.ordinal_weekday',
                                                        )
                                                    }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>{{
                                            t(
                                                'transactions.recurring.form.labels.month',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="yearlyMonth"
                                            type="number"
                                            min="1"
                                            max="12"
                                            :disabled="structuralLocked"
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        />
                                    </div>

                                    <div
                                        v-if="yearlyMode === 'month_day'"
                                        class="grid gap-2"
                                    >
                                        <Label>{{
                                            t(
                                                'transactions.recurring.form.labels.dayOfMonth',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="yearlyDay"
                                            type="number"
                                            min="1"
                                            max="31"
                                            :disabled="structuralLocked"
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        />
                                    </div>

                                    <template v-else>
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t(
                                                    'transactions.recurring.form.labels.ordinal',
                                                )
                                            }}</Label>
                                            <Select
                                                :model-value="ordinal"
                                                :disabled="structuralLocked"
                                                @update:model-value="
                                                    updateOrdinal(
                                                        String($event),
                                                    )
                                                "
                                            >
                                                <SelectTrigger
                                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="option in ordinalOptions"
                                                        :key="option"
                                                        :value="option"
                                                    >
                                                        {{
                                                            t(
                                                                `transactions.recurring.form.ordinals.${option}`,
                                                            )
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t(
                                                    'transactions.recurring.form.labels.weekday',
                                                )
                                            }}</Label>
                                            <Select
                                                :model-value="ordinalWeekday"
                                                :disabled="structuralLocked"
                                                @update:model-value="
                                                    updateOrdinalWeekday(
                                                        String($event),
                                                    )
                                                "
                                            >
                                                <SelectTrigger
                                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="weekday in weekdayOptions"
                                                        :key="weekday"
                                                        :value="weekday"
                                                    >
                                                        {{
                                                            t(
                                                                `transactions.recurring.form.weekdays.${weekday}`,
                                                            )
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </template>
                                </template>
                            </div>

                            <template v-if="selectedPlanType === 'recurring'">
                                <div
                                    class="space-y-3 border-t border-slate-200/80 pt-4 dark:border-slate-800"
                                >
                                    <p
                                        class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            t(
                                                'transactions.recurring.form.labels.endMode',
                                            )
                                        }}
                                    </p>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <button
                                            type="button"
                                            class="rounded-[20px] border px-4 py-3 text-left transition-all"
                                            :class="
                                                form.end_mode === 'never'
                                                    ? 'border-sky-300 bg-white text-sky-950 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-50'
                                                    : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                                            "
                                            :disabled="structuralLocked"
                                            @click="setEndMode('never')"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.endModes.never',
                                                )
                                            }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-[20px] border px-4 py-3 text-left transition-all"
                                            :class="
                                                form.end_mode ===
                                                'after_occurrences'
                                                    ? 'border-sky-300 bg-white text-sky-950 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-50'
                                                    : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                                            "
                                            :disabled="structuralLocked"
                                            @click="
                                                setEndMode('after_occurrences')
                                            "
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.endModes.after_occurrences',
                                                )
                                            }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-[20px] border px-4 py-3 text-left transition-all"
                                            :class="
                                                form.end_mode === 'until_date'
                                                    ? 'border-sky-300 bg-white text-sky-950 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-50'
                                                    : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                                            "
                                            :disabled="structuralLocked"
                                            @click="setEndMode('until_date')"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.endModes.until_date',
                                                )
                                            }}
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="form.end_mode === 'after_occurrences'"
                                    class="grid gap-2 md:max-w-sm"
                                >
                                    <Label for="occurrences-limit">{{
                                        t(
                                            'transactions.recurring.form.labels.repetitionsCount',
                                        )
                                    }}</Label>
                                    <Select
                                        :model-value="form.occurrences_limit"
                                        :disabled="structuralLocked"
                                        @update:model-value="
                                            form.occurrences_limit =
                                                String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            :class="
                                                cn(
                                                    'h-11 rounded-2xl',
                                                    fieldErrorClass(
                                                        hasFieldError(
                                                            form.errors
                                                                .occurrences_limit,
                                                        ),
                                                    ),
                                                )
                                            "
                                            id="occurrences-limit"
                                        >
                                            <SelectValue
                                                :placeholder="
                                                    t(
                                                        'transactions.recurring.form.placeholders.selectRepetitionsCount',
                                                    )
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in repetitionLimitOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.occurrences_limit"
                                    />
                                </div>

                                <div
                                    v-if="form.end_mode === 'until_date'"
                                    class="grid gap-2 md:max-w-sm"
                                >
                                    <Label for="recurring-end-date">{{
                                        t(
                                            'transactions.recurring.form.labels.endDate',
                                        )
                                    }}</Label>
                                    <div
                                        :class="
                                            cn(
                                                'flex items-center gap-2 rounded-2xl border bg-white px-3 dark:bg-slate-950/70',
                                                fieldErrorClass(
                                                    hasFieldError(
                                                        form.errors.end_date,
                                                    ),
                                                ),
                                            )
                                        "
                                    >
                                        <Input
                                            id="recurring-end-date"
                                            ref="endDateInput"
                                            v-model="form.end_date"
                                            type="date"
                                            :min="form.start_date || undefined"
                                            :disabled="structuralLocked"
                                            class="h-11 border-0 px-0 shadow-none focus-visible:ring-0 dark:border-0"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-9 rounded-full text-slate-500"
                                            :disabled="structuralLocked"
                                            :aria-label="
                                                t(
                                                    'transactions.recurring.form.actions.openDatePicker',
                                                )
                                            "
                                            @click="openDatePicker('end')"
                                        >
                                            <Calendar class="size-4" />
                                        </Button>
                                    </div>
                                    <InputError
                                        :message="form.errors.end_date"
                                    />
                                </div>
                            </template>

                            <div
                                v-else
                                class="rounded-[22px] border border-emerald-200/80 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-100"
                            >
                                {{
                                    t(
                                        'transactions.recurring.form.helper.installmentEnd',
                                    )
                                }}
                            </div>

                            <InputError
                                :message="recurrenceConfigurationError"
                            />
                        </section>

                        <section class="grid gap-2">
                            <Label for="recurring-notes">{{
                                t('transactions.recurring.form.labels.notes')
                            }}</Label>
                            <textarea
                                id="recurring-notes"
                                v-model="form.notes"
                                rows="4"
                                :placeholder="
                                    t(
                                        'transactions.recurring.form.placeholders.notes',
                                    )
                                "
                                class="min-h-28 rounded-2xl border border-slate-200 bg-transparent px-3 py-3 text-sm shadow-xs transition-colors outline-none placeholder:text-slate-400 focus:border-slate-400 dark:border-slate-800 dark:placeholder:text-slate-500"
                            />
                            <InputError :message="form.errors.notes" />
                        </section>

                        <Collapsible
                            v-model:open="advancedOpen"
                            class="rounded-[28px] border border-slate-200/80 bg-white/80 dark:border-slate-800 dark:bg-slate-950/30"
                        >
                            <CollapsibleTrigger as-child>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between px-4 py-4 text-left"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.sections.advanced',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.form.helper.advanced',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-slate-500 dark:text-slate-400"
                                    >
                                        {{ advancedOpen ? '-' : '+' }}
                                    </span>
                                </button>
                            </CollapsibleTrigger>

                            <CollapsibleContent
                                class="border-t border-slate-200/80 px-4 py-4 dark:border-slate-800"
                            >
                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t(
                                                'transactions.recurring.form.labels.scope',
                                            )
                                        }}</Label>
                                        <SearchableSelect
                                            v-model="form.scope_uuid"
                                            :options="[
                                                {
                                                    value: NONE_VALUE,
                                                    label: t(
                                                        'transactions.recurring.form.placeholders.none',
                                                    ),
                                                },
                                                ...scopeOptions,
                                            ]"
                                            :placeholder="
                                                t(
                                                    'transactions.recurring.form.placeholders.selectScope',
                                                )
                                            "
                                            :search-placeholder="
                                                t(
                                                    'transactions.recurring.form.placeholders.searchScope',
                                                )
                                            "
                                            :empty-label="
                                                t(
                                                    'transactions.recurring.form.placeholders.noSearchResults',
                                                )
                                            "
                                            :clear-value="NONE_VALUE"
                                            clearable
                                            :teleport="false"
                                            trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                            content-class="z-[260]"
                                        />
                                        <InputError
                                            :message="
                                                formError(
                                                    'scope_uuid',
                                                    'scope_id',
                                                )
                                            "
                                        />
                                    </div>

                                    <div class="grid gap-4 self-end">
                                        <label
                                            class="flex items-start gap-3 rounded-2xl border border-slate-200/80 px-4 py-3 dark:border-slate-800"
                                        >
                                            <Checkbox
                                                :checked="
                                                    form.auto_generate_occurrences
                                                "
                                                @update:checked="
                                                    setBooleanField(
                                                        'auto_generate_occurrences',
                                                        $event,
                                                    )
                                                "
                                            />
                                            <span class="space-y-1">
                                                <span
                                                    class="block text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.labels.autoGenerateOccurrences',
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="block text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.helper.autoGenerateOccurrences',
                                                        )
                                                    }}
                                                </span>
                                            </span>
                                        </label>

                                        <label
                                            class="flex items-start gap-3 rounded-2xl border border-slate-200/80 px-4 py-3 dark:border-slate-800"
                                        >
                                            <Checkbox
                                                :checked="form.is_active"
                                                @update:checked="
                                                    setBooleanField(
                                                        'is_active',
                                                        $event,
                                                    )
                                                "
                                            />
                                            <span class="space-y-1">
                                                <span
                                                    class="block text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.labels.isActive',
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="block text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.recurring.form.helper.isActive',
                                                        )
                                                    }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </form>
                </div>

                <div
                    class="border-t border-slate-200/80 px-6 py-4 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="closeSheet"
                        >
                            {{ t('app.common.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            class="rounded-2xl"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            {{
                                isEditing
                                    ? t(
                                          'transactions.recurring.form.actions.save',
                                      )
                                    : t(
                                          'transactions.recurring.form.actions.create',
                                      )
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
