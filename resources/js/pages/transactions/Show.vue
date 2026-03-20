<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    Filter,
    Lock,
    Pencil,
    Plus,
    Receipt,
    RotateCcw,
    Search,
    Trash2,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import TransactionFormSheet from '@/components/transactions/TransactionFormSheet.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { show as transactionsRoute } from '@/routes/transactions';
import type {
    BreadcrumbItem,
    MonthlyTransactionSheetData,
    MonthlyTransactionSheetOverviewItem,
    MonthlyTransactionSheetPageProps,
    MonthlyTransactionSheetSummaryCard,
    MonthlyTransactionSheetTrackedItemOption,
    MonthlyTransactionSheetTransaction,
} from '@/types';

type SummaryMetricCard = {
    key: string;
    label: string;
    value: number | null;
    tone: string;
    icon: typeof TrendingUp;
    helper: string;
};

type OverviewGroupView = MonthlyTransactionSheetOverviewItem & {
    isHighlighted: boolean;
    isDimmed: boolean;
};

type PendingMutation =
    | { type: 'create' }
    | { type: 'update'; transactionUuid: string }
    | { type: 'delete'; transactionUuid: string };

type RowFeedbackState = {
    transactionUuid: string;
    type: 'create' | 'update';
};

const props = defineProps<MonthlyTransactionSheetPageProps>();
const page = usePage();
const inlineDateInput = ref<HTMLInputElement | null>(null);
const transferTypeKey = 'transfer';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Transazioni',
        href: transactionsRoute({ year: props.year, month: props.month }),
    },
];

const sheet = ref<MonthlyTransactionSheetData>(props.monthlySheet);
const selectedMacrogroup = ref('all');
const selectedCategory = ref('all');
const selectedAccount = ref('all');
const searchQuery = ref('');
const formOpen = ref(false);
const editingTransaction = ref<MonthlyTransactionSheetTransaction | null>(null);
const editingInlineUuid = ref<string | null>(null);
const deletingTransaction = ref<MonthlyTransactionSheetTransaction | null>(
    null,
);
const creatingInlineTrackedItem = ref(false);
const creatingEditTrackedItem = ref(false);
const pendingMutation = ref<PendingMutation | null>(null);
const rowFeedback = ref<RowFeedbackState | null>(null);
const removingTransactionUuid = ref<string | null>(null);
let rowFeedbackTimeout: ReturnType<typeof setTimeout> | null = null;

const inlineForm = useForm({
    transaction_day: '',
    type_key: '',
    category_uuid: '',
    destination_account_uuid: '',
    amount: '',
    description: '',
    account_uuid: '',
    tracked_item_uuid: '',
});

const editForm = useForm({
    transaction_day: '',
    type_key: '',
    category_uuid: '',
    destination_account_uuid: '',
    amount: '',
    description: '',
    account_uuid: '',
    tracked_item_uuid: '',
});

watch(
    () => props.monthlySheet,
    (value, previousValue) => {
        sheet.value = value;
        editingInlineUuid.value = null;
        resetFilters();
        resetInlineEntry();

        if (!previousValue || pendingMutation.value === null) {
            return;
        }

        if (pendingMutation.value.type === 'create') {
            const previousIds = new Set(
                previousValue.transactions.map((transaction) => transaction.uuid),
            );
            const createdTransaction = value.transactions.find(
                (transaction) => !previousIds.has(transaction.uuid),
            );

            if (createdTransaction) {
                triggerRowFeedback(createdTransaction.uuid, 'create');
            }
        }

        if (pendingMutation.value.type === 'update') {
            const transactionUuid = pendingMutation.value.transactionUuid;
            const updatedTransaction = value.transactions.find(
                (transaction) => transaction.uuid === transactionUuid,
            );

            if (updatedTransaction) {
                triggerRowFeedback(updatedTransaction.uuid, 'update');
            }
        }

        if (pendingMutation.value.type === 'delete') {
            removingTransactionUuid.value = null;
        }

        pendingMutation.value = null;
    },
);

const currency = computed(() => sheet.value.settings.base_currency || 'EUR');
const yearValue = computed(() => String(sheet.value.filters.year));
const monthValue = computed(() => String(sheet.value.filters.month));
const canEdit = computed(() => sheet.value.editor.can_edit);
const macrogroupFilterOptions = computed(() => [
    { value: 'all', label: 'Tutti i gruppi' },
    ...sheet.value.filters.group_options,
]);
const headerMacrogroupLabel = computed(() => {
    if (selectedMacrogroup.value === 'all') {
        return 'Macrogruppo';
    }

    return (
        macrogroupFilterOptions.value.find(
            (option) => option.value === selectedMacrogroup.value,
        )?.label ?? 'Macrogruppo'
    );
});
const categoryFilterOptions = computed(() => [
    { value: 'all', label: 'Tutte le categorie' },
    ...sheet.value.filters.category_options,
]);
const accountFilterOptions = computed(() => [
    { value: 'all', label: 'Tutti i conti' },
    ...sheet.value.filters.account_options,
]);
const trackedItemOptions = computed(() => sheet.value.editor.tracked_items);
const isInlineTransfer = computed(
    () => inlineForm.type_key === transferTypeKey,
);
const isEditTransfer = computed(() => editForm.type_key === transferTypeKey);
const inlineDestinationAccounts = computed(() =>
    sheet.value.editor.accounts.filter(
        (account) => account.value !== inlineForm.account_uuid,
    ),
);
const editDestinationAccounts = computed(() =>
    sheet.value.editor.accounts.filter(
        (account) => account.value !== editForm.account_uuid,
    ),
);
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const flashSuccess = computed(() => flash.value.success ?? null);

const currentCalendarYear = new Date().getFullYear();
const currentCalendarMonth = new Date().getMonth() + 1;

const isCurrentPeriod = computed(
    () =>
        sheet.value.period.year === currentCalendarYear &&
        sheet.value.period.month === currentCalendarMonth,
);

const periodNotice = computed(() => {
    if (isCurrentPeriod.value) {
        return null;
    }

    return `Stai visualizzando ${sheet.value.period.month_label} ${sheet.value.period.year}, mentre il periodo attuale è ${getMonthLabel(currentCalendarMonth)} ${currentCalendarYear}.`;
});

const hasActiveFilters = computed(
    () =>
        selectedMacrogroup.value !== 'all' ||
        selectedCategory.value !== 'all' ||
        selectedAccount.value !== 'all' ||
        searchQuery.value.trim() !== '',
);

const filteredTransactions = computed(() =>
    sheet.value.transactions.filter((transaction) =>
        matchesFilters(transaction),
    ),
);

const filteredSummary = computed(() => {
    const income = filteredTransactions.value
        .filter((transaction) => transaction.amount_raw > 0)
        .reduce((sum, transaction) => sum + transaction.amount_raw, 0);
    const expenses = filteredTransactions.value
        .filter((transaction) => transaction.amount_raw < 0)
        .reduce(
            (sum, transaction) => sum + Math.abs(transaction.amount_raw),
            0,
        );

    return {
        income,
        expenses,
        net: income - expenses,
        count: filteredTransactions.value.length,
    };
});

const summaryCards = computed<SummaryMetricCard[]>(() => {
    const summaryByKey = Object.fromEntries(
        sheet.value.summary_cards.map((card) => [card.key, card]),
    ) as Record<string, MonthlyTransactionSheetSummaryCard | undefined>;

    return [
        {
            key: 'income',
            label: 'Entrate del mese',
            value:
                summaryByKey.income?.actual_raw ??
                sheet.value.totals.actual_income_raw,
            tone: 'text-emerald-700 dark:text-emerald-300',
            icon: TrendingUp,
            helper: buildBudgetHelper(summaryByKey.income),
        },
        {
            key: 'expense',
            label: 'Uscite del mese',
            value: sheet.value.totals.actual_expense_raw,
            tone: 'text-rose-700 dark:text-rose-300',
            icon: TrendingDown,
            helper: buildBudgetHelper(summaryByKey.expense),
        },
        {
            key: 'net',
            label: 'Saldo netto',
            value: sheet.value.totals.net_actual_raw,
            tone: getAmountTone(sheet.value.totals.net_actual_raw),
            icon: Wallet,
            helper: sheet.value.meta.has_budget_data
                ? `Scostamento ${formatCurrency(
                      sheet.value.totals.net_actual_raw -
                          sheet.value.totals.net_budgeted_raw,
                      currency.value,
                  )}`
                : 'Bilancio effettivo del mese',
        },
        {
            key: 'count',
            label: 'Numero registrazioni',
            value: sheet.value.meta.transactions_count,
            tone: 'text-slate-900 dark:text-slate-100',
            icon: Receipt,
            helper: `${filteredSummary.value.count} righe visibili nel foglio`,
        },
        {
            key: 'balance',
            label: 'Saldo finale mese',
            value: sheet.value.meta.last_balance_raw,
            tone: getAmountTone(sheet.value.meta.last_balance_raw ?? 0),
            icon: Calendar,
            helper: sheet.value.meta.last_recorded_at
                ? `Ultimo movimento ${formatDateLong(sheet.value.meta.last_recorded_at)}`
                : 'Saldo non disponibile',
        },
    ];
});

const inlineDayRange = computed(() =>
    buildMonthDayRange(sheet.value.period.year, sheet.value.period.month),
);

const inlineCategories = computed(() =>
    sheet.value.editor.categories.filter((category) =>
        inlineForm.type_key === ''
            ? true
            : category.type_key === inlineForm.type_key,
    ),
);

const editCategories = computed(() =>
    sheet.value.editor.categories.filter((category) =>
        editForm.type_key === ''
            ? true
            : category.type_key === editForm.type_key,
    ),
);

const inlineTrackedItems = computed(() =>
    filterTrackedItemOptions(
        trackedItemOptions.value,
        inlineForm.type_key,
        inlineForm.category_uuid,
        inlineForm.tracked_item_uuid,
    ),
);

const editTrackedItems = computed(() =>
    filterTrackedItemOptions(
        trackedItemOptions.value,
        editForm.type_key,
        editForm.category_uuid,
        editForm.tracked_item_uuid,
    ),
);

const activeEditorForm = computed(() =>
    editingInlineUuid.value !== null ? editForm : inlineForm,
);

const selectedInlineCategoryOverview = computed(
    () =>
        sheet.value.overview.categories.find(
            (item) => item.uuid === activeEditorForm.value.category_uuid,
        ) ?? null,
);

const selectedInlineGroupKey = computed(
    () =>
        selectedInlineCategoryOverview.value?.group_key ??
        (activeEditorForm.value.type_key !== ''
            ? activeEditorForm.value.type_key
            : null),
);

const overviewGroups = computed<OverviewGroupView[]>(() =>
    sheet.value.overview.groups.map((group) => ({
        ...group,
        isHighlighted: selectedInlineGroupKey.value === group.key,
        isDimmed:
            selectedInlineGroupKey.value !== null &&
            selectedInlineGroupKey.value !== group.key,
    })),
);

const categoryFocus = computed(() => {
    if (selectedInlineCategoryOverview.value) {
        return {
            title: selectedInlineCategoryOverview.value.label,
            subtitle:
                editingInlineUuid.value !== null
                    ? 'Categoria in modifica nel foglio'
                    : 'Categoria selezionata nella riga nuova',
            item: selectedInlineCategoryOverview.value,
        };
    }

    return null;
});

const inlineErrorsList = computed(() =>
    Object.values(inlineForm.errors).filter(
        (message): message is string =>
            typeof message === 'string' && message !== '',
    ),
);

const editErrorsList = computed(() =>
    Object.values(editForm.errors).filter(
        (message): message is string =>
            typeof message === 'string' && message !== '',
    ),
);

function buildBudgetHelper(card?: MonthlyTransactionSheetSummaryCard): string {
    if (!card || card.budgeted_raw === 0) {
        return 'Valore effettivo del mese';
    }

    return `Budget ${formatCurrency(card.budgeted_raw, currency.value)} · Delta ${formatCurrency(card.variance_raw, currency.value)}`;
}

function getMonthLabel(month: number): string {
    const labels = [
        'Gennaio',
        'Febbraio',
        'Marzo',
        'Aprile',
        'Maggio',
        'Giugno',
        'Luglio',
        'Agosto',
        'Settembre',
        'Ottobre',
        'Novembre',
        'Dicembre',
    ];

    return labels[month - 1] ?? 'Mese sconosciuto';
}

function buildMonthDayRange(
    year: number,
    month: number,
): {
    min: number;
    max: number;
} {
    return {
        min: 1,
        max: new Date(year, month, 0).getDate(),
    };
}

function formatDateShort(date: string | null): string {
    if (!date) {
        return 'Data assente';
    }

    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
    }).format(new Date(date));
}

function formatDateLong(date: string | null): string {
    if (!date) {
        return 'Data assente';
    }

    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(date));
}

function extractDayFromDate(date: string | null): string {
    if (!date) {
        return '1';
    }

    return String(new Date(date).getDate());
}

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function filterTrackedItemOptions(
    options: MonthlyTransactionSheetTrackedItemOption[],
    typeKey: string,
    categoryUuid: string,
    selectedValue: string,
): MonthlyTransactionSheetTrackedItemOption[] {
    if (typeKey === '' || typeKey === transferTypeKey) {
        return options.filter((option) => option.value === selectedValue);
    }

    const selectedOption =
        options.find((option) => option.value === selectedValue) ?? null;
    const matchingOptions = options.filter((option) =>
        trackedItemMatchesContext(option, typeKey, categoryUuid),
    );

    if (
        selectedOption &&
        !matchingOptions.some((option) => option.value === selectedOption.value)
    ) {
        return [selectedOption, ...matchingOptions];
    }

    return matchingOptions;
}

function trackedItemMatchesContext(
    option: MonthlyTransactionSheetTrackedItemOption,
    typeKey: string,
    categoryUuid: string,
): boolean {
    if (typeKey === '' || typeKey === transferTypeKey) {
        return false;
    }

    const groupKeys = option.group_keys ?? [];
    const categoryUuids = option.category_uuids ?? [];
    const categoryContextUuids = resolveCategoryContextUuids(categoryUuid);

    if (categoryUuids.length > 0) {
        return categoryContextUuids.some((uuid) => categoryUuids.includes(uuid));
    }

    if (groupKeys.length > 0) {
        return groupKeys.includes(typeKey);
    }

    return false;
}

function resolveCategoryContextUuids(categoryUuid: string): string[] {
    if (categoryUuid === '') {
        return [];
    }

    const category = sheet.value.editor.categories.find(
        (option) => option.value === categoryUuid,
    );

    if (!category) {
        return [categoryUuid];
    }

    return [categoryUuid, ...category.ancestor_uuids];
}

function pushTrackedItemOption(
    option: MonthlyTransactionSheetTrackedItemOption,
): void {
    const alreadyExists = sheet.value.editor.tracked_items.some(
        (item) => item.value === option.value,
    );

    if (alreadyExists) {
        return;
    }

    sheet.value.editor.tracked_items = [
        ...sheet.value.editor.tracked_items,
        option,
    ].sort((first, second) => first.label.localeCompare(second.label, 'it'));
}

async function createTrackedItemFromContext(
    name: string,
    typeKey: string,
    categoryUuid: string,
): Promise<MonthlyTransactionSheetTrackedItemOption> {
    const response = await fetch('/settings/tracked-items', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': readCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            name,
            parent_uuid: null,
            type: null,
            is_active: true,
            settings: {
                transaction_group_keys: typeKey !== '' ? [typeKey] : [],
                transaction_category_uuids:
                    categoryUuid !== '' ? [categoryUuid] : [],
            },
        }),
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => null);
        const firstError = payload?.errors
            ? Object.values(payload.errors)[0]
            : null;

        throw new Error(
            Array.isArray(firstError)
                ? firstError[0]
                : 'Impossibile creare l’elemento da tracciare.',
        );
    }

    const payload = await response.json();

    return payload.item as MonthlyTransactionSheetTrackedItemOption;
}

async function handleCreateInlineTrackedItem(name: string): Promise<void> {
    if (inlineForm.type_key === '' || inlineForm.type_key === transferTypeKey) {
        inlineForm.setError(
            'tracked_item_uuid',
            'Seleziona prima un tipo valido per associare il nuovo elemento.',
        );

        return;
    }

    creatingInlineTrackedItem.value = true;

    try {
        const option = await createTrackedItemFromContext(
            name,
            inlineForm.type_key,
            inlineForm.category_uuid,
        );

        pushTrackedItemOption(option);
        inlineForm.tracked_item_uuid = option.value;
        inlineForm.clearErrors('tracked_item_uuid');
    } catch (error) {
        inlineForm.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : 'Impossibile creare l’elemento da tracciare.',
        );
    } finally {
        creatingInlineTrackedItem.value = false;
    }
}

async function handleCreateEditTrackedItem(name: string): Promise<void> {
    if (editForm.type_key === '' || editForm.type_key === transferTypeKey) {
        editForm.setError(
            'tracked_item_uuid',
            'Seleziona prima un tipo valido per associare il nuovo elemento.',
        );

        return;
    }

    creatingEditTrackedItem.value = true;

    try {
        const option = await createTrackedItemFromContext(
            name,
            editForm.type_key,
            editForm.category_uuid,
        );

        pushTrackedItemOption(option);
        editForm.tracked_item_uuid = option.value;
        editForm.clearErrors('tracked_item_uuid');
    } catch (error) {
        editForm.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : 'Impossibile creare l’elemento da tracciare.',
        );
    } finally {
        creatingEditTrackedItem.value = false;
    }
}

function normalizeAmountDraft(value: string): string {
    return value.replace(/[^\d.,]/g, '').replace(/\s+/g, '');
}

function formatIntegerPartForDisplay(value: string): string {
    if (value === '') {
        return '';
    }

    return new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 0,
    }).format(Number.parseInt(value, 10));
}

function formatAmountDraftProgressive(rawValue: string): string {
    const sanitized = normalizeAmountDraft(rawValue);

    if (sanitized === '') {
        return '';
    }

    const lastCommaIndex = sanitized.lastIndexOf(',');
    const lastDotIndex = sanitized.lastIndexOf('.');
    const lastSeparatorIndex = Math.max(lastCommaIndex, lastDotIndex);
    const hasTrailingSeparator = lastSeparatorIndex === sanitized.length - 1;
    const hasAnySeparator = lastSeparatorIndex !== -1;
    const decimalsLength = hasAnySeparator
        ? sanitized.length - lastSeparatorIndex - 1
        : 0;
    const separatorsCount = (sanitized.match(/[.,]/g) ?? []).length;
    const shouldTreatAsDecimal =
        hasAnySeparator &&
        (hasTrailingSeparator ||
            decimalsLength <= 2 ||
            (separatorsCount > 1 && decimalsLength <= 2));

    if (!shouldTreatAsDecimal) {
        return formatIntegerPartForDisplay(sanitized.replace(/[.,]/g, ''));
    }

    const integerDigits = sanitized
        .slice(0, lastSeparatorIndex)
        .replace(/[.,]/g, '');
    const decimalDigits = sanitized
        .slice(lastSeparatorIndex + 1)
        .replace(/[.,]/g, '')
        .slice(0, 2);
    const formattedInteger = formatIntegerPartForDisplay(integerDigits || '0');

    return `${formattedInteger},${decimalDigits}`;
}

function parseLocalizedAmount(value: string): number | null {
    const sanitized = normalizeAmountDraft(value);

    if (sanitized === '') {
        return null;
    }

    const separators = [...sanitized.matchAll(/[.,]/g)].map(
        (match) => match.index ?? 0,
    );

    if (separators.length === 0) {
        const parsedInteger = Number.parseFloat(sanitized);

        return Number.isFinite(parsedInteger) ? parsedInteger : null;
    }

    const lastSeparatorIndex = separators[separators.length - 1] ?? -1;
    const digitsAfterSeparator = sanitized.length - lastSeparatorIndex - 1;
    const singleSeparator = separators.length === 1;

    if (
        singleSeparator &&
        (digitsAfterSeparator === 3 || digitsAfterSeparator === 0)
    ) {
        const thousandsValue = Number.parseFloat(
            sanitized.replace(/[.,]/g, ''),
        );

        return Number.isFinite(thousandsValue) ? thousandsValue : null;
    }

    const integerPart = sanitized
        .slice(0, lastSeparatorIndex)
        .replace(/[.,]/g, '');
    const decimalPart = sanitized
        .slice(lastSeparatorIndex + 1)
        .replace(/[.,]/g, '');
    const normalized =
        decimalPart === '' ? integerPart : `${integerPart}.${decimalPart}`;
    const parsedValue = Number.parseFloat(normalized);

    return Number.isFinite(parsedValue) ? parsedValue : null;
}

function formatAmountForDisplay(value: number | null): string {
    if (value === null || Number.isNaN(value)) {
        return '';
    }

    return new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function normalizeInlineAmount(): number | null {
    const parsedAmount = parseLocalizedAmount(inlineForm.amount);

    if (parsedAmount === null || parsedAmount <= 0) {
        inlineForm.setError(
            'amount',
            "L'importo deve essere maggiore di zero.",
        );

        return null;
    }

    inlineForm.amount = formatAmountForDisplay(parsedAmount);
    inlineForm.clearErrors('amount');

    return parsedAmount;
}

function normalizeEditAmount(): number | null {
    const parsedAmount = parseLocalizedAmount(editForm.amount);

    if (parsedAmount === null || parsedAmount <= 0) {
        editForm.setError('amount', "L'importo deve essere maggiore di zero.");

        return null;
    }

    editForm.amount = formatAmountForDisplay(parsedAmount);
    editForm.clearErrors('amount');

    return parsedAmount;
}

function handleInlineAmountInput(value: string | number): void {
    inlineForm.amount = formatAmountDraftProgressive(String(value ?? ''));
}

function handleEditAmountInput(value: string | number): void {
    editForm.amount = formatAmountDraftProgressive(String(value ?? ''));
}

function formatPercent(value: number): string {
    return `${new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1,
    }).format(value)}%`;
}

function progressWidth(value: number): string {
    return `${Math.min(value, 100)}%`;
}

function getAmountTone(value: number): string {
    if (Math.abs(value) < 0.01) {
        return 'text-slate-700 dark:text-slate-200';
    }

    return value >= 0
        ? 'text-emerald-700 dark:text-emerald-300'
        : 'text-rose-700 dark:text-rose-300';
}

function groupBadgeTone(groupKey: string | null | undefined): string {
    return (
        {
            income: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            expense:
                'bg-slate-200/80 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            bill: 'bg-cyan-500/12 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
            debt: 'bg-rose-500/12 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            saving: 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            tax: 'bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            investment:
                'bg-indigo-500/12 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
            transfer:
                'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        }[groupKey ?? ''] ??
        'bg-slate-200/80 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
    );
}

function groupPanelTone(groupKey: string | null | undefined): string {
    return (
        {
            income: 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-500/25 dark:bg-emerald-500/8',
            expense:
                'border-slate-200/80 bg-slate-50/80 dark:border-white/10 dark:bg-slate-900/70',
            bill: 'border-cyan-200/80 bg-cyan-50/70 dark:border-cyan-500/25 dark:bg-cyan-500/8',
            debt: 'border-rose-200/80 bg-rose-50/70 dark:border-rose-500/25 dark:bg-rose-500/8',
            saving: 'border-violet-200/80 bg-violet-50/70 dark:border-violet-500/25 dark:bg-violet-500/8',
            tax: 'border-amber-200/80 bg-amber-50/70 dark:border-amber-500/25 dark:bg-amber-500/8',
            investment:
                'border-indigo-200/80 bg-indigo-50/70 dark:border-indigo-500/25 dark:bg-indigo-500/8',
            transfer:
                'border-sky-200/80 bg-sky-50/70 dark:border-sky-500/25 dark:bg-sky-500/8',
        }[groupKey ?? ''] ??
        'border-slate-200/80 bg-slate-50/80 dark:border-white/10 dark:bg-slate-900/70'
    );
}

function groupProgressTone(groupKey: string | null | undefined): string {
    return (
        {
            income: 'bg-emerald-500',
            expense: 'bg-slate-700 dark:bg-slate-300',
            bill: 'bg-cyan-500',
            debt: 'bg-rose-500',
            saving: 'bg-violet-500',
            tax: 'bg-amber-500',
            investment: 'bg-indigo-500',
            transfer: 'bg-sky-500',
        }[groupKey ?? ''] ?? 'bg-slate-700 dark:bg-slate-300'
    );
}

function groupGlowTone(groupKey: string | null | undefined): string {
    return (
        {
            income: 'bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.16),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.14),transparent_52%)]',
            expense:
                'bg-[radial-gradient(circle_at_top_right,rgba(100,116,139,0.14),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(148,163,184,0.10),transparent_52%)]',
            bill: 'bg-[radial-gradient(circle_at_top_right,rgba(6,182,212,0.16),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(6,182,212,0.14),transparent_52%)]',
            debt: 'bg-[radial-gradient(circle_at_top_right,rgba(244,63,94,0.16),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(244,63,94,0.14),transparent_52%)]',
            saving: 'bg-[radial-gradient(circle_at_top_right,rgba(139,92,246,0.16),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(139,92,246,0.14),transparent_52%)]',
            tax: 'bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.16),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.14),transparent_52%)]',
            investment:
                'bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.16),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.14),transparent_52%)]',
            transfer:
                'bg-[radial-gradient(circle_at_top_right,rgba(14,165,233,0.16),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(14,165,233,0.14),transparent_52%)]',
        }[groupKey ?? ''] ??
        'bg-[radial-gradient(circle_at_top_right,rgba(148,163,184,0.12),transparent_52%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(148,163,184,0.08),transparent_52%)]'
    );
}

function fieldClass(errors: Record<string, string>, field: string): string {
    return cn(
        'h-10 rounded-xl bg-white dark:bg-slate-950/60',
        errors[field]
            ? 'border-rose-300 ring-1 ring-rose-200 dark:border-rose-500/40 dark:ring-rose-500/20'
            : 'border-sky-200 dark:border-sky-500/20',
    );
}

function inlineFieldClass(field: string): string {
    return fieldClass(inlineForm.errors, field);
}

function editFieldClass(field: string): string {
    return fieldClass(editForm.errors, field);
}

function resolveDefaultInlineDay(): string {
    if (
        sheet.value.period.year === currentCalendarYear &&
        sheet.value.period.month === currentCalendarMonth
    ) {
        return String(new Date().getDate());
    }

    return '1';
}

function validateInlineDay(): boolean {
    const day = Number(inlineForm.transaction_day);

    if (
        !Number.isInteger(day) ||
        day < inlineDayRange.value.min ||
        day > inlineDayRange.value.max
    ) {
        inlineForm.setError(
            'transaction_day',
            `Il giorno deve restare tra ${inlineDayRange.value.min} e ${inlineDayRange.value.max}.`,
        );

        return false;
    }

    inlineForm.clearErrors('transaction_day');

    return true;
}

function validateInlineTransfer(): boolean {
    if (!isInlineTransfer.value) {
        inlineForm.clearErrors('destination_account_uuid');

        return true;
    }

    if (inlineForm.destination_account_uuid === '') {
        inlineForm.setError(
            'destination_account_uuid',
            'Seleziona il conto di destinazione.',
        );

        return false;
    }

    if (inlineForm.destination_account_uuid === inlineForm.account_uuid) {
        inlineForm.setError(
            'destination_account_uuid',
            'Il conto di destinazione deve essere diverso dal conto sorgente.',
        );

        return false;
    }

    inlineForm.clearErrors('destination_account_uuid');

    return true;
}

function validateEditDay(): boolean {
    const day = Number(editForm.transaction_day);

    if (
        !Number.isInteger(day) ||
        day < inlineDayRange.value.min ||
        day > inlineDayRange.value.max
    ) {
        editForm.setError(
            'transaction_day',
            `Il giorno deve restare tra ${inlineDayRange.value.min} e ${inlineDayRange.value.max}.`,
        );

        return false;
    }

    editForm.clearErrors('transaction_day');

    return true;
}

function validateEditTransfer(): boolean {
    if (!isEditTransfer.value) {
        editForm.clearErrors('destination_account_uuid');

        return true;
    }

    if (editForm.destination_account_uuid === '') {
        editForm.setError(
            'destination_account_uuid',
            'Seleziona il conto di destinazione.',
        );

        return false;
    }

    if (editForm.destination_account_uuid === editForm.account_uuid) {
        editForm.setError(
            'destination_account_uuid',
            'Il conto di destinazione deve essere diverso dal conto sorgente.',
        );

        return false;
    }

    editForm.clearErrors('destination_account_uuid');

    return true;
}

function resetInlineEntry(): void {
    const defaults = {
        transaction_day: resolveDefaultInlineDay(),
        type_key: '',
        category_uuid: '',
        destination_account_uuid: '',
        amount: '',
        description: '',
        account_uuid: sheet.value.editor.accounts[0]?.value ?? '',
        tracked_item_uuid: '',
    };

    inlineForm.defaults(defaults);
    inlineForm.reset();
    inlineForm.clearErrors();
}

function focusInlineRow(): void {
    nextTick(() => {
        inlineDateInput.value?.focus();
    });
}

function triggerRowFeedback(
    transactionUuid: string,
    type: 'create' | 'update',
): void {
    if (rowFeedbackTimeout) {
        clearTimeout(rowFeedbackTimeout);
        rowFeedbackTimeout = null;
    }

    rowFeedback.value = {
        transactionUuid,
        type,
    };

    rowFeedbackTimeout = setTimeout(() => {
        if (rowFeedback.value?.transactionUuid === transactionUuid) {
            rowFeedback.value = null;
        }

        rowFeedbackTimeout = null;
    }, 2200);
}

function transactionFeedbackClass(transactionUuid: string): string {
    if (removingTransactionUuid.value === transactionUuid) {
        return 'bg-rose-50/80 opacity-0 transition-all duration-500 dark:bg-rose-500/8';
    }

    if (rowFeedback.value?.transactionUuid !== transactionUuid) {
        return '';
    }

    return rowFeedback.value.type === 'create'
        ? 'bg-emerald-50/85 ring-1 ring-emerald-200 transition-all duration-700 dark:bg-emerald-500/10 dark:ring-emerald-500/25'
        : 'bg-sky-50/85 ring-1 ring-sky-200 transition-all duration-700 dark:bg-sky-500/10 dark:ring-sky-500/25';
}

function handleYearSelection(value: unknown): void {
    const year = Number(value);

    if (!Number.isInteger(year)) {
        return;
    }

    router.get(
        transactionsRoute.url({
            year,
            month: sheet.value.filters.month,
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function handleMonthSelection(value: unknown): void {
    const month = Number(value);

    if (!Number.isInteger(month) || month < 1 || month > 12) {
        return;
    }

    router.get(
        transactionsRoute.url({
            year: sheet.value.filters.year,
            month,
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function resetFilters(): void {
    selectedMacrogroup.value = 'all';
    selectedCategory.value = 'all';
    selectedAccount.value = 'all';
    searchQuery.value = '';
}

function openCreate(): void {
    if (!canEdit.value) {
        return;
    }

    if (
        typeof window !== 'undefined' &&
        window.matchMedia('(min-width: 1280px)').matches
    ) {
        editingInlineUuid.value = null;
        resetInlineEntry();
        focusInlineRow();

        return;
    }

    editingTransaction.value = null;
    formOpen.value = true;
}

function openEdit(transaction: MonthlyTransactionSheetTransaction): void {
    if (!canEdit.value) {
        return;
    }

    editingTransaction.value = transaction;
    formOpen.value = true;
}

function startInlineEdit(
    transaction: MonthlyTransactionSheetTransaction,
): void {
    if (!canEdit.value) {
        return;
    }

    editingInlineUuid.value = transaction.uuid;
    editForm.defaults({
        transaction_day: extractDayFromDate(transaction.date),
        type_key: transaction.type_key ?? '',
        category_uuid: transaction.is_transfer
            ? ''
            : transaction.category_uuid
              ? String(transaction.category_uuid)
              : '',
        destination_account_uuid: transaction.related_account_uuid
            ? String(transaction.related_account_uuid)
            : '',
        amount: formatAmountForDisplay(transaction.amount_value_raw ?? null),
        description: transaction.description ?? '',
        account_uuid: transaction.account_uuid
            ? String(transaction.account_uuid)
            : '',
        tracked_item_uuid: transaction.is_transfer
            ? ''
            : transaction.tracked_item_uuid
              ? String(transaction.tracked_item_uuid)
              : '',
    });
    editForm.reset();
    editForm.clearErrors();
}

function cancelInlineEdit(): void {
    editingInlineUuid.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function requestDelete(transaction: MonthlyTransactionSheetTransaction): void {
    if (!canEdit.value) {
        return;
    }

    deletingTransaction.value = transaction;
}

function confirmDelete(): void {
    if (!deletingTransaction.value) {
        return;
    }

    const transactionUuid = deletingTransaction.value.uuid;
    removingTransactionUuid.value = transactionUuid;
    pendingMutation.value = {
        type: 'delete',
        transactionUuid,
    };
    deletingTransaction.value = null;

    window.setTimeout(() => {
        router.delete(
            `/transactions/${props.year}/${props.month}/${transactionUuid}`,
            {
                preserveScroll: true,
                onError: () => {
                    removingTransactionUuid.value = null;
                    pendingMutation.value = null;
                },
            },
        );
    }, 220);
}

function submitInlineTransaction(): void {
    if (!canEdit.value) {
        return;
    }

    if (!validateInlineDay() || !validateInlineTransfer()) {
        return;
    }

    const normalizedAmount = normalizeInlineAmount();

    if (normalizedAmount === null) {
        return;
    }

    const payload = {
        transaction_day: Number(inlineForm.transaction_day),
        type_key: inlineForm.type_key,
        category_uuid: inlineForm.category_uuid || null,
        destination_account_uuid: inlineForm.destination_account_uuid || null,
        amount: normalizedAmount,
        description: inlineForm.description.trim() || null,
        account_uuid: inlineForm.account_uuid,
        tracked_item_uuid: inlineForm.tracked_item_uuid || null,
    };

    const preservedDay = inlineForm.transaction_day;
    const preservedAccount = inlineForm.account_uuid;

    inlineForm
        .transform(() => payload)
        .post(`/transactions/${props.year}/${props.month}`, {
            preserveScroll: true,
            onSuccess: () => {
                pendingMutation.value = { type: 'create' };
                inlineForm.defaults({
                    transaction_day: preservedDay || resolveDefaultInlineDay(),
                    type_key: '',
                    category_uuid: '',
                    destination_account_uuid: '',
                    amount: '',
                    description: '',
                    account_uuid:
                        preservedAccount ||
                        sheet.value.editor.accounts[0]?.value ||
                        '',
                    tracked_item_uuid: '',
                });
                inlineForm.reset();
                inlineForm.clearErrors();
                focusInlineRow();
            },
        });
}

function submitInlineEdit(transactionUuid: string): void {
    if (!canEdit.value || !validateEditDay() || !validateEditTransfer()) {
        return;
    }

    const transaction = sheet.value.transactions.find(
        (item) => item.uuid === transactionUuid,
    );

    if (!transaction) {
        return;
    }

    const normalizedAmount = normalizeEditAmount();

    if (normalizedAmount === null) {
        return;
    }

    const payload = {
        transaction_day: Number(editForm.transaction_day),
        type_key: editForm.type_key,
        category_uuid: editForm.category_uuid || null,
        destination_account_uuid: editForm.destination_account_uuid || null,
        amount: normalizedAmount,
        description: editForm.description.trim() || null,
        account_uuid: editForm.account_uuid,
        tracked_item_uuid: editForm.tracked_item_uuid || null,
    };

    editForm
        .transform(() => payload)
        .patch(`/transactions/${props.year}/${props.month}/${transaction.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                pendingMutation.value = {
                    type: 'update',
                    transactionUuid,
                };
                cancelInlineEdit();
            },
        });
}

function matchesFilters(
    transaction: MonthlyTransactionSheetTransaction,
): boolean {
    const query = searchQuery.value.trim().toLowerCase();

    if (
        selectedMacrogroup.value !== 'all' &&
        transaction.type_key !== selectedMacrogroup.value
    ) {
        return false;
    }

    if (
        selectedCategory.value !== 'all' &&
        String(transaction.category_uuid) !== selectedCategory.value
    ) {
        return false;
    }

    if (
        selectedAccount.value !== 'all' &&
        String(transaction.account_uuid) !== selectedAccount.value
    ) {
        return false;
    }

    return (
        query === '' ||
        [
            transaction.type,
            transaction.category_label,
            transaction.category_path,
            transaction.description ?? '',
            transaction.detail ?? '',
            transaction.account_label,
            transaction.related_account_label ?? '',
        ].some((value) => value.toLowerCase().includes(query))
    );
}

watch(
    () => inlineForm.type_key,
    (typeKey) => {
        if (typeKey === transferTypeKey) {
            inlineForm.category_uuid = '';
            inlineForm.tracked_item_uuid = '';
            inlineForm.clearErrors('category_uuid', 'tracked_item_uuid');
        } else {
            inlineForm.destination_account_uuid = '';
            inlineForm.clearErrors('destination_account_uuid');
        }

        if (
            inlineForm.category_uuid !== '' &&
            !inlineCategories.value.some(
                (category) => category.value === inlineForm.category_uuid,
            )
        ) {
            inlineForm.category_uuid = '';
        }

        inlineForm.clearErrors('category_uuid');
    },
);

watch(
    () => [inlineForm.type_key, inlineForm.category_uuid] as const,
    ([typeKey, categoryId], [, previousCategoryId]) => {
        if (inlineForm.tracked_item_uuid === '') {
            return;
        }

        if (
            previousCategoryId === undefined ||
            trackedItemMatchesContext(
                trackedItemOptions.value.find(
                    (option) => option.value === inlineForm.tracked_item_uuid,
                ) ?? { value: '', label: '' },
                typeKey,
                categoryId,
            )
        ) {
            return;
        }

        inlineForm.tracked_item_uuid = '';
        inlineForm.clearErrors('tracked_item_uuid');
    },
);

watch(
    () => editForm.type_key,
    (typeKey) => {
        if (typeKey === transferTypeKey) {
            editForm.category_uuid = '';
            editForm.tracked_item_uuid = '';
            editForm.clearErrors('category_uuid', 'tracked_item_uuid');
        } else {
            editForm.destination_account_uuid = '';
            editForm.clearErrors('destination_account_uuid');
        }

        if (
            editForm.category_uuid !== '' &&
            !editCategories.value.some(
                (category) => category.value === editForm.category_uuid,
            )
        ) {
            editForm.category_uuid = '';
        }

        editForm.clearErrors('category_uuid');
    },
);

watch(
    () => [editForm.type_key, editForm.category_uuid] as const,
    ([typeKey, categoryId], [, previousCategoryId]) => {
        if (editForm.tracked_item_uuid === '') {
            return;
        }

        if (
            previousCategoryId === undefined ||
            trackedItemMatchesContext(
                trackedItemOptions.value.find(
                    (option) => option.value === editForm.tracked_item_uuid,
                ) ?? { value: '', label: '' },
                typeKey,
                categoryId,
            )
        ) {
            return;
        }

        editForm.tracked_item_uuid = '';
        editForm.clearErrors('tracked_item_uuid');
    },
);

watch(
    () => inlineForm.account_uuid,
    () => {
        if (inlineForm.destination_account_uuid === inlineForm.account_uuid) {
            inlineForm.destination_account_uuid = '';
        }
    },
);

watch(
    () => editForm.account_uuid,
    () => {
        if (editForm.destination_account_uuid === editForm.account_uuid) {
            editForm.destination_account_uuid = '';
        }
    },
);

resetInlineEntry();
</script>

<template>
    <Head
        :title="`Transazioni ${sheet.period.month_label} ${sheet.period.year}`"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-[28px] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.14),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.10),_transparent_28%),linear-gradient(135deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94))] shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.16),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.12),_transparent_28%),linear-gradient(135deg,rgba(2,6,23,0.95),rgba(15,23,42,0.9))]"
            >
                <div
                    class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:p-7"
                >
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                <Receipt class="mr-1 size-3.5" />
                                Foglio operativo mensile
                            </Badge>
                            <Badge
                                v-if="sheet.meta.has_budget_data"
                                class="rounded-full bg-emerald-500/12 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                            >
                                <Calendar class="mr-1 size-3.5" />
                                Collegato al budget
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                Transazioni {{ sheet.period.month_label }}
                                {{ sheet.period.year }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Foglio gestionale del mese: registrazione rapida
                                inline, controllo budget per gruppo e categoria,
                                modifica veloce delle righe senza uscire dalla
                                pagina.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[520px]">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Anno
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
                                <SelectContent class="z-[170]">
                                    <SelectItem
                                        v-for="option in sheet.filters
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
                                Mese
                            </p>
                            <Select
                                :model-value="monthValue"
                                @update:model-value="handleMonthSelection"
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent class="z-[170]">
                                    <SelectItem
                                        v-for="month in 12"
                                        :key="month"
                                        :value="String(month)"
                                    >
                                        {{ getMonthLabel(month) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Macrogruppo globale
                            </p>
                            <Select
                                :model-value="selectedMacrogroup"
                                @update:model-value="
                                    selectedMacrogroup = String($event)
                                "
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                                >
                                    <span
                                        class="truncate text-sm text-slate-900 dark:text-slate-100"
                                    >
                                        {{ headerMacrogroupLabel }}
                                    </span>
                                </SelectTrigger>
                                <SelectContent class="z-[170]">
                                    <SelectItem
                                        v-for="option in macrogroupFilterOptions"
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
                            class="h-11 rounded-2xl border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70"
                            :disabled="!canEdit"
                            @click="openCreate"
                        >
                            <Lock v-if="!canEdit" class="mr-2 size-4" />
                            <Plus v-else class="mr-2 size-4" />
                            {{ canEdit ? 'Nuova' : 'Anno chiuso' }}
                        </Button>
                    </div>
                </div>
            </section>

            <Alert
                v-if="periodNotice"
                class="border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
            >
                <Calendar class="size-4" />
                <AlertTitle>Periodo non corrente</AlertTitle>
                <AlertDescription>
                    {{ periodNotice }}
                </AlertDescription>
            </Alert>

            <Alert
                v-if="flashSuccess"
                class="border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
            >
                <Receipt class="size-4" />
                <AlertTitle>Operazione completata</AlertTitle>
                <AlertDescription>
                    {{ flashSuccess }}
                </AlertDescription>
            </Alert>

            <Alert
                v-if="sheet.meta.year_is_closed"
                class="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <Lock class="size-4" />
                <AlertTitle>Anno chiuso</AlertTitle>
                <AlertDescription>
                    {{ sheet.meta.closed_year_message }}
                </AlertDescription>
            </Alert>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Card
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="overflow-hidden border-white/70 bg-white/90 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardContent class="space-y-4 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ card.label }}
                                </p>
                                <p
                                    class="text-2xl font-semibold tracking-tight"
                                    :class="card.tone"
                                >
                                    <template v-if="card.key === 'count'">
                                        {{ card.value ?? 0 }}
                                    </template>
                                    <template v-else>
                                        {{
                                            formatCurrency(
                                                card.value ?? 0,
                                                currency,
                                            )
                                        }}
                                    </template>
                                </p>
                            </div>
                            <div
                                class="rounded-2xl bg-slate-100 p-2.5 text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                            >
                                <component :is="card.icon" class="size-4" />
                            </div>
                        </div>
                        <p
                            class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                        >
                            {{ card.helper }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card
                class="overflow-hidden border-white/70 bg-white/90 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
            >
                <CardContent class="p-4 sm:p-5">
                    <div
                        class="grid gap-3 xl:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,0.7fr))_auto]"
                    >
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Ricerca
                            </p>
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Cerca dettaglio, categoria, conto"
                                    class="h-11 rounded-2xl border-slate-200 pl-10 dark:border-white/10"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Tipo / macrogruppo
                            </p>
                            <Select
                                :model-value="selectedMacrogroup"
                                @update:model-value="
                                    selectedMacrogroup = String($event)
                                "
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-slate-200 dark:border-white/10"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent class="z-[170]">
                                    <SelectItem
                                        v-for="option in macrogroupFilterOptions"
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
                                Categoria
                            </p>
                            <SearchableSelect
                                v-model="selectedCategory"
                                :options="categoryFilterOptions"
                                placeholder="Tutte le categorie"
                                search-placeholder="Cerca categoria"
                                clearable
                                clear-value="all"
                                trigger-class="h-11 rounded-2xl border-slate-200 dark:border-white/10"
                            />
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Conto
                            </p>
                            <SearchableSelect
                                v-model="selectedAccount"
                                :options="accountFilterOptions"
                                placeholder="Tutti i conti"
                                search-placeholder="Cerca conto"
                                clearable
                                clear-value="all"
                                trigger-class="h-11 rounded-2xl border-slate-200 dark:border-white/10"
                            />
                        </div>

                        <div class="flex items-end">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 w-full rounded-2xl"
                                :disabled="!hasActiveFilters"
                                @click="resetFilters"
                            >
                                <RotateCcw class="mr-2 size-4" />
                                Reset
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card
                    class="overflow-hidden border-white/70 bg-white/90 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader
                        class="border-b border-slate-200/70 bg-slate-50/70 px-4 py-4 sm:px-5 dark:border-white/10 dark:bg-slate-900/60"
                    >
                        <div
                            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="space-y-1">
                                <CardTitle
                                    class="text-lg text-slate-950 dark:text-white"
                                >
                                    Foglio transazioni
                                </CardTitle>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    {{ filteredSummary.count }} righe visibili
                                    su
                                    {{
                                        sheet.meta.transactions_count
                                    }}
                                    registrazioni del mese.
                                </p>
                                <p
                                    v-if="canEdit"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Desktop: doppio click su una riga per aprire
                                    la modifica. La matita resta solo su mobile.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    variant="outline"
                                    class="rounded-full border-slate-200 bg-white/80 px-3 py-1 text-slate-600 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300"
                                >
                                    <Filter class="mr-1 size-3.5" />
                                    {{
                                        hasActiveFilters
                                            ? 'Filtri attivi'
                                            : 'Vista completa'
                                    }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="rounded-full border-slate-200 bg-white/80 px-3 py-1 text-slate-600 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300"
                                >
                                    {{ sheet.period.month_label }}
                                    {{ sheet.period.year }}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="p-0">
                        <div class="hidden overflow-x-auto xl:block">
                            <table
                                class="w-full min-w-[1140px] border-collapse text-sm"
                            >
                                <thead
                                    class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur dark:bg-slate-900/95"
                                >
                                    <tr
                                        class="border-b border-slate-200 dark:border-white/10"
                                    >
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Data
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Tipo / macrogruppo
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Categoria
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Importo
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Dettaglio
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Elemento da tracciare
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Conto / risorsa
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Azioni
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="transaction in filteredTransactions"
                                        :key="transaction.uuid"
                                    >
                                        <tr
                                            v-if="
                                                editingInlineUuid ===
                                                transaction.uuid
                                            "
                                            class="border-b border-sky-200/80 bg-sky-50/70 align-top dark:border-sky-500/20 dark:bg-sky-500/5"
                                        >
                                            <td class="px-3 py-3">
                                                <div class="space-y-1.5">
                                                    <Input
                                                        v-model="
                                                            editForm.transaction_day
                                                        "
                                                        type="number"
                                                        inputmode="numeric"
                                                        placeholder="GG"
                                                        :min="
                                                            inlineDayRange.min
                                                        "
                                                        :max="
                                                            inlineDayRange.max
                                                        "
                                                        :class="
                                                            cn(
                                                                editFieldClass(
                                                                    'transaction_day',
                                                                ),
                                                                'text-center',
                                                            )
                                                        "
                                                        @keydown.enter.prevent="
                                                            submitInlineEdit(
                                                                transaction.uuid,
                                                            )
                                                        "
                                                    />
                                                    <p
                                                        class="text-center text-[10px] font-medium tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        Giorno
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3">
                                                <Select
                                                    :model-value="
                                                        editForm.type_key
                                                    "
                                                    @update:model-value="
                                                        editForm.type_key =
                                                            String($event)
                                                    "
                                                >
                                                    <SelectTrigger
                                                        :class="
                                                            editFieldClass(
                                                                'type_key',
                                                            )
                                                        "
                                                    >
                                                        <SelectValue
                                                            placeholder="Tipo"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent
                                                        class="z-[170]"
                                                    >
                                                        <SelectItem
                                                            v-for="option in sheet
                                                                .editor
                                                                .group_options"
                                                            :key="option.value"
                                                            :value="
                                                                option.value
                                                            "
                                                        >
                                                            {{ option.label }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                            <td class="px-3 py-3">
                                                <SearchableSelect
                                                    v-if="!isEditTransfer"
                                                    v-model="
                                                        editForm.category_uuid
                                                    "
                                                    :options="editCategories"
                                                    placeholder="Categoria"
                                                    search-placeholder="Cerca categoria"
                                                    :disabled="
                                                        editForm.type_key === ''
                                                    "
                                                    clearable
                                                    :trigger-class="
                                                        editFieldClass(
                                                            'category_uuid',
                                                        )
                                                    "
                                                />
                                                <SearchableSelect
                                                    v-else
                                                    v-model="
                                                        editForm.destination_account_uuid
                                                    "
                                                    :options="
                                                        editDestinationAccounts
                                                    "
                                                    placeholder="Conto destinazione"
                                                    search-placeholder="Cerca conto destinazione"
                                                    clearable
                                                    :trigger-class="
                                                        editFieldClass(
                                                            'destination_account_uuid',
                                                        )
                                                    "
                                                />
                                            </td>
                                            <td class="px-3 py-3">
                                                <Input
                                                    :model-value="
                                                        editForm.amount
                                                    "
                                                    inputmode="decimal"
                                                    placeholder="0,00"
                                                    :class="
                                                        cn(
                                                            editFieldClass(
                                                                'amount',
                                                            ),
                                                            'text-right font-mono',
                                                        )
                                                    "
                                                    @update:model-value="
                                                        handleEditAmountInput
                                                    "
                                                    @blur="normalizeEditAmount"
                                                    @keydown.enter.prevent="
                                                        submitInlineEdit(
                                                            transaction.uuid,
                                                        )
                                                    "
                                                />
                                            </td>
                                            <td class="px-3 py-3">
                                                <Input
                                                    v-model="
                                                        editForm.description
                                                    "
                                                    placeholder="Dettaglio"
                                                    class="h-10 rounded-xl border-sky-200 bg-white dark:border-sky-500/20 dark:bg-slate-950/60"
                                                    @keydown.enter.prevent="
                                                        submitInlineEdit(
                                                            transaction.uuid,
                                                        )
                                                    "
                                                />
                                            </td>
                                            <td class="px-3 py-3">
                                                <SearchableSelect
                                                    v-if="!isEditTransfer"
                                                    v-model="
                                                        editForm.tracked_item_uuid
                                                    "
                                                    :options="[
                                                        {
                                                            value: '',
                                                            label: 'Nessuno',
                                                        },
                                                        ...editTrackedItems,
                                                    ]"
                                                    placeholder="Elemento da tracciare"
                                                    search-placeholder="Cerca elemento da tracciare"
                                                    :disabled="
                                                        editForm.type_key === ''
                                                    "
                                                    clearable
                                                    creatable
                                                    :creating="
                                                        creatingEditTrackedItem
                                                    "
                                                    create-label="Crea elemento"
                                                    :trigger-class="
                                                        editFieldClass(
                                                            'tracked_item_uuid',
                                                        )
                                                    "
                                                    @create-option="
                                                        handleCreateEditTrackedItem
                                                    "
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-10 items-center rounded-xl border border-dashed border-sky-200 px-3 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:text-sky-300"
                                                >
                                                    Giroconto tra conti
                                                </div>
                                            </td>
                                            <td class="px-3 py-3">
                                                <SearchableSelect
                                                    v-model="
                                                        editForm.account_uuid
                                                    "
                                                    :options="
                                                        sheet.editor.accounts
                                                    "
                                                    :placeholder="
                                                        isEditTransfer
                                                            ? 'Conto sorgente'
                                                            : 'Conto'
                                                    "
                                                    :search-placeholder="
                                                        isEditTransfer
                                                            ? 'Cerca conto sorgente'
                                                            : 'Cerca conto'
                                                    "
                                                    clearable
                                                    :trigger-class="
                                                        editFieldClass(
                                                            'account_uuid',
                                                        )
                                                    "
                                                />
                                            </td>
                                            <td class="px-3 py-3">
                                                <div
                                                    class="flex justify-end gap-2"
                                                >
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        class="rounded-xl"
                                                        :disabled="
                                                            editForm.processing
                                                        "
                                                        @click="
                                                            submitInlineEdit(
                                                                transaction.uuid,
                                                            )
                                                        "
                                                    >
                                                        Salva
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        class="rounded-xl"
                                                        :disabled="
                                                            editForm.processing
                                                        "
                                                        @click="
                                                            cancelInlineEdit
                                                        "
                                                    >
                                                        Annulla
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr
                                            v-else
                                            :class="
                                                cn(
                                                    'border-b border-slate-200/70 transition-colors hover:bg-slate-50/80 dark:border-white/8 dark:hover:bg-slate-900/60',
                                                    canEdit
                                                        ? 'cursor-pointer'
                                                        : '',
                                                    transactionFeedbackClass(
                                                        transaction.uuid,
                                                    ),
                                                )
                                            "
                                            @dblclick="
                                                startInlineEdit(transaction)
                                            "
                                        >
                                            <td class="px-4 py-3 align-top">
                                                <div class="space-y-0.5">
                                                    <p
                                                        class="font-medium text-slate-900 dark:text-slate-100"
                                                    >
                                                        {{
                                                            formatDateShort(
                                                                transaction.date,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            transaction.date ??
                                                            'Senza data'
                                                        }}
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <Badge
                                                    :class="
                                                        cn(
                                                            'rounded-full px-2.5 py-1 text-[11px]',
                                                            groupBadgeTone(
                                                                transaction.type_key,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{ transaction.type }}
                                                </Badge>
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <div class="space-y-0.5">
                                                    <p
                                                        class="font-medium text-slate-900 dark:text-slate-100"
                                                    >
                                                        {{
                                                            transaction.category_label
                                                        }}
                                                    </p>
                                                    <p
                                                        class="text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            transaction.is_transfer
                                                                ? transaction.direction ===
                                                                  'income'
                                                                    ? `Da ${transaction.related_account_label ?? 'Conto sorgente'} a ${transaction.account_label}`
                                                                    : `Da ${transaction.account_label} a ${transaction.related_account_label ?? 'Conto destinazione'}`
                                                                : transaction.category_path
                                                        }}
                                                    </p>
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right align-top"
                                            >
                                                <span
                                                    class="font-mono font-semibold"
                                                    :class="
                                                        getAmountTone(
                                                            transaction.amount_raw,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatCurrency(
                                                            transaction.amount_raw,
                                                            currency,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <div
                                                    class="max-w-[220px] space-y-0.5"
                                                >
                                                    <p
                                                        class="truncate text-sm text-slate-800 dark:text-slate-200"
                                                        :title="
                                                            transaction.detail ??
                                                            transaction.description ??
                                                            'Nessun dettaglio'
                                                        "
                                                    >
                                                        {{
                                                            transaction.detail ??
                                                            transaction.description ??
                                                            'Nessun dettaglio'
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="transaction.notes"
                                                        class="truncate text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{ transaction.notes }}
                                                    </p>
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 align-top text-sm text-slate-700 dark:text-slate-300"
                                            >
                                                {{
                                                    transaction.is_transfer
                                                        ? (transaction.related_account_label ??
                                                          '—')
                                                        : (transaction.tracked_item_label ??
                                                          '—')
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3 align-top text-sm text-slate-700 dark:text-slate-300"
                                            >
                                                {{ transaction.account_label }}
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <div class="flex justify-end">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 w-8 rounded-xl p-0 text-rose-600 hover:text-rose-700"
                                                        :disabled="!canEdit"
                                                        @click="
                                                            requestDelete(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-4"
                                                        />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr
                                            v-if="
                                                editingInlineUuid ===
                                                    transaction.uuid &&
                                                editErrorsList.length > 0
                                            "
                                            class="border-b border-slate-200/70 bg-rose-50/80 dark:border-white/10 dark:bg-rose-500/8"
                                        >
                                            <td colspan="8" class="px-4 py-3">
                                                <div
                                                    class="flex flex-wrap gap-2 text-xs text-rose-700 dark:text-rose-300"
                                                >
                                                    <span
                                                        v-for="message in editErrorsList"
                                                        :key="message"
                                                        class="rounded-full bg-rose-100 px-2.5 py-1 dark:bg-rose-500/10"
                                                    >
                                                        {{ message }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <tr
                                        v-if="canEdit"
                                        class="border-t-2 border-sky-200 bg-sky-50/70 align-top dark:border-sky-500/20 dark:bg-sky-500/5"
                                    >
                                        <td class="px-3 py-3">
                                            <div class="space-y-1.5">
                                                <Input
                                                    ref="inlineDateInput"
                                                    v-model="
                                                        inlineForm.transaction_day
                                                    "
                                                    type="number"
                                                    inputmode="numeric"
                                                    placeholder="GG"
                                                    :min="inlineDayRange.min"
                                                    :max="inlineDayRange.max"
                                                    :class="
                                                        cn(
                                                            inlineFieldClass(
                                                                'transaction_day',
                                                            ),
                                                            'text-center',
                                                        )
                                                    "
                                                    @keydown.enter.prevent="
                                                        submitInlineTransaction
                                                    "
                                                />
                                                <p
                                                    class="text-center text-[10px] font-medium tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Giorno
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <Select
                                                :model-value="
                                                    inlineForm.type_key
                                                "
                                                @update:model-value="
                                                    inlineForm.type_key =
                                                        String($event)
                                                "
                                            >
                                                <SelectTrigger
                                                    :class="
                                                        inlineFieldClass(
                                                            'type_key',
                                                        )
                                                    "
                                                >
                                                    <SelectValue
                                                        placeholder="Tipo"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent class="z-[170]">
                                                    <SelectItem
                                                        v-for="option in sheet
                                                            .editor
                                                            .group_options"
                                                        :key="option.value"
                                                        :value="option.value"
                                                    >
                                                        {{ option.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </td>
                                        <td class="px-3 py-3">
                                            <SearchableSelect
                                                v-if="!isInlineTransfer"
                                                v-model="inlineForm.category_uuid"
                                                :options="inlineCategories"
                                                placeholder="Categoria"
                                                search-placeholder="Cerca categoria"
                                                :disabled="
                                                    inlineForm.type_key === ''
                                                "
                                                clearable
                                                :trigger-class="
                                                    inlineFieldClass(
                                                        'category_uuid',
                                                    )
                                                "
                                            />
                                            <SearchableSelect
                                                v-else
                                                v-model="
                                                    inlineForm.destination_account_uuid
                                                "
                                                :options="
                                                    inlineDestinationAccounts
                                                "
                                                placeholder="Conto destinazione"
                                                search-placeholder="Cerca conto destinazione"
                                                clearable
                                                :trigger-class="
                                                    inlineFieldClass(
                                                        'destination_account_uuid',
                                                    )
                                                "
                                            />
                                        </td>
                                        <td class="px-3 py-3">
                                            <Input
                                                :model-value="inlineForm.amount"
                                                inputmode="decimal"
                                                placeholder="0,00"
                                                :class="
                                                    cn(
                                                        inlineFieldClass(
                                                            'amount',
                                                        ),
                                                        'text-right font-mono',
                                                    )
                                                "
                                                @update:model-value="
                                                    handleInlineAmountInput
                                                "
                                                @blur="normalizeInlineAmount"
                                                @keydown.enter.prevent="
                                                    submitInlineTransaction
                                                "
                                            />
                                        </td>
                                        <td class="px-3 py-3">
                                            <Input
                                                v-model="inlineForm.description"
                                                placeholder="Dettaglio"
                                                class="h-10 rounded-xl border-sky-200 bg-white dark:border-sky-500/20 dark:bg-slate-950/60"
                                                @keydown.enter.prevent="
                                                    submitInlineTransaction
                                                "
                                            />
                                        </td>
                                        <td class="px-3 py-3">
                                            <SearchableSelect
                                                v-if="!isInlineTransfer"
                                                v-model="
                                                    inlineForm.tracked_item_uuid
                                                "
                                                :options="[
                                                    {
                                                        value: '',
                                                        label: 'Nessuno',
                                                    },
                                                    ...inlineTrackedItems,
                                                ]"
                                                placeholder="Elemento da tracciare"
                                                search-placeholder="Cerca elemento da tracciare"
                                                :disabled="
                                                    inlineForm.type_key === ''
                                                "
                                                clearable
                                                creatable
                                                :creating="
                                                    creatingInlineTrackedItem
                                                "
                                                create-label="Crea elemento"
                                                :trigger-class="
                                                    inlineFieldClass(
                                                        'tracked_item_uuid',
                                                    )
                                                "
                                                @create-option="
                                                    handleCreateInlineTrackedItem
                                                "
                                            />
                                            <div
                                                v-else
                                                class="flex h-10 items-center rounded-xl border border-dashed border-sky-200 px-3 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:text-sky-300"
                                            >
                                                Giroconto tra conti
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <SearchableSelect
                                                v-model="inlineForm.account_uuid"
                                                :options="sheet.editor.accounts"
                                                :placeholder="
                                                    isInlineTransfer
                                                        ? 'Conto sorgente'
                                                        : 'Conto'
                                                "
                                                :search-placeholder="
                                                    isInlineTransfer
                                                        ? 'Cerca conto sorgente'
                                                        : 'Cerca conto'
                                                "
                                                clearable
                                                :trigger-class="
                                                    inlineFieldClass(
                                                        'account_uuid',
                                                    )
                                                "
                                            />
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    class="rounded-xl"
                                                    :disabled="
                                                        inlineForm.processing
                                                    "
                                                    @click="
                                                        submitInlineTransaction
                                                    "
                                                >
                                                    <Plus class="mr-2 size-4" />
                                                    Salva
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    class="rounded-xl"
                                                    :disabled="
                                                        inlineForm.processing
                                                    "
                                                    @click="resetInlineEntry"
                                                >
                                                    <RotateCcw class="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr
                                        v-if="
                                            canEdit &&
                                            inlineErrorsList.length > 0
                                        "
                                        class="border-b border-slate-200/70 bg-rose-50/80 dark:border-white/10 dark:bg-rose-500/8"
                                    >
                                        <td colspan="8" class="px-4 py-3">
                                            <div
                                                class="flex flex-wrap gap-2 text-xs text-rose-700 dark:text-rose-300"
                                            >
                                                <span
                                                    v-for="message in inlineErrorsList"
                                                    :key="message"
                                                    class="rounded-full bg-rose-100 px-2.5 py-1 dark:bg-rose-500/10"
                                                >
                                                    {{ message }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr
                                        v-if="!canEdit"
                                        class="border-t border-slate-200/70 bg-slate-50/80 dark:border-white/10 dark:bg-slate-900/60"
                                    >
                                        <td
                                            colspan="8"
                                            class="px-4 py-4 text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            Questo mese è in sola lettura perché
                                            l’anno di gestione è chiuso.
                                        </td>
                                    </tr>

                                    <tr
                                        v-if="filteredTransactions.length === 0"
                                    >
                                        <td
                                            colspan="8"
                                            class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400"
                                        >
                                            Nessuna transazione trovata con i
                                            filtri applicati.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-3 p-4 xl:hidden">
                            <Card
                                v-if="canEdit"
                                class="border-sky-200/80 bg-sky-50/70 shadow-none dark:border-sky-500/20 dark:bg-sky-500/5"
                            >
                                <CardContent class="space-y-3 p-4">
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-white"
                                    >
                                        Nuova registrazione
                                    </p>
                                    <Button
                                        type="button"
                                        class="w-full rounded-2xl"
                                        @click="openCreate"
                                    >
                                        <Plus class="mr-2 size-4" />
                                        Apri inserimento
                                    </Button>
                                </CardContent>
                            </Card>

                            <Card
                                v-for="transaction in filteredTransactions"
                                :key="transaction.uuid"
                                :class="
                                    cn(
                                        'border-slate-200/80 bg-white/95 shadow-none transition-all duration-500 dark:border-white/10 dark:bg-slate-950/80',
                                        transactionFeedbackClass(
                                            transaction.uuid,
                                        ),
                                    )
                                "
                            >
                                <CardContent class="space-y-3 p-4">
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="space-y-1">
                                            <Badge
                                                :class="
                                                    cn(
                                                        'rounded-full px-2.5 py-1 text-[11px]',
                                                        groupBadgeTone(
                                                            transaction.type_key,
                                                        ),
                                                    )
                                                "
                                            >
                                                {{ transaction.type }}
                                            </Badge>
                                            <p
                                                class="font-medium text-slate-950 dark:text-slate-100"
                                            >
                                                {{ transaction.category_label }}
                                            </p>
                                            <p
                                                class="truncate text-sm text-slate-600 dark:text-slate-300"
                                                :title="
                                                    transaction.detail ??
                                                    transaction.description ??
                                                    'Nessun dettaglio'
                                                "
                                            >
                                                {{
                                                    transaction.detail ??
                                                    transaction.description ??
                                                    'Nessun dettaglio'
                                                }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p
                                                class="font-mono font-semibold"
                                                :class="
                                                    getAmountTone(
                                                        transaction.amount_raw,
                                                    )
                                                "
                                            >
                                                {{
                                                    formatCurrency(
                                                        transaction.amount_raw,
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    formatDateLong(
                                                        transaction.date,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="grid gap-2 text-xs text-slate-500 sm:grid-cols-2 dark:text-slate-400"
                                    >
                                        <div>
                                            Conto:
                                            <span
                                                class="text-slate-700 dark:text-slate-200"
                                                >{{
                                                    transaction.account_label
                                                }}</span
                                            >
                                        </div>
                                        <div>
                                            {{
                                                transaction.is_transfer
                                                    ? 'Conto collegato:'
                                                    : 'Elemento da tracciare:'
                                            }}
                                            <span
                                                class="text-slate-700 dark:text-slate-200"
                                            >
                                                {{
                                                    transaction.is_transfer
                                                        ? (transaction.related_account_label ??
                                                          '—')
                                                        : (transaction.tracked_item_label ??
                                                          '—')
                                                }}
                                            </span>
                                        </div>
                                        <div>
                                            Saldo:
                                            <span
                                                class="text-slate-700 dark:text-slate-200"
                                                >{{
                                                    transaction.balance_after_raw ===
                                                    null
                                                        ? '—'
                                                        : formatCurrency(
                                                              transaction.balance_after_raw,
                                                              currency,
                                                          )
                                                }}</span
                                            >
                                        </div>
                                    </div>

                                    <div
                                        v-if="canEdit"
                                        class="flex justify-end gap-2"
                                    >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl"
                                            @click="openEdit(transaction)"
                                        >
                                            <Pencil class="mr-2 size-4" />
                                            Modifica
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl text-rose-600 hover:text-rose-700"
                                            @click="requestDelete(transaction)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Elimina
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            <div
                                v-if="filteredTransactions.length === 0"
                                class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"
                            >
                                Nessuna transazione trovata con i filtri
                                applicati.
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-4">
                    <Card
                        class="overflow-hidden border-white/70 bg-white/90 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                    >
                        <CardHeader
                            class="border-b border-slate-200/70 bg-slate-50/70 px-5 py-4 dark:border-white/10 dark:bg-slate-900/60"
                        >
                            <div class="space-y-1">
                                <CardTitle
                                    class="text-base text-slate-950 dark:text-white"
                                >
                                    Riepilogo dinamico mensile
                                </CardTitle>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    Attuale vs previsto per i gruppi del mese.
                                    La nuova riga mette in evidenza subito il
                                    contesto selezionato.
                                </p>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4 p-5">
                            <div
                                v-if="categoryFocus"
                                :class="
                                    cn(
                                        'relative overflow-hidden rounded-[24px] border p-4',
                                        groupPanelTone(
                                            categoryFocus.item.group_key,
                                        ),
                                    )
                                "
                            >
                                <div
                                    :class="
                                        cn(
                                            'pointer-events-none absolute inset-0',
                                            groupGlowTone(
                                                categoryFocus.item.group_key,
                                            ),
                                        )
                                    "
                                />
                                <div class="relative z-10">
                                    <p
                                        :class="
                                            cn(
                                                'inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold tracking-[0.16em] uppercase',
                                                groupBadgeTone(
                                                    categoryFocus.item
                                                        .group_key,
                                                ),
                                            )
                                        "
                                    >
                                        {{ categoryFocus.subtitle }}
                                    </p>
                                    <p
                                        class="mt-2 text-lg font-semibold text-slate-950 dark:text-white"
                                    >
                                        {{ categoryFocus.title }}
                                    </p>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Attuale
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-slate-950 dark:text-white"
                                            >
                                                {{
                                                    formatCurrency(
                                                        categoryFocus.item
                                                            .actual_raw,
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Previsto
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-slate-950 dark:text-white"
                                            >
                                                {{
                                                    formatCurrency(
                                                        categoryFocus.item
                                                            .budget_raw,
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Rimanente
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-emerald-700 dark:text-emerald-300"
                                            >
                                                {{
                                                    formatCurrency(
                                                        categoryFocus.item
                                                            .remaining_raw,
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                Eccedenza
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-rose-700 dark:text-rose-300"
                                            >
                                                {{
                                                    formatCurrency(
                                                        categoryFocus.item
                                                            .excess_raw,
                                                        currency,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="rounded-[24px] bg-slate-50/80 p-4 text-sm text-slate-600 dark:bg-slate-900/60 dark:text-slate-300"
                            >
                                <template v-if="selectedInlineGroupKey">
                                    Il macrogruppo attivo nel foglio è
                                    evidenziato qui sotto. Gli altri gruppi
                                    restano visibili ma secondari.
                                </template>
                                <template v-else>
                                    In assenza di selezione vedi tutti i gruppi
                                    principali del mese. Seleziona un tipo o una
                                    categoria nel foglio per focalizzare il
                                    margine utile.
                                </template>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="group in overviewGroups"
                                    :key="group.key"
                                    :class="
                                        cn(
                                            'relative overflow-hidden rounded-[22px] border p-4 transition-all',
                                            group.isHighlighted
                                                ? `${groupPanelTone(group.key)} shadow-sm`
                                                : 'border-slate-200 bg-white/70 dark:border-white/10 dark:bg-slate-950/40',
                                            group.isDimmed ? 'opacity-55' : '',
                                        )
                                    "
                                >
                                    <div
                                        v-if="group.isHighlighted"
                                        :class="
                                            cn(
                                                'pointer-events-none absolute inset-0',
                                                groupGlowTone(group.key),
                                            )
                                        "
                                    />
                                    <div class="relative z-10">
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-slate-950 dark:text-white"
                                                >
                                                    {{ group.label }}
                                                </p>
                                                <p
                                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        group.count
                                                    }}
                                                    registrazioni
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p
                                                    class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Progr.
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-semibold text-slate-950 dark:text-white"
                                                >
                                                    {{
                                                        formatPercent(
                                                            group.progress_percentage,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <div
                                                class="grid grid-cols-2 gap-3 text-sm"
                                            >
                                                <div>
                                                    <p
                                                        class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        Attuale
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold text-slate-950 dark:text-white"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                group.actual_raw,
                                                                currency,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p
                                                        class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        Previsto
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold text-slate-950 dark:text-white"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                group.budget_raw,
                                                                currency,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="space-y-2">
                                                <div
                                                    class="h-2 rounded-full bg-slate-200 dark:bg-slate-800"
                                                >
                                                    <div
                                                        :class="
                                                            cn(
                                                                'h-2 rounded-full transition-all',
                                                                groupProgressTone(
                                                                    group.key,
                                                                ),
                                                            )
                                                        "
                                                        :style="{
                                                            width: progressWidth(
                                                                group.progress_percentage,
                                                            ),
                                                        }"
                                                    />
                                                </div>
                                                <div
                                                    class="grid grid-cols-2 gap-3 text-xs"
                                                >
                                                    <div>
                                                        <p
                                                            class="tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                        >
                                                            Rimanente
                                                        </p>
                                                        <p
                                                            class="mt-1 font-semibold text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            {{
                                                                formatCurrency(
                                                                    group.remaining_raw,
                                                                    currency,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p
                                                            class="tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                        >
                                                            Eccedenza
                                                        </p>
                                                        <p
                                                            class="mt-1 font-semibold text-rose-700 dark:text-rose-300"
                                                        >
                                                            {{
                                                                formatCurrency(
                                                                    group.excess_raw,
                                                                    currency,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <TransactionFormSheet
                v-model:open="formOpen"
                :year="props.year"
                :month="props.month"
                :sheet="sheet"
                :transaction="editingTransaction"
            />

            <Dialog
                :open="deletingTransaction !== null"
                @update:open="
                    (value) => {
                        if (!value) {
                            deletingTransaction = null;
                        }
                    }
                "
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Elimina registrazione</DialogTitle>
                        <DialogDescription>
                            Questa operazione rimuove la riga selezionata dal
                            foglio del mese.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deletingTransaction"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-white/10 dark:bg-slate-900/60"
                    >
                        <p class="font-medium text-slate-950 dark:text-white">
                            {{ deletingTransaction.category_label }}
                        </p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">
                            {{
                                deletingTransaction.detail ??
                                deletingTransaction.description ??
                                'Nessun dettaglio'
                            }}
                        </p>
                        <p
                            class="mt-3 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ formatDateLong(deletingTransaction.date) }} ·
                            {{
                                formatCurrency(
                                    deletingTransaction.amount_raw,
                                    currency,
                                )
                            }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="deletingTransaction = null"
                        >
                            Annulla
                        </Button>
                        <Button
                            type="button"
                            class="rounded-2xl bg-rose-600 text-white hover:bg-rose-700"
                            @click="confirmDelete"
                        >
                            Elimina riga
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
