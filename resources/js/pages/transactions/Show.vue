<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Calendar,
    ChevronDown,
    ChevronUp,
    Filter,
    Lock,
    Pencil,
    Plus,
    Receipt,
    RefreshCcw,
    RotateCcw,
    Trash2,
    TrendingDown,
    TrendingUp,
    Scale,
    User,
    Wallet,
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
import { previewExchangeSnapshot } from '@/actions/App/Http/Controllers/TransactionsController';
import MoneyInput from '@/components/MoneyInput.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import TransactionFormSheet from '@/components/transactions/TransactionFormSheet.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePrivacyMode } from '@/composables/usePrivacyMode';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency, formatCurrencyLabel } from '@/lib/currency';
import {
    filterOpeningBalanceTransactions,
    persistOpeningBalanceVisibility,
    readOpeningBalanceVisibility,
} from '@/lib/opening-balance-visibility.js';
import {
    persistPlannedRecurringVisibility,
    readPlannedRecurringVisibility,
} from '@/lib/planned-recurring-visibility.js';
import {
    persistTransactionVisibility,
    readTransactionVisibility,
} from '@/lib/transaction-visibility.js';
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

type ReferenceOption = {
    value: string;
    label: string;
};

type PendingMutation =
    | { type: 'create' }
    | { type: 'update'; transactionUuid: string }
    | { type: 'delete'; transactionUuid: string };

type RowFeedbackState = {
    transactionUuid: string;
    type: 'create' | 'highlight' | 'update';
};

type TransactionExchangePreview = {
    amount_raw: number;
    converted_base_amount_raw: number;
    currency_code: string;
    base_currency_code: string;
    exchange_rate: string;
    exchange_rate_date: string;
    exchange_rate_source: string;
    is_multi_currency: boolean;
    should_preview: boolean;
};

type TransactionVisibilityFilter = 'active' | 'deleted' | 'all';
const heroStorageKey = 'transactions-sheet-hero-collapsed';

const props = defineProps<MonthlyTransactionSheetPageProps>();
const { locale, t } = useI18n();
const { isPrivacyModeEnabled } = usePrivacyMode();
const page = usePage();
const inlineDateInput = ref<HTMLInputElement | null>(null);
const transferTypeKey = 'transfer';
const balanceAdjustmentTypeKey = 'balance_adjustment';
const refundTypeKey = 'refund';
const moveTypeKey = 'move';
const moveEligibleTypeKeys = ['income', 'expense', 'bill', 'debt', 'saving'];
const moveBlockedKinds = [
    'scheduled',
    'opening_balance',
    'balance_adjustment',
    'refund',
];
const moveAvailableYears = computed(() =>
    sheet.value.filters.available_years.map((option) => option.value),
);
const moveDateMin = computed(() => {
    const firstYear = moveAvailableYears.value[0];

    return firstYear ? `${firstYear}-01-01` : undefined;
});
const moveDateMax = computed(() => {
    const lastYear = moveAvailableYears.value.at(-1);

    return lastYear ? `${lastYear}-12-31` : undefined;
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('transactions.index.title'),
        href: transactionsRoute({ year: props.year, month: props.month }),
    },
];

const sheet = ref<MonthlyTransactionSheetData>(props.monthlySheet);
const selectedMacrogroup = ref('all');
const selectedCategory = ref('all');
const selectedAccount = ref('all');
const visibilityFilter = ref<TransactionVisibilityFilter>('active');
const showOpeningBalances = ref(true);
const showPlannedRecurring = ref(false);
const isHeroCollapsed = ref(false);
const formOpen = ref(false);
const editingTransaction = ref<MonthlyTransactionSheetTransaction | null>(null);
const editingInlineUuid = ref<string | null>(null);
const refundingTransaction = ref<MonthlyTransactionSheetTransaction | null>(
    null,
);
const deletingTransaction = ref<MonthlyTransactionSheetTransaction | null>(
    null,
);
const forceDeletingTransaction = ref<MonthlyTransactionSheetTransaction | null>(
    null,
);
const creatingInlineTrackedItem = ref(false);
const creatingEditTrackedItem = ref(false);
const pendingMutation = ref<PendingMutation | null>(null);
const rowFeedback = ref<RowFeedbackState | null>(null);
const removingTransactionUuid = ref<string | null>(null);
const highlightedTransactionUuid = ref<string | null>(null);
let rowFeedbackTimeout: ReturnType<typeof setTimeout> | null = null;

const inlineForm = useForm({
    transaction_day: '',
    type_key: '',
    category_uuid: '',
    destination_account_uuid: '',
    amount: '',
    desired_balance: '',
    description: '',
    account_uuid: '',
    scope_uuid: '',
    tracked_item_uuid: '',
});

const editForm = useForm({
    transaction_day: '',
    target_month: '',
    transaction_date: '',
    type_key: '',
    category_uuid: '',
    destination_account_uuid: '',
    amount: '',
    description: '',
    account_uuid: '',
    scope_uuid: '',
    tracked_item_uuid: '',
});
const refundForm = useForm({
    transaction_date: '',
});
const inlineBalanceAdjustmentPreview = ref<{
    theoretical_balance_raw: number;
    desired_balance_raw: number;
    adjustment_amount_raw: number;
    direction: string;
} | null>(null);
const inlineBalanceAdjustmentCurrentBalanceRaw = ref<number | null>(null);
const inlineBalanceAdjustmentCurrentBalanceLoading = ref(false);
const inlineBalanceAdjustmentLoading = ref(false);
const inlineExchangePreview = ref<TransactionExchangePreview | null>(null);
const inlineExchangePreviewLoading = ref(false);
const inlineExchangePreviewError = ref<string | null>(null);
const editExchangePreview = ref<TransactionExchangePreview | null>(null);
const editExchangePreviewLoading = ref(false);
const editExchangePreviewError = ref<string | null>(null);

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
                previousValue.transactions.map(
                    (transaction) => transaction.uuid,
                ),
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
const moneyFormatLocale = computed(() =>
    String(page.props.auth.user?.format_locale ?? 'it-IT'),
);
const visibleInlineDayError = computed(
    () =>
        inlineForm.errors.transaction_date || inlineForm.errors.transaction_day,
);
const visibleEditDayError = computed(
    () => editForm.errors.transaction_date || editForm.errors.transaction_day,
);
const yearValue = computed(() => String(sheet.value.filters.year));
const monthValue = computed(() => String(sheet.value.filters.month));
const canEdit = computed(() => sheet.value.editor.can_edit);
const periodLabel = computed(
    () =>
        `${getMonthLabel(sheet.value.period.month)} ${sheet.value.period.year}`,
);

function resolveFormCurrency(accountUuid: string): string {
    return (
        sheet.value.editor.accounts.find(
            (account) => account.value === accountUuid,
        )?.currency ?? String(page.props.auth.user?.base_currency_code ?? 'EUR')
    );
}

function resolveFormCurrencyLabel(accountUuid: string): string {
    return formatCurrencyLabel(resolveFormCurrency(accountUuid));
}
const macrogroupFilterOptions = computed(() => {
    const seenValues = new Set<string>();

    return [
        { value: 'all', label: t('transactions.index.labels.allGroups') },
        ...sheet.value.filters.group_options,
    ].filter((option) => {
        if (seenValues.has(option.value)) {
            return false;
        }

        seenValues.add(option.value);

        return true;
    });
});
const headerMacrogroupLabel = computed(() => {
    if (selectedMacrogroup.value === 'all') {
        return t('transactions.index.labels.macrogroup');
    }

    return (
        macrogroupFilterOptions.value.find(
            (option) => option.value === selectedMacrogroup.value,
        )?.label ?? t('transactions.index.labels.macrogroup')
    );
});
const categoryFilterOptions = computed(() => [
    { value: 'all', label: t('transactions.index.labels.allCategories') },
    ...sheet.value.filters.category_options,
]);
function accountOwnershipBadgeLabel(
    account:
        | (typeof sheet.value.editor.accounts)[number]
        | (typeof sheet.value.filters.account_options)[number],
): string {
    return account.is_shared === true
        ? t('dashboard.filters.sharedBadge')
        : t('dashboard.filters.ownedBadge');
}

function accountOwnershipBadgeClass(
    account:
        | (typeof sheet.value.editor.accounts)[number]
        | (typeof sheet.value.filters.account_options)[number],
): string {
    return account.is_shared === true
        ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
}

function accountGroupLabel(
    account:
        | (typeof sheet.value.editor.accounts)[number]
        | (typeof sheet.value.filters.account_options)[number],
): string {
    return account.account_type_code === 'credit_card'
        ? t('dashboard.filters.creditCardsGroup')
        : t('dashboard.filters.paymentAccountsGroup');
}

function mapAccountSelectOption(
    account:
        | (typeof sheet.value.editor.accounts)[number]
        | (typeof sheet.value.filters.account_options)[number],
): {
    value: string;
    label: string;
    groupLabel?: string;
    badgeLabel?: string;
    badgeClass?: string;
} {
    return {
        value: account.value,
        label: account.label,
        groupLabel: accountGroupLabel(account),
        badgeLabel: accountOwnershipBadgeLabel(account),
        badgeClass: accountOwnershipBadgeClass(account),
    };
}

function sortAccountOptionsByGroup<
    T extends { account_type_code?: string | null },
>(accounts: T[]): T[] {
    return [...accounts].sort((left, right) => {
        const leftRank = left.account_type_code === 'credit_card' ? 1 : 0;
        const rightRank = right.account_type_code === 'credit_card' ? 1 : 0;

        if (leftRank !== rightRank) {
            return leftRank - rightRank;
        }

        return 0;
    });
}

const accountFilterOptions = computed(() => [
    { value: 'all', label: t('transactions.index.labels.allAccounts') },
    ...sortAccountOptionsByGroup(sheet.value.filters.account_options).map(
        (account) => mapAccountSelectOption(account),
    ),
]);
const trackedItemOptions = computed(() => sheet.value.editor.tracked_items);
const inlineCreateTypeOptions = computed(() => sheet.value.editor.type_options);
const editingInlineTransaction = computed(
    () =>
        sheet.value.transactions.find(
            (transaction) => transaction.uuid === editingInlineUuid.value,
        ) ?? null,
);

function categoryOptionForTransaction(
    transaction: MonthlyTransactionSheetTransaction,
) {
    if (
        transaction.category_uuid === null ||
        transaction.category_uuid === ''
    ) {
        return null;
    }

    const categoryUuid = String(transaction.category_uuid);
    const accountCategories =
        transaction.account_uuid !== null
            ? (sheet.value.editor.categories[
                  String(transaction.account_uuid)
              ] ?? [])
            : [];

    return (
        accountCategories.find((category) => category.value === categoryUuid) ??
        Object.values(sheet.value.editor.categories)
            .flat()
            .find((category) => category.value === categoryUuid) ??
        null
    );
}

function transactionCategoryLabel(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    if (transaction.is_transfer || transaction.is_opening_balance) {
        return transaction.category_label;
    }

    return (
        categoryOptionForTransaction(transaction)?.label ??
        transaction.category_label
    );
}

function transactionCategoryPath(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    if (transaction.is_transfer) {
        return t('dashboard.sections.transfer');
    }

    if (transaction.is_opening_balance) {
        return transaction.category_path;
    }

    const option = categoryOptionForTransaction(transaction);

    return option?.full_path ?? option?.label ?? transaction.category_path;
}

const inlineEditTypeOptions = computed(() => {
    const options = sheet.value.editor.type_options.filter(
        (option) => option.create_only !== true,
    );

    if (editingInlineTransaction.value?.can_refund) {
        options.push({
            value: refundTypeKey,
            label: t('transactions.form.actions.refund'),
        });
    }

    if (!canMoveTransaction(editingInlineTransaction.value)) {
        return options;
    }

    return [
        ...options,
        {
            value: moveTypeKey,
            label: t('transactions.form.actions.move'),
        },
    ];
});
const isInlineTransfer = computed(
    () => inlineForm.type_key === transferTypeKey,
);
const isInlineBalanceAdjustment = computed(
    () => inlineForm.type_key === balanceAdjustmentTypeKey,
);
const isEditTransfer = computed(() => editForm.type_key === transferTypeKey);
const isEditMove = computed(() => editForm.type_key === moveTypeKey);
const inlineDestinationAccounts = computed(() =>
    sheet.value.editor.accounts.filter(
        (account) => account.value !== inlineForm.account_uuid,
    ),
);
const inlineAccountOptions = computed(() =>
    sortAccountOptionsByGroup(sheet.value.editor.accounts).map((account) =>
        mapAccountSelectOption(account),
    ),
);
const inlineDestinationAccountOptions = computed(() =>
    sortAccountOptionsByGroup(inlineDestinationAccounts.value).map((account) =>
        mapAccountSelectOption(account),
    ),
);
const editDestinationAccounts = computed(() =>
    sheet.value.editor.accounts.filter(
        (account) => account.value !== editForm.account_uuid,
    ),
);
const editAccountOptions = computed(() =>
    sortAccountOptionsByGroup(sheet.value.editor.accounts).map((account) =>
        mapAccountSelectOption(account),
    ),
);
const editDestinationAccountOptions = computed(() =>
    sortAccountOptionsByGroup(editDestinationAccounts.value).map((account) =>
        mapAccountSelectOption(account),
    ),
);
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const flashSuccess = computed(() => flash.value.success ?? null);
const refundTransactionError = computed(
    () =>
        (refundForm.errors as { transaction?: string | undefined })
            .transaction ?? null,
);

function canMoveTransaction(
    transaction: MonthlyTransactionSheetTransaction | null | undefined,
): boolean {
    if (!transaction) {
        return false;
    }

    return (
        moveEligibleTypeKeys.includes(transaction.type_key) &&
        !moveBlockedKinds.includes(transaction.kind ?? '') &&
        !transaction.is_transfer &&
        !transaction.is_recurring_transaction &&
        !transaction.is_opening_balance &&
        !transaction.is_deleted
    );
}

function lockedMoveValue(value: string | null | undefined): string {
    return value && value !== ''
        ? value
        : t('transactions.sheet.grid.noSelection');
}

function resolveAccountCategoryContributorUserIds(
    accountUuid: string,
): number[] {
    if (accountUuid === '') {
        return [];
    }

    return (
        sheet.value.editor.accounts.find(
            (account) => account.value === accountUuid,
        )?.category_contributor_user_ids ?? []
    );
}

function resolveAccountScopeContributorUserIds(accountUuid: string): number[] {
    if (accountUuid === '') {
        return [];
    }

    return (
        sheet.value.editor.accounts.find(
            (account) => account.value === accountUuid,
        )?.scope_contributor_user_ids ?? []
    );
}

function resolveAccountTrackedItemContributorUserIds(
    accountUuid: string,
): number[] {
    if (accountUuid === '') {
        return [];
    }

    return (
        sheet.value.editor.accounts.find(
            (account) => account.value === accountUuid,
        )?.tracked_item_contributor_user_ids ?? []
    );
}

function filterEditorCategoriesByAccount(
    accountUuid: string,
    typeKey: string,
): NonNullable<(typeof sheet.value.editor.categories)[string]> {
    const contributorUserIds =
        resolveAccountCategoryContributorUserIds(accountUuid);

    const categories =
        accountUuid === ''
            ? []
            : (sheet.value.editor.categories[accountUuid] ?? []);

    return categories.filter((category) => {
        if (
            contributorUserIds.length > 0 &&
            !contributorUserIds.includes(category.owner_user_id ?? -1)
        ) {
            return false;
        }

        if (typeKey === '') {
            return true;
        }

        return category.type_key === typeKey;
    });
}

function filterEditorScopesByAccount(
    accountUuid: string,
): typeof sheet.value.editor.scopes {
    const contributorUserIds =
        resolveAccountScopeContributorUserIds(accountUuid);

    return sheet.value.editor.scopes.filter(
        (scope) =>
            contributorUserIds.length === 0 ||
            contributorUserIds.includes(scope.owner_user_id ?? -1),
    );
}

function transactionHasAuditDetails(
    transaction: MonthlyTransactionSheetTransaction,
): boolean {
    const createdByUuid = transaction.created_by?.uuid ?? null;
    const updatedByUuid = transaction.updated_by?.uuid ?? null;

    return createdByUuid !== null || updatedByUuid !== createdByUuid;
}

function shouldShowTransactionAuditIcon(
    transaction: MonthlyTransactionSheetTransaction,
): boolean {
    const authenticatedUserUuid = page.props.auth.user?.uuid ?? null;
    const createdBy = transaction.created_by ?? null;

    if (
        !isSharedAccountTransaction(transaction) ||
        !transactionHasAuditDetails(transaction) ||
        createdBy === null
    ) {
        return false;
    }

    return createdBy.uuid !== authenticatedUserUuid;
}

function isSharedAccountTransaction(
    transaction: MonthlyTransactionSheetTransaction,
): boolean {
    if (transaction.account_uuid === null) {
        return false;
    }

    const accountOption =
        sheet.value.editor.accounts.find(
            (account) => account.uuid === transaction.account_uuid,
        ) ??
        sheet.value.filters.account_options.find(
            (account) => account.uuid === transaction.account_uuid,
        ) ??
        null;

    return accountOption?.is_shared === true;
}

function transactionAuditCreatedLabel(
    transaction: MonthlyTransactionSheetTransaction,
): string | null {
    const createdBy = transaction.created_by ?? null;

    if (createdBy === null) {
        return null;
    }

    return t('transactions.sheet.grid.createdBy', {
        name: createdBy.name,
        email: createdBy.email,
    });
}

function transactionAuditUpdatedLabel(
    transaction: MonthlyTransactionSheetTransaction,
): string | null {
    const createdBy = transaction.created_by ?? null;
    const updatedBy = transaction.updated_by ?? null;

    if (updatedBy === null || updatedBy.uuid === createdBy?.uuid) {
        return null;
    }

    return t('transactions.sheet.grid.updatedBy', {
        name: updatedBy.name,
        email: updatedBy.email,
    });
}

function isBalanceAdjustmentTransaction(
    transaction: MonthlyTransactionSheetTransaction,
): boolean {
    return transaction.kind === 'balance_adjustment';
}

function balanceAdjustmentEffectLabel(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    return transaction.amount_raw >= 0
        ? t('transactions.sheet.grid.balanceAdjustmentIncrease')
        : t('transactions.sheet.grid.balanceAdjustmentDecrease');
}

function balanceAdjustmentBadgeTone(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    return transaction.amount_raw >= 0
        ? 'border border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200'
        : 'border border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200';
}

function balanceAdjustmentEffectTone(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    return transaction.amount_raw >= 0
        ? 'text-emerald-700 dark:text-emerald-300'
        : 'text-amber-700 dark:text-amber-300';
}

function transactionAmountCurrency(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    return transaction.currency_code ?? currency.value;
}

function transactionHasExchangeDetails(
    transaction: MonthlyTransactionSheetTransaction,
): boolean {
    return (
        transaction.is_multi_currency &&
        transaction.converted_base_amount_raw !== null &&
        transaction.base_currency_code !== null &&
        transaction.exchange_rate !== null &&
        transaction.exchange_rate_date !== null
    );
}

function transactionConvertedAmountLabel(
    transaction: MonthlyTransactionSheetTransaction,
): string | null {
    if (!transactionHasExchangeDetails(transaction)) {
        return null;
    }

    return t('transactions.sheet.grid.convertedAmount', {
        amount: formatCurrency(
            transaction.converted_base_amount_raw ?? 0,
            transaction.base_currency_code,
            moneyFormatLocale.value,
        ),
    });
}

function transactionExchangeRateContextLabel(
    transaction: MonthlyTransactionSheetTransaction,
): string | null {
    if (!transactionHasExchangeDetails(transaction)) {
        return null;
    }

    return t('transactions.sheet.grid.exchangeRateContext', {
        rate: transaction.exchange_rate,
        date: formatDateLong(transaction.exchange_rate_date),
    });
}

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

    return t('transactions.index.periodNotice', {
        selectedPeriod: periodLabel.value,
        currentPeriod: `${getMonthLabel(currentCalendarMonth)} ${currentCalendarYear}`,
    });
});

const hasActiveFilters = computed(
    () =>
        selectedMacrogroup.value !== 'all' ||
        selectedCategory.value !== 'all' ||
        selectedAccount.value !== 'all',
);

const filteredTransactions = computed(() =>
    filterOpeningBalanceTransactions(
        sheet.value.transactions,
        showOpeningBalances.value,
    ).filter((transaction: MonthlyTransactionSheetTransaction) =>
        matchesFilters(transaction),
    ),
);

const filteredDeletedTransactions = computed(() =>
    sheet.value.deleted_transactions.filter(
        (transaction: MonthlyTransactionSheetTransaction) =>
            matchesFilters(transaction),
    ),
);

const filteredPlannedRecurringTransactions = computed(() => {
    if (!showPlannedRecurring.value || visibilityFilter.value === 'deleted') {
        return [];
    }

    return sheet.value.planned_occurrences.filter(
        (transaction: MonthlyTransactionSheetTransaction) =>
            matchesFilters(transaction),
    );
});

const displayedTransactions = computed(() =>
    [
        ...(visibilityFilter.value === 'deleted'
            ? []
            : filteredTransactions.value),
        ...(visibilityFilter.value === 'active'
            ? []
            : filteredDeletedTransactions.value),
        ...filteredPlannedRecurringTransactions.value,
    ]
        .slice()
        .sort(compareTransactionsForDisplay),
);

onMounted(() => {
    visibilityFilter.value = readTransactionVisibility();
    showOpeningBalances.value = readOpeningBalanceVisibility();
    showPlannedRecurring.value = readPlannedRecurringVisibility();
    isHeroCollapsed.value =
        window.localStorage.getItem(heroStorageKey) === 'true';
    focusHighlightedTransaction();
});

watch(visibilityFilter, (value) => {
    persistTransactionVisibility(value);
});

watch(showOpeningBalances, (value) => {
    persistOpeningBalanceVisibility(value);
});

watch(showPlannedRecurring, (value) => {
    persistPlannedRecurringVisibility(value);
});

watch(isHeroCollapsed, (value) => {
    window.localStorage.setItem(heroStorageKey, value ? 'true' : 'false');
});

watch(displayedTransactions, () => {
    focusHighlightedTransaction();
});

const filteredSummary = computed(() => {
    const totals = filteredTransactions.value.reduce(
        (
            summary: { income: number; expenses: number },
            transaction: MonthlyTransactionSheetTransaction,
        ) => {
            if (transaction.is_opening_balance || transaction.is_transfer) {
                return summary;
            }

            const amount = Math.abs(transaction.amount_raw);

            if (transaction.kind === 'refund') {
                if (transaction.amount_raw > 0) {
                    summary.expenses -= amount;
                } else if (transaction.amount_raw < 0) {
                    summary.income -= amount;
                }

                return summary;
            }

            if (transaction.amount_raw > 0) {
                summary.income += amount;
            } else if (transaction.amount_raw < 0) {
                summary.expenses += amount;
            }

            return summary;
        },
        { income: 0, expenses: 0 },
    );

    return {
        income: totals.income,
        expenses: totals.expenses,
        net: totals.income - totals.expenses,
        count: displayedTransactions.value.length,
    };
});

const filteredLastBalance = computed(() => {
    const periodEndingBalances = sheet.value.meta.period_ending_balances ?? [];

    if (selectedAccount.value !== 'all') {
        return (
            periodEndingBalances.find(
                (balance) => balance.account_uuid === selectedAccount.value,
            )?.balance_raw ?? null
        );
    }

    if (periodEndingBalances.length === 0) {
        return null;
    }

    return periodEndingBalances.reduce(
        (sum, balance) => sum + balance.balance_raw,
        0,
    );
});

const filteredLastMovementDate = computed(() => {
    const periodEndingBalances = sheet.value.meta.period_ending_balances ?? [];

    if (selectedAccount.value !== 'all') {
        return (
            periodEndingBalances.find(
                (balance) => balance.account_uuid === selectedAccount.value,
            )?.last_recorded_at ?? null
        );
    }

    return (
        periodEndingBalances
            .map((balance) => balance.last_recorded_at)
            .filter((value): value is string => value !== null)
            .sort()
            .at(-1) ?? null
    );
});

const totalVisibleRows = computed(() => {
    const activeCount =
        visibilityFilter.value === 'deleted'
            ? 0
            : sheet.value.meta.transactions_count;
    const deletedCount =
        visibilityFilter.value === 'active'
            ? 0
            : sheet.value.meta.deleted_transactions_count;
    const plannedCount =
        showPlannedRecurring.value && visibilityFilter.value !== 'deleted'
            ? sheet.value.meta.planned_occurrences_count
            : 0;

    return activeCount + deletedCount + plannedCount;
});

const summaryCards = computed<SummaryMetricCard[]>(() => {
    const summaryByKey = Object.fromEntries(
        sheet.value.summary_cards.map((card) => [card.key, card]),
    ) as Record<string, MonthlyTransactionSheetSummaryCard | undefined>;
    const scopedIncome = hasActiveFilters.value
        ? filteredSummary.value.income
        : sheet.value.totals.actual_income_raw;
    const scopedExpense = hasActiveFilters.value
        ? filteredSummary.value.expenses
        : sheet.value.totals.actual_expense_raw;
    const scopedNet = hasActiveFilters.value
        ? filteredSummary.value.net
        : sheet.value.totals.net_actual_raw;
    const scopedBalance =
        selectedAccount.value !== 'all'
            ? filteredLastBalance.value
            : sheet.value.meta.last_balance_raw;
    const scopedLastMovementDate =
        selectedAccount.value !== 'all'
            ? filteredLastMovementDate.value
            : sheet.value.meta.last_recorded_at;

    return [
        {
            key: 'income',
            label: t('transactions.sheet.summary.income'),
            value: scopedIncome,
            tone: 'text-emerald-700 dark:text-emerald-300',
            icon: TrendingUp,
            helper: buildBudgetHelper(summaryByKey.income),
        },
        {
            key: 'expense',
            label: t('transactions.sheet.summary.expenses'),
            value: scopedExpense,
            tone: 'text-rose-700 dark:text-rose-300',
            icon: TrendingDown,
            helper: buildBudgetHelper(summaryByKey.expense),
        },
        {
            key: 'net',
            label: t('transactions.sheet.summary.net'),
            value: scopedNet,
            tone: getAmountTone(scopedNet),
            icon: Wallet,
            helper:
                !hasActiveFilters.value && sheet.value.meta.has_budget_data
                    ? isPrivacyModeEnabled.value
                        ? 'Importi nascosti'
                        : t('transactions.sheet.summary.deviation', {
                              amount: formatCurrency(
                                  sheet.value.totals.net_actual_raw -
                                      sheet.value.totals.net_budgeted_raw,
                                  currency.value,
                              ),
                          })
                    : t('transactions.sheet.summary.actualBalance'),
        },
        {
            key: 'count',
            label: t('transactions.sheet.summary.records'),
            value: sheet.value.meta.transactions_count,
            tone: 'text-slate-900 dark:text-slate-100',
            icon: Receipt,
            helper: t('transactions.sheet.summary.visibleRows', {
                count: filteredSummary.value.count,
            }),
        },
        {
            key: 'balance',
            label: t('transactions.sheet.summary.endingBalance'),
            value: scopedBalance,
            tone: getAmountTone(scopedBalance ?? 0),
            icon: Calendar,
            helper: scopedLastMovementDate
                ? t('transactions.sheet.summary.lastMovement', {
                      date: formatDateLong(scopedLastMovementDate),
                  })
                : t('transactions.sheet.summary.unavailableBalance'),
        },
    ];
});

const inlineDayRange = computed(() =>
    buildMonthDayRange(sheet.value.period.year, sheet.value.period.month),
);
const editDayRange = computed(() =>
    buildMonthDayRange(
        sheet.value.period.year,
        isEditMove.value
            ? Number(editForm.target_month || sheet.value.period.month)
            : sheet.value.period.month,
    ),
);

const inlineCategories = computed(() =>
    filterEditorCategoriesByAccount(
        inlineForm.account_uuid,
        inlineForm.type_key,
    ),
);

const editCategories = computed(() =>
    filterEditorCategoriesByAccount(editForm.account_uuid, editForm.type_key),
);

const inlineScopes = computed(() =>
    filterEditorScopesByAccount(inlineForm.account_uuid),
);

const editScopes = computed(() =>
    filterEditorScopesByAccount(editForm.account_uuid),
);

const inlineTrackedItems = computed(() =>
    filterTrackedItemOptions(
        trackedItemOptions.value,
        inlineForm.account_uuid,
        inlineForm.type_key,
        inlineForm.category_uuid,
        inlineForm.tracked_item_uuid,
    ),
);

const editTrackedItems = computed(() =>
    filterTrackedItemOptions(
        trackedItemOptions.value,
        editForm.account_uuid,
        editForm.type_key,
        editForm.category_uuid,
        editForm.tracked_item_uuid,
    ),
);

const inlineReferenceOptions = computed<ReferenceOption[]>(() => [
    ...inlineScopes.value.map((scope) => ({
        value: `scope:${scope.uuid ?? scope.value}`,
        label: scope.label,
    })),
    ...inlineTrackedItems.value.map((trackedItem) => ({
        value: `tracked_item:${trackedItem.uuid ?? trackedItem.value}`,
        label: trackedItem.label,
    })),
]);

const editReferenceOptions = computed<ReferenceOption[]>(() => [
    ...editScopes.value.map((scope) => ({
        value: `scope:${scope.uuid ?? scope.value}`,
        label: scope.label,
    })),
    ...editTrackedItems.value.map((trackedItem) => ({
        value: `tracked_item:${trackedItem.uuid ?? trackedItem.value}`,
        label: trackedItem.label,
    })),
]);

const inlineReferenceValue = computed({
    get(): string {
        if (inlineForm.scope_uuid !== '') {
            return `scope:${inlineForm.scope_uuid}`;
        }

        if (inlineForm.tracked_item_uuid !== '') {
            return `tracked_item:${inlineForm.tracked_item_uuid}`;
        }

        return '';
    },
    set(value: string): void {
        if (value === '') {
            inlineForm.scope_uuid = '';
            inlineForm.tracked_item_uuid = '';
            inlineForm.clearErrors('scope_uuid', 'tracked_item_uuid');

            return;
        }

        if (value.startsWith('scope:')) {
            inlineForm.scope_uuid = value.slice('scope:'.length);
            inlineForm.tracked_item_uuid = '';
            inlineForm.clearErrors('scope_uuid', 'tracked_item_uuid');

            return;
        }

        if (value.startsWith('tracked_item:')) {
            inlineForm.scope_uuid = '';
            inlineForm.tracked_item_uuid = value.slice('tracked_item:'.length);
            inlineForm.clearErrors('scope_uuid', 'tracked_item_uuid');
        }
    },
});

const editReferenceValue = computed({
    get(): string {
        if (editForm.scope_uuid !== '') {
            return `scope:${editForm.scope_uuid}`;
        }

        if (editForm.tracked_item_uuid !== '') {
            return `tracked_item:${editForm.tracked_item_uuid}`;
        }

        return '';
    },
    set(value: string): void {
        if (value === '') {
            editForm.scope_uuid = '';
            editForm.tracked_item_uuid = '';
            editForm.clearErrors('scope_uuid', 'tracked_item_uuid');

            return;
        }

        if (value.startsWith('scope:')) {
            editForm.scope_uuid = value.slice('scope:'.length);
            editForm.tracked_item_uuid = '';
            editForm.clearErrors('scope_uuid', 'tracked_item_uuid');

            return;
        }

        if (value.startsWith('tracked_item:')) {
            editForm.scope_uuid = '';
            editForm.tracked_item_uuid = value.slice('tracked_item:'.length);
            editForm.clearErrors('scope_uuid', 'tracked_item_uuid');
        }
    },
});

const activeEditorForm = computed(() =>
    editingInlineUuid.value !== null ? editForm : inlineForm,
);

const selectedInlineCategoryOverview = computed(
    () =>
        sheet.value.editor.category_overview_items.find(
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
                    ? t('transactions.sheet.overview.editingCategory')
                    : t('transactions.sheet.overview.newRowCategory'),
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
        return t('transactions.sheet.summary.actualValue');
    }

    if (isPrivacyModeEnabled.value) {
        return 'Importi nascosti';
    }

    return `${t('transactions.monthly.section.budget')} ${formatCurrency(card.budgeted_raw, currency.value)} · ${t('transactions.monthly.section.difference')} ${formatCurrency(card.variance_raw, currency.value)}`;
}

function getMonthLabel(month: number): string {
    try {
        return new Intl.DateTimeFormat(locale.value, { month: 'long' }).format(
            new Date(2026, month - 1, 1),
        );
    } catch {
        return t('transactions.monthly.unknownMonth');
    }
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
        return t('transactions.sheet.grid.noDateFallback');
    }

    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: 'short',
    }).format(new Date(date));
}

function formatDateLong(date: string | null): string {
    if (!date) {
        return t('transactions.sheet.grid.noDateFallback');
    }

    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(date));
}

function formatDateNumeric(date: string | null): string {
    if (!date) {
        return t('transactions.sheet.grid.noDateFallback');
    }

    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date));
}

function extractDayFromDate(date: string | null): string {
    if (!date) {
        return '1';
    }

    return String(new Date(date).getDate());
}

function parseIsoDateParts(
    date: string | null,
): { year: number; month: number; day: number } | null {
    if (!date) {
        return null;
    }

    const [year, month, day] = date.split('-').map((value) => Number(value));

    if (
        !Number.isInteger(year) ||
        !Number.isInteger(month) ||
        !Number.isInteger(day)
    ) {
        return null;
    }

    return { year, month, day };
}

function isMoveDateYearAllowed(date: string): boolean {
    const dateParts = parseIsoDateParts(date);

    if (!dateParts) {
        return false;
    }

    return moveAvailableYears.value.includes(dateParts.year);
}

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function resetInlineExchangePreview(): void {
    inlineExchangePreview.value = null;
    inlineExchangePreviewError.value = null;
}

function resetEditExchangePreview(): void {
    editExchangePreview.value = null;
    editExchangePreviewError.value = null;
}

async function refreshExchangePreviewForForm(
    form: typeof inlineForm | typeof editForm,
    previewTarget: typeof inlineExchangePreview | typeof editExchangePreview,
    errorTarget:
        | typeof inlineExchangePreviewError
        | typeof editExchangePreviewError,
    loadingTarget:
        | typeof inlineExchangePreviewLoading
        | typeof editExchangePreviewLoading,
    options: {
        isTransfer: boolean;
        isMove: boolean;
    },
): Promise<void> {
    if (
        options.isTransfer ||
        options.isMove ||
        form.account_uuid === '' ||
        form.transaction_day === '' ||
        form.amount === ''
    ) {
        previewTarget.value = null;
        errorTarget.value = null;

        return;
    }

    const parsedAmount = Number(form.amount);

    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
        previewTarget.value = null;
        errorTarget.value = null;

        return;
    }

    loadingTarget.value = true;
    errorTarget.value = null;

    try {
        const response = await fetch(
            previewExchangeSnapshot.url({
                year: props.year,
                month: props.month,
            }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    account_uuid: form.account_uuid,
                    transaction_day: Number(form.transaction_day),
                    amount: parsedAmount,
                }),
            },
        );

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            previewTarget.value = null;

            Object.entries(payload?.errors ?? {}).forEach(
                ([field, messages]) => {
                    const firstMessage = Array.isArray(messages)
                        ? messages[0]
                        : messages;

                    if (typeof firstMessage === 'string') {
                        form.setError(
                            field as
                                | 'account_uuid'
                                | 'transaction_day'
                                | 'transaction_date'
                                | 'amount',
                            firstMessage,
                        );
                    }
                },
            );

            errorTarget.value =
                (payload?.errors?.transaction_date?.[0] as
                    | string
                    | undefined) ??
                (payload?.errors?.transaction_day?.[0] as string | undefined) ??
                (payload?.errors?.amount?.[0] as string | undefined) ??
                (payload?.errors?.account_uuid?.[0] as string | undefined) ??
                null;

            return;
        }

        previewTarget.value = {
            amount_raw: Number(payload?.amount_raw ?? parsedAmount),
            converted_base_amount_raw: Number(
                payload?.converted_base_amount_raw ?? 0,
            ),
            currency_code: String(
                payload?.currency_code ??
                    resolveFormCurrency(form.account_uuid),
            ),
            base_currency_code: String(
                payload?.base_currency_code ?? currency.value,
            ),
            exchange_rate: String(payload?.exchange_rate ?? '1.00000000'),
            exchange_rate_date: String(payload?.exchange_rate_date ?? ''),
            exchange_rate_source: String(
                payload?.exchange_rate_source ?? 'identity',
            ),
            is_multi_currency: Boolean(payload?.is_multi_currency ?? false),
            should_preview: Boolean(payload?.should_preview ?? false),
        };
        errorTarget.value = null;
        form.clearErrors('transaction_date');
    } catch {
        previewTarget.value = null;
        errorTarget.value = null;
    } finally {
        loadingTarget.value = false;
    }
}

function filterTrackedItemOptions(
    options: MonthlyTransactionSheetTrackedItemOption[],
    accountUuid: string,
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
        trackedItemMatchesContext(option, accountUuid, typeKey, categoryUuid),
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
    accountUuid: string,
    typeKey: string,
    categoryUuid: string,
): boolean {
    if (typeKey === '' || typeKey === transferTypeKey) {
        return false;
    }

    const groupKeys = option.group_keys ?? [];
    const categoryUuids = option.category_uuids ?? [];
    const categoryContextUuids = resolveCategoryContextUuids(categoryUuid);
    const contributorUserIds =
        resolveAccountTrackedItemContributorUserIds(accountUuid);

    if (
        contributorUserIds.length > 0 &&
        !contributorUserIds.includes(option.owner_user_id ?? -1)
    ) {
        return false;
    }

    if (categoryUuids.length > 0) {
        return categoryContextUuids.some((uuid) =>
            categoryUuids.includes(uuid),
        );
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

    const accountUuid =
        editingInlineUuid.value === null
            ? inlineForm.account_uuid
            : editForm.account_uuid;
    const category = (
        accountUuid === ''
            ? []
            : (sheet.value.editor.categories[accountUuid] ?? [])
    ).find((option) => option.value === categoryUuid);

    if (!category) {
        return [categoryUuid];
    }

    return [categoryUuid, ...category.ancestor_uuids];
}

function ensureCategoryMatchesAccountContext(
    accountUuid: string,
    form: typeof inlineForm | typeof editForm,
): void {
    const mutableForm = form as {
        category_uuid: string;
        clearErrors: (...fields: string[]) => void;
    };

    if (mutableForm.category_uuid === '') {
        return;
    }

    const categories = filterEditorCategoriesByAccount(
        accountUuid,
        form.type_key,
    );

    if (
        categories.some(
            (category) => category.value === mutableForm.category_uuid,
        )
    ) {
        return;
    }

    mutableForm.category_uuid = '';
    mutableForm.clearErrors('category_uuid');
}

function ensureScopeMatchesAccountContext(
    accountUuid: string,
    form: typeof inlineForm | typeof editForm,
): void {
    const mutableForm = form as {
        scope_uuid: string;
        clearErrors: (...fields: string[]) => void;
    };

    if (mutableForm.scope_uuid === '') {
        return;
    }

    const scopes = filterEditorScopesByAccount(accountUuid);

    if (scopes.some((scope) => scope.value === mutableForm.scope_uuid)) {
        return;
    }

    mutableForm.scope_uuid = '';
    mutableForm.clearErrors('scope_uuid');
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
    accountUuid: string,
    typeKey: string,
    categoryUuid: string,
): Promise<MonthlyTransactionSheetTrackedItemOption> {
    const response = await fetch('/transactions/tracked-items', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': readCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            name,
            account_uuid: accountUuid,
            category_uuid: categoryUuid,
            type_key: typeKey,
        }),
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => null);
        const slugError = Array.isArray(payload?.errors?.slug)
            ? payload.errors.slug[0]
            : null;
        const firstError = payload?.errors
            ? Object.values(payload.errors)[0]
            : null;

        throw new Error(
            typeof slugError === 'string'
                ? slugError
                : Array.isArray(firstError)
                  ? firstError[0]
                  : t('transactions.form.errors.createTrackedItemFailed'),
        );
    }

    const payload = await response.json();

    return payload.item as MonthlyTransactionSheetTrackedItemOption;
}

async function handleCreateInlineTrackedItem(name: string): Promise<void> {
    if (inlineForm.type_key === '' || inlineForm.type_key === transferTypeKey) {
        inlineForm.setError(
            'tracked_item_uuid',
            t('transactions.form.errors.invalidTypeForTrackedItem'),
        );

        return;
    }

    creatingInlineTrackedItem.value = true;

    try {
        const option = await createTrackedItemFromContext(
            name,
            inlineForm.account_uuid,
            inlineForm.type_key,
            inlineForm.category_uuid,
        );

        pushTrackedItemOption(option);
        inlineReferenceValue.value = `tracked_item:${option.value}`;
    } catch (error) {
        inlineForm.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : t('transactions.form.errors.createTrackedItemFailed'),
        );
    } finally {
        creatingInlineTrackedItem.value = false;
    }
}

async function handleCreateEditTrackedItem(name: string): Promise<void> {
    if (editForm.type_key === '' || editForm.type_key === transferTypeKey) {
        editForm.setError(
            'tracked_item_uuid',
            t('transactions.form.errors.invalidTypeForTrackedItem'),
        );

        return;
    }

    creatingEditTrackedItem.value = true;

    try {
        const option = await createTrackedItemFromContext(
            name,
            editForm.account_uuid,
            editForm.type_key,
            editForm.category_uuid,
        );

        pushTrackedItemOption(option);
        editReferenceValue.value = `tracked_item:${option.value}`;
    } catch (error) {
        editForm.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : t('transactions.form.errors.createTrackedItemFailed'),
        );
    } finally {
        creatingEditTrackedItem.value = false;
    }
}

function normalizeInlineAmount(): number | null {
    const parsedAmount = Number(inlineForm.amount);

    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
        inlineForm.setError(
            'amount',
            t('transactions.form.errors.amountMustBePositive'),
        );

        return null;
    }

    inlineForm.amount = String(parsedAmount);
    inlineForm.clearErrors('amount');

    return parsedAmount;
}

function normalizeInlineDesiredBalance(): number | null {
    const parsedBalance = Number(inlineForm.desired_balance);

    if (!Number.isFinite(parsedBalance)) {
        inlineForm.setError(
            'desired_balance',
            t('transactions.form.errors.desiredBalanceRequired'),
        );

        return null;
    }

    inlineForm.desired_balance = String(parsedBalance);
    inlineForm.clearErrors('desired_balance');

    return parsedBalance;
}

async function refreshInlineBalanceAdjustmentPreview(): Promise<void> {
    if (
        !isInlineBalanceAdjustment.value ||
        inlineForm.account_uuid === '' ||
        inlineForm.transaction_day === '' ||
        inlineForm.desired_balance === ''
    ) {
        inlineBalanceAdjustmentPreview.value = null;

        return;
    }

    const desiredBalance = Number(inlineForm.desired_balance);

    if (!Number.isFinite(desiredBalance)) {
        inlineBalanceAdjustmentPreview.value = null;

        return;
    }

    inlineBalanceAdjustmentLoading.value = true;

    try {
        const response = await fetch(
            `/transactions/${props.year}/${props.month}/balance-adjustment-preview`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    account_uuid: inlineForm.account_uuid,
                    transaction_day: Number(inlineForm.transaction_day),
                    desired_balance: desiredBalance,
                }),
            },
        );

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            inlineBalanceAdjustmentPreview.value = null;
            inlineForm.clearErrors(
                'account_uuid',
                'transaction_day',
                'desired_balance',
            );

            Object.entries(payload?.errors ?? {}).forEach(
                ([field, messages]) => {
                    const firstMessage = Array.isArray(messages)
                        ? messages[0]
                        : messages;

                    if (typeof firstMessage === 'string') {
                        inlineForm.setError(
                            field as
                                | 'account_uuid'
                                | 'transaction_day'
                                | 'desired_balance',
                            firstMessage,
                        );
                    }
                },
            );

            return;
        }

        inlineBalanceAdjustmentPreview.value = {
            theoretical_balance_raw: Number(
                payload?.theoretical_balance_raw ?? 0,
            ),
            desired_balance_raw: Number(
                payload?.desired_balance_raw ?? desiredBalance,
            ),
            adjustment_amount_raw: Number(payload?.adjustment_amount_raw ?? 0),
            direction: String(payload?.direction ?? 'expense'),
        };
        inlineForm.clearErrors(
            'account_uuid',
            'transaction_day',
            'desired_balance',
        );
    } finally {
        inlineBalanceAdjustmentLoading.value = false;
    }
}

async function refreshInlineBalanceAdjustmentCurrentBalance(): Promise<void> {
    if (
        !isInlineBalanceAdjustment.value ||
        inlineForm.account_uuid === '' ||
        inlineForm.transaction_day === ''
    ) {
        inlineBalanceAdjustmentCurrentBalanceRaw.value = null;

        return;
    }

    inlineBalanceAdjustmentCurrentBalanceLoading.value = true;

    try {
        const response = await fetch(
            `/transactions/${props.year}/${props.month}/balance-adjustment-preview`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    account_uuid: inlineForm.account_uuid,
                    transaction_day: Number(inlineForm.transaction_day),
                    desired_balance: 0,
                }),
            },
        );

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            inlineBalanceAdjustmentCurrentBalanceRaw.value = null;

            return;
        }

        inlineBalanceAdjustmentCurrentBalanceRaw.value = Number(
            payload?.theoretical_balance_raw ?? 0,
        );
    } finally {
        inlineBalanceAdjustmentCurrentBalanceLoading.value = false;
    }
}

async function refreshInlineExchangePreview(): Promise<void> {
    await refreshExchangePreviewForForm(
        inlineForm,
        inlineExchangePreview,
        inlineExchangePreviewError,
        inlineExchangePreviewLoading,
        {
            isTransfer: isInlineTransfer.value,
            isMove: false,
        },
    );
}

function normalizeEditAmount(): number | null {
    const parsedAmount = Number(editForm.amount);

    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
        editForm.setError(
            'amount',
            t('transactions.form.errors.amountMustBePositive'),
        );

        return null;
    }

    editForm.amount = String(parsedAmount);
    editForm.clearErrors('amount');

    return parsedAmount;
}

async function refreshEditExchangePreview(): Promise<void> {
    await refreshExchangePreviewForForm(
        editForm,
        editExchangePreview,
        editExchangePreviewError,
        editExchangePreviewLoading,
        {
            isTransfer: isEditTransfer.value,
            isMove: isEditMove.value,
        },
    );
}

function formatPercent(value: number): string {
    return `${new Intl.NumberFormat(locale.value, {
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

function transactionTypeBadgeTone(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    if (transaction.kind === 'refund') {
        return 'border border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/12 dark:text-emerald-200';
    }

    return groupBadgeTone(transaction.type_key);
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
            t('transactions.form.errors.dayRange', {
                min: inlineDayRange.value.min,
                max: inlineDayRange.value.max,
            }),
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
            t('transactions.form.errors.destinationAccountRequired'),
        );

        return false;
    }

    if (inlineForm.destination_account_uuid === inlineForm.account_uuid) {
        inlineForm.setError(
            'destination_account_uuid',
            t('transactions.form.errors.destinationAccountDifferent'),
        );

        return false;
    }

    inlineForm.clearErrors('destination_account_uuid');

    return true;
}

function validateEditDay(): boolean {
    if (isEditMove.value) {
        if (editForm.transaction_date === '') {
            editForm.setError(
                'transaction_date',
                t('transactions.form.labels.moveDate'),
            );

            return false;
        }

        if (!isMoveDateYearAllowed(editForm.transaction_date)) {
            editForm.setError(
                'transaction_date',
                t('transactions.form.errors.moveYearUnavailable'),
            );

            return false;
        }

        editForm.clearErrors('transaction_date');

        return true;
    }

    const day = Number(editForm.transaction_day);

    if (
        !Number.isInteger(day) ||
        day < editDayRange.value.min ||
        day > editDayRange.value.max
    ) {
        editForm.setError(
            'transaction_day',
            t('transactions.form.errors.dayRange', {
                min: editDayRange.value.min,
                max: editDayRange.value.max,
            }),
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
            t('transactions.form.errors.destinationAccountRequired'),
        );

        return false;
    }

    if (editForm.destination_account_uuid === editForm.account_uuid) {
        editForm.setError(
            'destination_account_uuid',
            t('transactions.form.errors.destinationAccountDifferent'),
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
        desired_balance: '',
        description: '',
        account_uuid: resolveDefaultEditorAccountUuid(),
        scope_uuid: '',
        tracked_item_uuid: '',
    };

    inlineForm.defaults(defaults);
    inlineForm.reset();
    inlineForm.clearErrors();
    resetInlineExchangePreview();
}

function resolveDefaultEditorAccountUuid(): string {
    const defaultAccountUuid = sheet.value.editor.default_account_uuid;

    if (
        defaultAccountUuid &&
        sheet.value.editor.accounts.some(
            (account) => account.value === defaultAccountUuid,
        )
    ) {
        return defaultAccountUuid;
    }

    return sheet.value.editor.accounts[0]?.value ?? '';
}

function focusInlineRow(): void {
    nextTick(() => {
        inlineDateInput.value?.focus();
    });
}

function triggerRowFeedback(
    transactionUuid: string,
    type: 'create' | 'highlight' | 'update',
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

    if (rowFeedback.value.type === 'highlight') {
        return 'bg-amber-50/85 ring-1 ring-amber-200 transition-all duration-700 dark:bg-amber-500/10 dark:ring-amber-500/25';
    }

    return rowFeedback.value.type === 'create'
        ? 'bg-emerald-50/85 ring-1 ring-emerald-200 transition-all duration-700 dark:bg-emerald-500/10 dark:ring-emerald-500/25'
        : 'bg-sky-50/85 ring-1 ring-sky-200 transition-all duration-700 dark:bg-sky-500/10 dark:ring-sky-500/25';
}

function visibilityFilterLabel(value: TransactionVisibilityFilter): string {
    return t(`transactions.sheet.filters.visibilityOptions.${value}`);
}

function readHighlightedTransactionUuid(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const value = new URLSearchParams(window.location.search).get('highlight');

    return value && value.trim() !== '' ? value : null;
}

function focusHighlightedTransaction(): void {
    const transactionUuid = readHighlightedTransactionUuid();

    if (
        !transactionUuid ||
        highlightedTransactionUuid.value === transactionUuid ||
        !sheet.value.transactions.some(
            (transaction) => transaction.uuid === transactionUuid,
        )
    ) {
        return;
    }

    highlightedTransactionUuid.value = transactionUuid;

    nextTick(() => {
        const target = document.querySelector<HTMLElement>(
            `[data-transaction-row="${transactionUuid}"]`,
        );

        if (!target) {
            return;
        }

        target.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

        triggerRowFeedback(transactionUuid, 'highlight');
    });
}

function compareTransactionsForDisplay(
    left: MonthlyTransactionSheetTransaction,
    right: MonthlyTransactionSheetTransaction,
): number {
    const dateComparison = String(right.date ?? '').localeCompare(
        String(left.date ?? ''),
    );

    if (dateComparison !== 0) {
        return dateComparison;
    }

    const weight = (
        transaction: MonthlyTransactionSheetTransaction,
    ): number => {
        if (transaction.is_opening_balance) {
            return 0;
        }

        if (transaction.is_projected_recurring) {
            return 2;
        }

        return 1;
    };

    const weightComparison = weight(left) - weight(right);

    if (weightComparison !== 0) {
        return weightComparison;
    }

    return left.uuid.localeCompare(right.uuid);
}

function recurringTransactionBadge(
    transaction: MonthlyTransactionSheetTransaction,
): { label: string; tone: string } | null {
    if (transaction.is_projected_recurring) {
        return {
            label: t('transactions.sheet.grid.plannedRecurringBadge'),
            tone: 'border border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-200',
        };
    }

    if (transaction.is_recurring_transaction) {
        return {
            label: t('transactions.sheet.grid.recurringBadge'),
            tone: 'border border-sky-200 bg-sky-100 text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200',
        };
    }

    return null;
}

function recurringTransactionHelper(
    transaction: MonthlyTransactionSheetTransaction,
): string | null {
    if (transaction.is_projected_recurring) {
        return t('transactions.sheet.grid.fromRecurringPreview');
    }

    if (transaction.is_recurring_transaction) {
        return t('transactions.sheet.grid.fromRecurring');
    }

    return null;
}

function creditCardCycleHelper(
    transaction: MonthlyTransactionSheetTransaction,
): string | null {
    if (!transaction.credit_card_payment_due_date) {
        return null;
    }

    return t(
        transaction.kind === 'refund'
            ? 'transactions.sheet.grid.creditCardRefundCycleHint'
            : 'transactions.sheet.grid.creditCardChargeCycleHint',
        {
            date: formatDateLong(transaction.credit_card_payment_due_date),
        },
    );
}

function transactionRowToneClass(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    if (transaction.is_deleted) {
        return 'bg-slate-100/75 opacity-75 dark:bg-slate-900/75';
    }

    if (transaction.is_projected_recurring) {
        return 'bg-violet-50/70 dark:bg-violet-500/8';
    }

    if (transaction.is_opening_balance) {
        return 'bg-amber-50/70 dark:bg-amber-500/5';
    }

    if (transaction.is_recurring_transaction) {
        return 'bg-sky-50/60 dark:bg-sky-500/6';
    }

    if (transaction.kind === 'refund') {
        return 'bg-emerald-50/70 dark:bg-emerald-500/8';
    }

    return '';
}

function transactionAccentClass(
    transaction: MonthlyTransactionSheetTransaction,
): string {
    if (transaction.is_deleted) {
        return 'border-l-4 border-slate-300 dark:border-slate-700';
    }

    if (transaction.is_projected_recurring) {
        return 'border-l-4 border-violet-300 dark:border-violet-500/35';
    }

    if (transaction.is_recurring_transaction) {
        return 'border-l-4 border-sky-300 dark:border-sky-500/35';
    }

    if (transaction.kind === 'refund') {
        return 'border-l-4 border-emerald-300 dark:border-emerald-500/35';
    }

    return '';
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

function handleMobilePrimaryAction(event: Event): void {
    const customEvent = event as CustomEvent<{ kind?: string }>;

    if (customEvent.detail?.kind !== 'transaction') {
        return;
    }

    customEvent.preventDefault();
    openCreate();
}

function consumeCreateTransactionQuery(): boolean {
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

function openEdit(transaction: MonthlyTransactionSheetTransaction): void {
    if (!canEdit.value || !transaction.can_edit) {
        return;
    }

    editingTransaction.value = transaction;
    formOpen.value = true;
}

function startInlineEdit(
    transaction: MonthlyTransactionSheetTransaction,
): void {
    if (!canEdit.value || !transaction.can_edit) {
        return;
    }

    editingInlineUuid.value = transaction.uuid;
    const transactionDateParts = parseIsoDateParts(transaction.date);
    editForm.defaults({
        transaction_day: extractDayFromDate(transaction.date),
        target_month: transactionDateParts
            ? String(transactionDateParts.month)
            : String(sheet.value.period.month),
        transaction_date: transaction.date ?? '',
        type_key: transaction.type_key ?? '',
        category_uuid: transaction.is_transfer
            ? ''
            : transaction.category_uuid
              ? String(transaction.category_uuid)
              : '',
        destination_account_uuid: transaction.related_account_uuid
            ? String(transaction.related_account_uuid)
            : '',
        amount:
            transaction.amount_value_raw !== null
                ? String(transaction.amount_value_raw)
                : '',
        description: transaction.description ?? '',
        account_uuid: transaction.account_uuid
            ? String(transaction.account_uuid)
            : '',
        scope_uuid: transaction.scope_uuid
            ? String(transaction.scope_uuid)
            : '',
        tracked_item_uuid: transaction.is_transfer
            ? ''
            : transaction.tracked_item_uuid
              ? String(transaction.tracked_item_uuid)
              : '',
    });
    editForm.reset();
    editForm.clearErrors();
    resetEditExchangePreview();
}

function cancelInlineEdit(): void {
    editingInlineUuid.value = null;
    editForm.reset();
    editForm.clearErrors();
    resetEditExchangePreview();
}

function requestDelete(transaction: MonthlyTransactionSheetTransaction): void {
    if (!canEdit.value || !transaction.can_delete) {
        return;
    }

    deletingTransaction.value = transaction;
}

function requestRefund(transaction: MonthlyTransactionSheetTransaction): void {
    if (!canEdit.value || !transaction.can_refund) {
        return;
    }

    refundForm.defaults({
        transaction_date: transaction.date ?? '',
    });
    refundForm.reset();
    refundForm.clearErrors();
    refundingTransaction.value = transaction;
}

function undoRefund(transaction: MonthlyTransactionSheetTransaction): void {
    if (!canEdit.value || !transaction.can_undo_refund) {
        return;
    }

    router.delete(
        `/transactions/${props.year}/${props.month}/${transaction.uuid}/refund`,
        {
            preserveScroll: true,
        },
    );
}

function handleInlineEditTypeChange(
    transaction: MonthlyTransactionSheetTransaction,
    value: string,
): void {
    if (value === refundTypeKey) {
        cancelInlineEdit();
        requestRefund(transaction);

        return;
    }

    editForm.type_key = value;
}

function confirmRefund(): void {
    if (!refundingTransaction.value) {
        return;
    }

    refundForm
        .transform(() => ({
            transaction_date: refundForm.transaction_date || null,
        }))
        .post(
            `/transactions/${props.year}/${props.month}/${refundingTransaction.value.uuid}/refund`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    refundingTransaction.value = null;
                    refundForm.reset();
                    refundForm.clearErrors();
                },
            },
        );
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

    const normalizedAmount = isInlineBalanceAdjustment.value
        ? null
        : normalizeInlineAmount();
    const normalizedDesiredBalance = isInlineBalanceAdjustment.value
        ? normalizeInlineDesiredBalance()
        : null;

    if (
        (!isInlineBalanceAdjustment.value && normalizedAmount === null) ||
        (isInlineBalanceAdjustment.value && normalizedDesiredBalance === null)
    ) {
        return;
    }

    const payload = {
        transaction_day: Number(inlineForm.transaction_day),
        type_key: inlineForm.type_key,
        category_uuid: inlineForm.category_uuid || null,
        destination_account_uuid: inlineForm.destination_account_uuid || null,
        amount: normalizedAmount,
        desired_balance: normalizedDesiredBalance,
        description: inlineForm.description.trim() || null,
        account_uuid: inlineForm.account_uuid,
        scope_uuid: inlineForm.scope_uuid || null,
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
                    desired_balance: '',
                    description: '',
                    account_uuid:
                        preservedAccount || resolveDefaultEditorAccountUuid(),
                    scope_uuid: '',
                    tracked_item_uuid: '',
                });
                inlineForm.reset();
                inlineForm.clearErrors();
                resetInlineExchangePreview();
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

    if (isEditMove.value) {
        editForm
            .transform(() => ({
                transaction_date: editForm.transaction_date,
                type_key: moveTypeKey,
            }))
            .patch(
                `/transactions/${props.year}/${props.month}/${transaction.uuid}`,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        pendingMutation.value = {
                            type: 'update',
                            transactionUuid,
                        };
                        cancelInlineEdit();
                    },
                },
            );

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
        scope_uuid: editForm.scope_uuid || null,
        tracked_item_uuid: editForm.tracked_item_uuid || null,
    };

    editForm
        .transform(() => payload)
        .patch(
            `/transactions/${props.year}/${props.month}/${transaction.uuid}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    pendingMutation.value = {
                        type: 'update',
                        transactionUuid,
                    };
                    cancelInlineEdit();
                },
            },
        );
}

function matchesFilters(
    transaction: MonthlyTransactionSheetTransaction,
): boolean {
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

    return !(
        selectedAccount.value !== 'all' &&
        String(transaction.account_uuid) !== selectedAccount.value
    );
}

function setShowOpeningBalances(checked: boolean | 'indeterminate'): void {
    showOpeningBalances.value = checked === true;
}

function setShowPlannedRecurring(checked: boolean | 'indeterminate'): void {
    showPlannedRecurring.value = checked === true;
}

function setShowDeletedOnly(checked: boolean | 'indeterminate'): void {
    visibilityFilter.value = checked === true ? 'deleted' : 'active';
}

function setVisibilityFilter(value: string): void {
    if (value === 'active' || value === 'deleted' || value === 'all') {
        visibilityFilter.value = value;
    }
}

function restoreTransaction(transactionUuid: string): void {
    router.patch(
        `/transactions/${props.year}/${props.month}/${transactionUuid}/restore`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                visibilityFilter.value = 'active';
                pendingMutation.value = null;
                triggerRowFeedback(transactionUuid, 'update');
            },
        },
    );
}

function requestForceDelete(
    transaction: MonthlyTransactionSheetTransaction,
): void {
    if (!canEdit.value || !transaction.can_force_delete) {
        return;
    }

    forceDeletingTransaction.value = transaction;
}

function confirmForceDelete(): void {
    if (!forceDeletingTransaction.value) {
        return;
    }

    const transactionUuid = forceDeletingTransaction.value.uuid;
    forceDeletingTransaction.value = null;

    router.delete(
        `/transactions/${props.year}/${props.month}/${transactionUuid}/force`,
        {
            preserveScroll: true,
            onSuccess: () => {
                visibilityFilter.value = 'deleted';
                pendingMutation.value = null;
            },
        },
    );
}

watch(
    () => inlineForm.type_key,
    (typeKey) => {
        if (typeKey === transferTypeKey) {
            inlineForm.category_uuid = '';
            inlineForm.scope_uuid = '';
            inlineForm.tracked_item_uuid = '';
            inlineForm.clearErrors(
                'category_uuid',
                'scope_uuid',
                'tracked_item_uuid',
            );
        } else if (typeKey === balanceAdjustmentTypeKey) {
            inlineForm.category_uuid = '';
            inlineForm.destination_account_uuid = '';
            inlineForm.scope_uuid = '';
            inlineForm.tracked_item_uuid = '';
            inlineForm.amount = '';
            inlineForm.clearErrors(
                'category_uuid',
                'destination_account_uuid',
                'scope_uuid',
                'tracked_item_uuid',
                'amount',
            );
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

onMounted(() => {
    if (consumeCreateTransactionQuery()) {
        openCreate();
    }

    window.addEventListener(
        'app:mobile-primary-action',
        handleMobilePrimaryAction as EventListener,
    );
});

onBeforeUnmount(() => {
    window.removeEventListener(
        'app:mobile-primary-action',
        handleMobilePrimaryAction as EventListener,
    );
});

watch(
    () =>
        [
            inlineForm.type_key,
            inlineForm.account_uuid,
            inlineForm.transaction_day,
        ] as const,
    () => {
        void refreshInlineBalanceAdjustmentCurrentBalance();
        void refreshInlineBalanceAdjustmentPreview();
    },
);

watch(
    () =>
        [
            inlineForm.type_key,
            inlineForm.account_uuid,
            inlineForm.transaction_day,
            inlineForm.amount,
        ] as const,
    () => {
        void refreshInlineExchangePreview();
    },
);

watch(
    () =>
        [
            inlineForm.type_key,
            inlineForm.account_uuid,
            inlineForm.transaction_day,
            inlineForm.desired_balance,
        ] as const,
    ([, , , desiredBalance], [, , , previousDesiredBalance]) => {
        if (desiredBalance === previousDesiredBalance) {
            return;
        }

        void refreshInlineBalanceAdjustmentPreview();
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
                inlineForm.account_uuid,
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
            editForm.scope_uuid = '';
            editForm.tracked_item_uuid = '';
            editForm.clearErrors(
                'category_uuid',
                'scope_uuid',
                'tracked_item_uuid',
            );
        } else if (typeKey === moveTypeKey) {
            editForm.clearErrors(
                'category_uuid',
                'scope_uuid',
                'tracked_item_uuid',
                'amount',
                'description',
                'account_uuid',
                'destination_account_uuid',
            );
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
                editForm.account_uuid,
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
    () => editForm.transaction_date,
    (value) => {
        if (!isEditMove.value || value === '') {
            return;
        }

        const dateParts = parseIsoDateParts(value);

        if (!dateParts) {
            return;
        }

        editForm.transaction_day = String(dateParts.day);
        editForm.target_month = String(dateParts.month);
        editForm.clearErrors('transaction_date');
    },
);

watch(
    () =>
        [
            editForm.type_key,
            editForm.account_uuid,
            editForm.transaction_day,
            editForm.amount,
            editForm.transaction_date,
        ] as const,
    () => {
        void refreshEditExchangePreview();
    },
);

watch(
    () => inlineForm.account_uuid,
    () => {
        if (inlineForm.destination_account_uuid === inlineForm.account_uuid) {
            inlineForm.destination_account_uuid = '';
        }

        ensureCategoryMatchesAccountContext(
            inlineForm.account_uuid,
            inlineForm,
        );
        ensureScopeMatchesAccountContext(inlineForm.account_uuid, inlineForm);

        if (
            inlineForm.tracked_item_uuid !== '' &&
            !inlineTrackedItems.value.some(
                (option) => option.value === inlineForm.tracked_item_uuid,
            )
        ) {
            inlineForm.tracked_item_uuid = '';
            inlineForm.clearErrors('tracked_item_uuid');
        }
    },
);

watch(
    () => editForm.account_uuid,
    () => {
        if (editForm.destination_account_uuid === editForm.account_uuid) {
            editForm.destination_account_uuid = '';
        }

        ensureCategoryMatchesAccountContext(editForm.account_uuid, editForm);
        ensureScopeMatchesAccountContext(editForm.account_uuid, editForm);

        if (
            editForm.tracked_item_uuid !== '' &&
            !editTrackedItems.value.some(
                (option) => option.value === editForm.tracked_item_uuid,
            )
        ) {
            editForm.tracked_item_uuid = '';
            editForm.clearErrors('tracked_item_uuid');
        }
    },
);

resetInlineEntry();
</script>

<template>
    <Head
        :title="
            t('transactions.sheet.metaTitle', {
                month: getMonthLabel(sheet.period.month),
                year: sheet.period.year,
            })
        "
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-[28px] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.14),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.10),_transparent_28%),linear-gradient(135deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94))] shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.16),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.12),_transparent_28%),linear-gradient(135deg,rgba(2,6,23,0.95),rgba(15,23,42,0.9))]"
            >
                <div class="space-y-4 p-5 md:hidden">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                <Receipt class="mr-1 size-3.5" />
                                {{ t('transactions.sheet.badge') }}
                            </Badge>
                            <Badge
                                v-if="sheet.meta.has_budget_data"
                                class="rounded-full bg-emerald-500/12 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                            >
                                <Calendar class="mr-1 size-3.5" />
                                {{ t('transactions.sheet.budgetLinked') }}
                            </Badge>
                        </div>

                        <div class="space-y-1">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{
                                    t('transactions.sheet.heading', {
                                        month: getMonthLabel(
                                            sheet.period.month,
                                        ),
                                        year: sheet.period.year,
                                    })
                                }}
                            </h1>
                            <p
                                v-if="!isHeroCollapsed"
                                class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('transactions.sheet.description') }}
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
                                          'transactions.sheet.actions.expandOverview',
                                      )
                                    : t(
                                          'transactions.sheet.actions.collapseOverview',
                                      )
                            }}
                        </Button>
                    </div>

                    <div
                        v-if="!isHeroCollapsed"
                        class="grid gap-3 sm:grid-cols-2"
                    >
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('transactions.sheet.filters.year') }}
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
                                {{ t('transactions.sheet.filters.month') }}
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

                        <div class="space-y-2 sm:col-span-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'transactions.sheet.filters.globalMacrogroup',
                                    )
                                }}
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
                    </div>
                </div>

                <div
                    class="hidden gap-6 p-5 md:grid lg:grid-cols-[minmax(0,1fr)_auto] lg:p-7"
                >
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                <Receipt class="mr-1 size-3.5" />
                                {{ t('transactions.sheet.badge') }}
                            </Badge>
                            <Badge
                                v-if="sheet.meta.has_budget_data"
                                class="rounded-full bg-emerald-500/12 px-3 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                            >
                                <Calendar class="mr-1 size-3.5" />
                                {{ t('transactions.sheet.budgetLinked') }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{
                                    t('transactions.sheet.heading', {
                                        month: getMonthLabel(
                                            sheet.period.month,
                                        ),
                                        year: sheet.period.year,
                                    })
                                }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('transactions.sheet.description') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[520px]">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('transactions.sheet.filters.year') }}
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
                                {{ t('transactions.sheet.filters.month') }}
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
                                {{
                                    t(
                                        'transactions.sheet.filters.globalMacrogroup',
                                    )
                                }}
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
                            class="hidden h-11 rounded-2xl border-white/70 bg-white/90 md:inline-flex dark:border-white/10 dark:bg-slate-950/70"
                            :disabled="!canEdit"
                            @click="openCreate"
                        >
                            <Lock v-if="!canEdit" class="mr-2 size-4" />
                            <Plus v-else class="mr-2 size-4" />
                            {{
                                canEdit
                                    ? t('transactions.sheet.actions.new')
                                    : t('transactions.sheet.actions.closedYear')
                            }}
                        </Button>
                    </div>
                </div>
            </section>

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

            <Alert
                v-if="flashSuccess"
                class="border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
            >
                <Receipt class="size-4" />
                <AlertTitle>{{
                    t('transactions.sheet.alerts.operationCompleted')
                }}</AlertTitle>
                <AlertDescription>
                    {{ flashSuccess }}
                </AlertDescription>
            </Alert>

            <Alert
                v-if="sheet.meta.year_is_closed"
                class="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <Lock class="size-4" />
                <AlertTitle>{{
                    t('transactions.sheet.alerts.closedYear')
                }}</AlertTitle>
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
                                        <SensitiveValue
                                            variant="veil"
                                            :value="
                                                formatCurrency(
                                                    card.value ?? 0,
                                                    currency,
                                                )
                                            "
                                        />
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
                        class="grid gap-3 xl:grid-cols-[repeat(4,minmax(0,1fr))_auto]"
                    >
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'transactions.sheet.filters.typeMacrogroup',
                                    )
                                }}
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
                                {{ t('transactions.sheet.filters.category') }}
                            </p>
                            <SearchableSelect
                                v-model="selectedCategory"
                                :options="categoryFilterOptions"
                                :placeholder="
                                    t('transactions.index.labels.allCategories')
                                "
                                :search-placeholder="
                                    t(
                                        'transactions.sheet.filters.searchCategory',
                                    )
                                "
                                clearable
                                clear-value="all"
                                trigger-class="h-11 rounded-2xl border-slate-200 dark:border-white/10"
                            />
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('transactions.sheet.filters.account') }}
                            </p>
                            <SearchableSelect
                                v-model="selectedAccount"
                                :options="accountFilterOptions"
                                :placeholder="
                                    t('transactions.index.labels.allAccounts')
                                "
                                :search-placeholder="
                                    t(
                                        'transactions.sheet.filters.searchAccount',
                                    )
                                "
                                clearable
                                clear-value="all"
                                trigger-class="h-11 rounded-2xl border-slate-200 dark:border-white/10"
                            />
                        </div>

                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('transactions.sheet.filters.visibility') }}
                            </p>
                            <Select
                                :model-value="visibilityFilter"
                                @update:model-value="
                                    setVisibilityFilter(String($event))
                                "
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-slate-200 dark:border-white/10"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent class="z-[170]">
                                    <SelectItem value="active">
                                        {{ visibilityFilterLabel('active') }}
                                    </SelectItem>
                                    <SelectItem value="deleted">
                                        {{ visibilityFilterLabel('deleted') }}
                                    </SelectItem>
                                    <SelectItem value="all">
                                        {{ visibilityFilterLabel('all') }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
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
                                {{ t('transactions.sheet.actions.reset') }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px] 2xl:grid-cols-[minmax(0,1fr)_340px]"
            >
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
                                    {{ t('transactions.sheet.grid.title') }}
                                </CardTitle>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t(
                                            'transactions.sheet.grid.visibleRowsSummary',
                                            {
                                                visible: filteredSummary.count,
                                                total: totalVisibleRows,
                                            },
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="canEdit"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t('transactions.sheet.grid.desktopHint')
                                    }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-sm text-slate-600 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300"
                                >
                                    <Checkbox
                                        :model-value="showOpeningBalances"
                                        @update:model-value="
                                            setShowOpeningBalances
                                        "
                                    />
                                    <span>
                                        {{
                                            t(
                                                'transactions.sheet.filters.showOpeningBalances',
                                            )
                                        }}
                                    </span>
                                </label>
                                <label
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-sm text-slate-600 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300"
                                >
                                    <Checkbox
                                        :model-value="showPlannedRecurring"
                                        @update:model-value="
                                            setShowPlannedRecurring
                                        "
                                    />
                                    <span>
                                        {{
                                            t(
                                                'transactions.sheet.filters.showPlannedRecurring',
                                            )
                                        }}
                                    </span>
                                </label>
                                <label
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-sm text-slate-600 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300"
                                >
                                    <Checkbox
                                        :model-value="
                                            visibilityFilter === 'deleted'
                                        "
                                        @update:model-value="setShowDeletedOnly"
                                    />
                                    <span>
                                        {{
                                            t(
                                                'transactions.sheet.filters.showDeletedOnly',
                                            )
                                        }}
                                    </span>
                                </label>
                                <Badge
                                    variant="outline"
                                    class="rounded-full border-slate-200 bg-white/80 px-3 py-1 text-slate-600 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300"
                                >
                                    <Filter class="mr-1 size-3.5" />
                                    {{
                                        hasActiveFilters
                                            ? t(
                                                  'transactions.sheet.grid.activeFilters',
                                              )
                                            : t(
                                                  'transactions.sheet.grid.fullView',
                                              )
                                    }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="rounded-full border-slate-200 bg-white/80 px-3 py-1 text-slate-600 dark:border-white/10 dark:bg-slate-950/60 dark:text-slate-300"
                                >
                                    {{ periodLabel }}
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
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.date',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.typeMacrogroup',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.accountResource',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.category',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="w-[11.5rem] min-w-[11.5rem] px-4 py-3 text-right text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.amount',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.detail',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.trackedItem',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.columns.actions',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="transaction in displayedTransactions"
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
                                                        v-if="isEditMove"
                                                        ref="inlineDateInput"
                                                        v-model="
                                                            editForm.transaction_date
                                                        "
                                                        type="date"
                                                        :min="moveDateMin"
                                                        :max="moveDateMax"
                                                        :class="
                                                            editFieldClass(
                                                                'transaction_date',
                                                            )
                                                        "
                                                        @keydown.enter.prevent="
                                                            submitInlineEdit(
                                                                transaction.uuid,
                                                            )
                                                        "
                                                    />
                                                    <Input
                                                        v-else
                                                        v-model="
                                                            editForm.transaction_day
                                                        "
                                                        type="number"
                                                        inputmode="numeric"
                                                        :placeholder="
                                                            t(
                                                                'transactions.form.placeholders.day',
                                                            )
                                                        "
                                                        :min="editDayRange.min"
                                                        :max="editDayRange.max"
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
                                                        {{
                                                            isEditMove
                                                                ? t(
                                                                      'transactions.form.labels.moveDate',
                                                                  )
                                                                : t(
                                                                      'transactions.sheet.grid.day',
                                                                  )
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            visibleEditDayError
                                                        "
                                                        class="text-center text-[11px] text-rose-600 dark:text-rose-400"
                                                    >
                                                        {{
                                                            visibleEditDayError
                                                        }}
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3">
                                                <Select
                                                    :model-value="
                                                        editForm.type_key
                                                    "
                                                    @update:model-value="
                                                        handleInlineEditTypeChange(
                                                            transaction,
                                                            String($event),
                                                        )
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
                                                            :placeholder="
                                                                t(
                                                                    'transactions.sheet.filters.type',
                                                                )
                                                            "
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent
                                                        class="z-[170]"
                                                    >
                                                        <SelectItem
                                                            v-for="option in inlineEditTypeOptions"
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
                                                    v-model="
                                                        editForm.account_uuid
                                                    "
                                                    :options="
                                                        editAccountOptions
                                                    "
                                                    :placeholder="
                                                        isEditTransfer
                                                            ? t(
                                                                  'transactions.sheet.filters.sourceAccount',
                                                              )
                                                            : t(
                                                                  'transactions.sheet.filters.account',
                                                              )
                                                    "
                                                    :search-placeholder="
                                                        isEditTransfer
                                                            ? t(
                                                                  'transactions.form.placeholders.searchSourceAccount',
                                                              )
                                                            : t(
                                                                  'transactions.form.placeholders.searchAccount',
                                                              )
                                                    "
                                                    clearable
                                                    :disabled="isEditMove"
                                                    :trigger-class="
                                                        editFieldClass(
                                                            'account_uuid',
                                                        )
                                                    "
                                                />
                                                <p
                                                    v-if="
                                                        editForm.account_uuid !==
                                                        ''
                                                    "
                                                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.form.helper.accountCurrency',
                                                            {
                                                                currency:
                                                                    resolveFormCurrencyLabel(
                                                                        editForm.account_uuid,
                                                                    ),
                                                            },
                                                        )
                                                    }}
                                                </p>
                                            </td>
                                            <td class="px-3 py-3">
                                                <div
                                                    v-if="isEditMove"
                                                    class="flex h-10 items-center rounded-xl border border-dashed border-sky-200 px-3 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:text-sky-300"
                                                >
                                                    {{
                                                        lockedMoveValue(
                                                            transactionCategoryLabel(
                                                                transaction,
                                                            ),
                                                        )
                                                    }}
                                                </div>
                                                <SearchableSelect
                                                    v-else-if="!isEditTransfer"
                                                    v-model="
                                                        editForm.category_uuid
                                                    "
                                                    :options="editCategories"
                                                    :placeholder="
                                                        t(
                                                            'transactions.sheet.filters.category',
                                                        )
                                                    "
                                                    :search-placeholder="
                                                        t(
                                                            'transactions.sheet.filters.searchCategory',
                                                        )
                                                    "
                                                    :disabled="
                                                        editForm.type_key === ''
                                                    "
                                                    clearable
                                                    hierarchical
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
                                                        editDestinationAccountOptions
                                                    "
                                                    :placeholder="
                                                        t(
                                                            'transactions.sheet.filters.destinationAccount',
                                                        )
                                                    "
                                                    :search-placeholder="
                                                        t(
                                                            'transactions.sheet.filters.searchDestinationAccount',
                                                        )
                                                    "
                                                    clearable
                                                    :trigger-class="
                                                        editFieldClass(
                                                            'destination_account_uuid',
                                                        )
                                                    "
                                                />
                                            </td>
                                            <td
                                                class="w-[11.5rem] min-w-[11.5rem] px-3 py-3"
                                            >
                                                <div class="space-y-2">
                                                    <MoneyInput
                                                        v-model="
                                                            editForm.amount
                                                        "
                                                        :disabled="isEditMove"
                                                        :format-locale="
                                                            moneyFormatLocale
                                                        "
                                                        :currency-code="
                                                            resolveFormCurrency(
                                                                editForm.account_uuid,
                                                            )
                                                        "
                                                        placeholder="0,00"
                                                        :class="
                                                            cn(
                                                                editFieldClass(
                                                                    'amount',
                                                                ),
                                                                'min-w-[10rem] px-4 text-right font-mono text-base font-semibold tracking-tight',
                                                            )
                                                        "
                                                        @blur="
                                                            normalizeEditAmount
                                                        "
                                                        @keydown.enter.prevent="
                                                            submitInlineEdit(
                                                                transaction.uuid,
                                                            )
                                                        "
                                                    />
                                                    <div
                                                        v-if="
                                                            !isEditTransfer &&
                                                            !isEditMove &&
                                                            (editExchangePreviewLoading ||
                                                                editExchangePreview?.should_preview ||
                                                                editExchangePreviewError)
                                                        "
                                                        class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-left dark:border-slate-800 dark:bg-slate-900/80"
                                                    >
                                                        <p
                                                            class="text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                                        >
                                                            {{
                                                                t(
                                                                    'transactions.form.helper.fxPreviewTitle',
                                                                )
                                                            }}
                                                        </p>
                                                        <p
                                                            v-if="
                                                                editExchangePreviewLoading
                                                            "
                                                            class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                                                        >
                                                            {{
                                                                t(
                                                                    'transactions.form.placeholders.balanceAdjustmentLoading',
                                                                )
                                                            }}
                                                        </p>
                                                        <template
                                                            v-else-if="
                                                                editExchangePreview &&
                                                                editExchangePreview.should_preview
                                                            "
                                                        >
                                                            <p
                                                                class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100"
                                                            >
                                                                {{
                                                                    t(
                                                                        'transactions.form.helper.fxPreviewAmount',
                                                                        {
                                                                            source: formatCurrency(
                                                                                editExchangePreview.amount_raw,
                                                                                editExchangePreview.currency_code,
                                                                                moneyFormatLocale,
                                                                            ),
                                                                            target: formatCurrency(
                                                                                editExchangePreview.converted_base_amount_raw,
                                                                                editExchangePreview.base_currency_code,
                                                                                moneyFormatLocale,
                                                                            ),
                                                                        },
                                                                    )
                                                                }}
                                                            </p>
                                                            <p
                                                                class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                                                            >
                                                                {{
                                                                    t(
                                                                        'transactions.form.helper.fxPreviewRateDate',
                                                                        {
                                                                            date: formatDateLong(
                                                                                editExchangePreview.exchange_rate_date,
                                                                            ),
                                                                        },
                                                                    )
                                                                }}
                                                            </p>
                                                        </template>
                                                        <p
                                                            v-else-if="
                                                                editExchangePreviewError
                                                            "
                                                            class="mt-1 text-xs text-rose-600 dark:text-rose-400"
                                                        >
                                                            {{
                                                                editExchangePreviewError
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3">
                                                <Input
                                                    v-model="
                                                        editForm.description
                                                    "
                                                    :disabled="isEditMove"
                                                    :placeholder="
                                                        t(
                                                            'transactions.form.labels.detail',
                                                        )
                                                    "
                                                    class="h-10 rounded-xl border-sky-200 bg-white dark:border-sky-500/20 dark:bg-slate-950/60"
                                                    @keydown.enter.prevent="
                                                        submitInlineEdit(
                                                            transaction.uuid,
                                                        )
                                                    "
                                                />
                                            </td>
                                            <td class="px-3 py-3">
                                                <div
                                                    v-if="isEditMove"
                                                    class="flex h-10 items-center rounded-xl border border-dashed border-sky-200 px-3 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:text-sky-300"
                                                >
                                                    {{
                                                        lockedMoveValue(
                                                            transaction.tracked_item_label ??
                                                                transaction.scope_label,
                                                        )
                                                    }}
                                                </div>
                                                <SearchableSelect
                                                    v-else-if="!isEditTransfer"
                                                    v-model="editReferenceValue"
                                                    :options="[
                                                        {
                                                            value: '',
                                                            label: t(
                                                                'transactions.sheet.grid.noSelection',
                                                            ),
                                                        },
                                                        ...editReferenceOptions,
                                                    ]"
                                                    :placeholder="
                                                        t(
                                                            'transactions.sheet.grid.columns.trackedItem',
                                                        )
                                                    "
                                                    :search-placeholder="
                                                        t(
                                                            'transactions.form.placeholders.searchTrackedItem',
                                                        )
                                                    "
                                                    :disabled="
                                                        editForm.type_key === ''
                                                    "
                                                    clearable
                                                    creatable
                                                    :creating="
                                                        creatingEditTrackedItem
                                                    "
                                                    :create-label="
                                                        t(
                                                            'transactions.form.placeholders.createTrackedItem',
                                                        )
                                                    "
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
                                                    {{
                                                        t(
                                                            'transactions.sheet.grid.transferBetweenAccounts',
                                                        )
                                                    }}
                                                </div>
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
                                                        {{
                                                            t(
                                                                'transactions.sheet.actions.save',
                                                            )
                                                        }}
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
                                                        {{
                                                            t(
                                                                'transactions.sheet.actions.cancel',
                                                            )
                                                        }}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr
                                            v-else
                                            :data-transaction-row="
                                                transaction.uuid
                                            "
                                            :class="
                                                cn(
                                                    'border-b border-slate-200/70 transition-colors hover:bg-slate-50/80 dark:border-white/8 dark:hover:bg-slate-900/60',
                                                    transactionRowToneClass(
                                                        transaction,
                                                    ),
                                                    transactionAccentClass(
                                                        transaction,
                                                    ),
                                                    canEdit &&
                                                        transaction.can_edit
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
                                                            formatDateNumeric(
                                                                transaction.date,
                                                            ) ??
                                                            t(
                                                                'transactions.sheet.grid.noDate',
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            creditCardCycleHelper(
                                                                transaction,
                                                            )
                                                        "
                                                        class="text-xs text-sky-700 dark:text-sky-300"
                                                    >
                                                        {{
                                                            creditCardCycleHelper(
                                                                transaction,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <div
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <Badge
                                                        v-if="
                                                            !transaction.is_opening_balance
                                                        "
                                                        :class="
                                                            cn(
                                                                'rounded-full px-2.5 py-1 text-[11px]',
                                                                transactionTypeBadgeTone(
                                                                    transaction,
                                                                ),
                                                            )
                                                        "
                                                    >
                                                        {{ transaction.type }}
                                                    </Badge>
                                                    <Badge
                                                        v-if="
                                                            transaction.is_opening_balance
                                                        "
                                                        class="rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-[11px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.sheet.grid.openingBadge',
                                                            )
                                                        }}
                                                    </Badge>
                                                    <TooltipProvider
                                                        v-if="
                                                            isBalanceAdjustmentTransaction(
                                                                transaction,
                                                            )
                                                        "
                                                        :delay-duration="0"
                                                    >
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <Badge
                                                                    :class="
                                                                        cn(
                                                                            'cursor-help rounded-full px-2.5 py-1 text-[11px]',
                                                                            balanceAdjustmentBadgeTone(
                                                                                transaction,
                                                                            ),
                                                                        )
                                                                    "
                                                                >
                                                                    <Scale
                                                                        class="mr-1 h-3.5 w-3.5"
                                                                    />
                                                                    {{
                                                                        t(
                                                                            'transactions.sheet.grid.balanceAdjustmentBadge',
                                                                        )
                                                                    }}
                                                                </Badge>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                align="start"
                                                                :collision-boundary="[]"
                                                                :update-position-strategy="'always'"
                                                                :avoid-collisions="
                                                                    true
                                                                "
                                                                :hide-when-detached="
                                                                    true
                                                                "
                                                                :position-strategy="'fixed'"
                                                                :arrow-padding="
                                                                    8
                                                                "
                                                                :sticky="'partial'"
                                                                :collision-padding="
                                                                    8
                                                                "
                                                                :align-offset="
                                                                    4
                                                                "
                                                                class="max-w-xs space-y-1"
                                                            >
                                                                <p
                                                                    class="font-medium"
                                                                >
                                                                    {{
                                                                        t(
                                                                            'transactions.sheet.grid.balanceAdjustmentTooltipTitle',
                                                                        )
                                                                    }}
                                                                </p>
                                                                <p>
                                                                    {{
                                                                        t(
                                                                            'transactions.sheet.grid.balanceAdjustmentTooltipBody',
                                                                        )
                                                                    }}
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <Badge
                                                        v-if="
                                                            isBalanceAdjustmentTransaction(
                                                                transaction,
                                                            )
                                                        "
                                                        :class="
                                                            cn(
                                                                'rounded-full px-2.5 py-1 text-[11px]',
                                                                balanceAdjustmentEffectTone(
                                                                    transaction,
                                                                ),
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            balanceAdjustmentEffectLabel(
                                                                transaction,
                                                            )
                                                        }}
                                                    </Badge>
                                                    <Badge
                                                        v-if="
                                                            recurringTransactionBadge(
                                                                transaction,
                                                            )
                                                        "
                                                        :class="
                                                            cn(
                                                                'rounded-full px-2.5 py-1 text-[11px]',
                                                                recurringTransactionBadge(
                                                                    transaction,
                                                                )?.tone,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            recurringTransactionBadge(
                                                                transaction,
                                                            )?.label
                                                        }}
                                                    </Badge>
                                                    <Badge
                                                        v-if="
                                                            transaction.is_deleted
                                                        "
                                                        class="rounded-full border border-slate-300 bg-slate-200/80 px-2.5 py-1 text-[11px] text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.sheet.grid.deletedBadge',
                                                            )
                                                        }}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <div
                                                    class="min-w-0 space-y-0.5"
                                                >
                                                    <p
                                                        class="truncate font-medium text-slate-900 dark:text-slate-100"
                                                    >
                                                        {{
                                                            transaction.account_label
                                                        }}
                                                    </p>
                                                    <p
                                                        class="break-words text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            transaction.is_transfer
                                                                ? transaction.direction ===
                                                                  'income'
                                                                    ? t(
                                                                          'transactions.sheet.grid.transferPath',
                                                                          {
                                                                              from:
                                                                                  transaction.related_account_label ??
                                                                                  t(
                                                                                      'transactions.sheet.filters.sourceAccount',
                                                                                  ),
                                                                              to: transaction.account_label,
                                                                          },
                                                                      )
                                                                    : t(
                                                                          'transactions.sheet.grid.transferPath',
                                                                          {
                                                                              from: transaction.account_label,
                                                                              to:
                                                                                  transaction.related_account_label ??
                                                                                  t(
                                                                                      'transactions.sheet.filters.destinationAccount',
                                                                                  ),
                                                                          },
                                                                      )
                                                                : '—'
                                                        }}
                                                    </p>
                                                </div>
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
                                                        class="truncate text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            transactionCategoryPath(
                                                                transaction,
                                                            )
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
                                                    <SensitiveValue
                                                        :value="
                                                            formatCurrency(
                                                                transaction.amount_raw,
                                                                transactionAmountCurrency(
                                                                    transaction,
                                                                ),
                                                                moneyFormatLocale,
                                                            )
                                                        "
                                                    />
                                                </span>
                                                <p
                                                    v-if="
                                                        transactionConvertedAmountLabel(
                                                            transaction,
                                                        )
                                                    "
                                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    <SensitiveValue
                                                        :value="
                                                            transactionConvertedAmountLabel(
                                                                transaction,
                                                            )
                                                        "
                                                    />
                                                </p>
                                                <p
                                                    v-if="
                                                        transactionExchangeRateContextLabel(
                                                            transaction,
                                                        )
                                                    "
                                                    class="mt-1 text-[11px] text-slate-400 dark:text-slate-500"
                                                >
                                                    {{
                                                        transactionExchangeRateContextLabel(
                                                            transaction,
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        isBalanceAdjustmentTransaction(
                                                            transaction,
                                                        )
                                                    "
                                                    class="mt-1 text-xs font-medium"
                                                    :class="
                                                        balanceAdjustmentEffectTone(
                                                            transaction,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        balanceAdjustmentEffectLabel(
                                                            transaction,
                                                        )
                                                    }}
                                                </p>
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
                                                            t(
                                                                'transactions.sheet.grid.noDetail',
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            transaction.detail ??
                                                            transaction.description ??
                                                            t(
                                                                'transactions.sheet.grid.noDetail',
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="transaction.notes"
                                                        class="truncate text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{ transaction.notes }}
                                                    </p>
                                                    <div
                                                        v-if="
                                                            recurringTransactionHelper(
                                                                transaction,
                                                            ) ||
                                                            transaction.recurring_entry_show_url
                                                        "
                                                        class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        <span
                                                            v-if="
                                                                recurringTransactionHelper(
                                                                    transaction,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                recurringTransactionHelper(
                                                                    transaction,
                                                                )
                                                            }}
                                                        </span>
                                                        <Link
                                                            v-if="
                                                                transaction.recurring_entry_show_url
                                                            "
                                                            :href="
                                                                transaction.recurring_entry_show_url
                                                            "
                                                            class="font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                                                        >
                                                            {{
                                                                t(
                                                                    'transactions.sheet.grid.recurringLink',
                                                                )
                                                            }}
                                                        </Link>
                                                    </div>
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
                                                          transaction.scope_label ??
                                                          '—')
                                                }}
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <div
                                                    class="flex justify-end gap-2"
                                                >
                                                    <TooltipProvider
                                                        v-if="
                                                            shouldShowTransactionAuditIcon(
                                                                transaction,
                                                            )
                                                        "
                                                        :delay-duration="0"
                                                    >
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <button
                                                                    type="button"
                                                                    :aria-label="
                                                                        t(
                                                                            'transactions.sheet.actions.auditInfo',
                                                                        )
                                                                    "
                                                                    class="inline-flex size-8 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
                                                                >
                                                                    <User
                                                                        class="size-4"
                                                                    />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                align="center"
                                                                :collision-boundary="[]"
                                                                :update-position-strategy="'always'"
                                                                :avoid-collisions="
                                                                    true
                                                                "
                                                                :hide-when-detached="
                                                                    true
                                                                "
                                                                :position-strategy="'fixed'"
                                                                :arrow-padding="
                                                                    8
                                                                "
                                                                :sticky="'partial'"
                                                                :collision-padding="
                                                                    8
                                                                "
                                                                :align-offset="
                                                                    4
                                                                "
                                                                class="max-w-xs space-y-1"
                                                            >
                                                                <p
                                                                    v-if="
                                                                        transactionAuditCreatedLabel(
                                                                            transaction,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        transactionAuditCreatedLabel(
                                                                            transaction,
                                                                        )
                                                                    }}
                                                                </p>
                                                                <p
                                                                    v-if="
                                                                        transactionAuditUpdatedLabel(
                                                                            transaction,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        transactionAuditUpdatedLabel(
                                                                            transaction,
                                                                        )
                                                                    }}
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <TooltipProvider
                                                        v-if="
                                                            transaction.kind ===
                                                                'scheduled' &&
                                                            transaction.recurring_entry_show_url
                                                        "
                                                        :delay-duration="0"
                                                    >
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                as-child
                                                            >
                                                                <Link
                                                                    :href="
                                                                        transaction.recurring_entry_show_url
                                                                    "
                                                                    :aria-label="
                                                                        t(
                                                                            'transactions.sheet.actions.openRecurring',
                                                                        )
                                                                    "
                                                                    class="inline-flex size-8 items-center justify-center rounded-xl text-sky-700 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-500/10"
                                                                >
                                                                    <ArrowUpRight
                                                                        class="size-4"
                                                                    />
                                                                </Link>
                                                            </TooltipTrigger>
                                                            <TooltipContent
                                                                side="top"
                                                                align="center"
                                                                :collision-boundary="[]"
                                                                :update-position-strategy="'always'"
                                                                :avoid-collisions="
                                                                    true
                                                                "
                                                                :hide-when-detached="
                                                                    true
                                                                "
                                                                :position-strategy="'fixed'"
                                                                :arrow-padding="
                                                                    8
                                                                "
                                                                :sticky="'partial'"
                                                                :collision-padding="
                                                                    8
                                                                "
                                                                :align-offset="
                                                                    4
                                                                "
                                                            >
                                                                <p>
                                                                    {{
                                                                        t(
                                                                            'transactions.sheet.actions.openRecurring',
                                                                        )
                                                                    }}
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <Button
                                                        v-if="
                                                            transaction.can_restore &&
                                                            canEdit
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 rounded-xl px-3 text-emerald-600 hover:text-emerald-700"
                                                        @click="
                                                            restoreTransaction(
                                                                transaction.uuid,
                                                            )
                                                        "
                                                    >
                                                        <RotateCcw
                                                            class="mr-2 size-4"
                                                        />
                                                        {{
                                                            t(
                                                                'transactions.sheet.actions.restore',
                                                            )
                                                        }}
                                                    </Button>
                                                    <Button
                                                        v-if="
                                                            transaction.can_force_delete &&
                                                            canEdit
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 rounded-xl px-3 text-rose-600 hover:text-rose-700"
                                                        @click="
                                                            requestForceDelete(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="mr-2 size-4"
                                                        />
                                                        {{
                                                            t(
                                                                'transactions.sheet.actions.forceDelete',
                                                            )
                                                        }}
                                                    </Button>
                                                    <Button
                                                        v-if="
                                                            transaction.can_undo_refund &&
                                                            canEdit
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 w-8 rounded-xl p-0 text-amber-600 hover:text-amber-700"
                                                        :aria-label="
                                                            t(
                                                                'transactions.sheet.actions.undoRefund',
                                                            )
                                                        "
                                                        @click="
                                                            undoRefund(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        <RotateCcw
                                                            class="size-4"
                                                        />
                                                    </Button>
                                                    <Button
                                                        v-if="
                                                            transaction.can_refund &&
                                                            canEdit
                                                        "
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 w-8 rounded-xl p-0"
                                                        :aria-label="
                                                            t(
                                                                'transactions.sheet.actions.refund',
                                                            )
                                                        "
                                                        @click="
                                                            requestRefund(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        <RefreshCcw
                                                            class="size-4"
                                                        />
                                                    </Button>
                                                    <Button
                                                        v-if="
                                                            transaction.can_delete &&
                                                            canEdit
                                                        "
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
                                                    :placeholder="
                                                        t(
                                                            'transactions.form.placeholders.day',
                                                        )
                                                    "
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
                                                    {{
                                                        t(
                                                            'transactions.sheet.grid.day',
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    v-if="visibleInlineDayError"
                                                    class="text-center text-[11px] text-rose-600 dark:text-rose-400"
                                                >
                                                    {{ visibleInlineDayError }}
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
                                                        :placeholder="
                                                            t(
                                                                'transactions.sheet.filters.type',
                                                            )
                                                        "
                                                    />
                                                </SelectTrigger>
                                                <SelectContent class="z-[170]">
                                                    <SelectItem
                                                        v-for="option in inlineCreateTypeOptions"
                                                        :key="option.value"
                                                        :value="option.value"
                                                    >
                                                        {{ option.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="space-y-2">
                                                <SearchableSelect
                                                    v-model="
                                                        inlineForm.account_uuid
                                                    "
                                                    :options="
                                                        inlineAccountOptions
                                                    "
                                                    :placeholder="
                                                        isInlineTransfer
                                                            ? t(
                                                                  'transactions.sheet.filters.sourceAccount',
                                                              )
                                                            : t(
                                                                  'transactions.sheet.filters.account',
                                                              )
                                                    "
                                                    :search-placeholder="
                                                        isInlineTransfer
                                                            ? t(
                                                                  'transactions.form.placeholders.searchSourceAccount',
                                                              )
                                                            : t(
                                                                  'transactions.form.placeholders.searchAccount',
                                                              )
                                                    "
                                                    clearable
                                                    :trigger-class="
                                                        inlineFieldClass(
                                                            'account_uuid',
                                                        )
                                                    "
                                                />
                                                <p
                                                    v-if="
                                                        inlineForm.account_uuid !==
                                                        ''
                                                    "
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.form.helper.accountCurrency',
                                                            {
                                                                currency:
                                                                    resolveFormCurrencyLabel(
                                                                        inlineForm.account_uuid,
                                                                    ),
                                                            },
                                                        )
                                                    }}
                                                </p>
                                                <div
                                                    v-if="
                                                        isInlineBalanceAdjustment
                                                    "
                                                    class="space-y-1"
                                                >
                                                    <div
                                                        class="flex h-10 items-center justify-end rounded-xl border border-dashed border-sky-200 px-3 text-sm font-semibold text-sky-700 dark:border-sky-500/30 dark:text-sky-300"
                                                    >
                                                        {{
                                                            inlineBalanceAdjustmentCurrentBalanceLoading
                                                                ? t(
                                                                      'transactions.form.placeholders.balanceAdjustmentLoading',
                                                                  )
                                                                : inlineBalanceAdjustmentCurrentBalanceRaw !==
                                                                    null
                                                                  ? formatCurrency(
                                                                        inlineBalanceAdjustmentCurrentBalanceRaw,
                                                                        resolveFormCurrency(
                                                                            inlineForm.account_uuid,
                                                                        ),
                                                                    )
                                                                  : t(
                                                                        'transactions.form.placeholders.balanceAdjustmentPending',
                                                                    )
                                                        }}
                                                    </div>
                                                    <p
                                                        class="text-center text-[10px] font-medium tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.sheet.grid.balanceAdjustmentCurrentBalanceLabel',
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div
                                                v-if="isInlineBalanceAdjustment"
                                                class="flex h-10 items-center rounded-xl border border-dashed border-sky-200 px-3 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:text-sky-300"
                                            >
                                                {{
                                                    t(
                                                        'transactions.sheet.grid.balanceAdjustmentBadge',
                                                    )
                                                }}
                                            </div>
                                            <SearchableSelect
                                                v-else-if="!isInlineTransfer"
                                                v-model="
                                                    inlineForm.category_uuid
                                                "
                                                :options="inlineCategories"
                                                :placeholder="
                                                    t(
                                                        'transactions.sheet.filters.category',
                                                    )
                                                "
                                                :search-placeholder="
                                                    t(
                                                        'transactions.sheet.filters.searchCategory',
                                                    )
                                                "
                                                :disabled="
                                                    inlineForm.type_key === ''
                                                "
                                                clearable
                                                hierarchical
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
                                                    inlineDestinationAccountOptions
                                                "
                                                :placeholder="
                                                    t(
                                                        'transactions.sheet.filters.destinationAccount',
                                                    )
                                                "
                                                :search-placeholder="
                                                    t(
                                                        'transactions.sheet.filters.searchDestinationAccount',
                                                    )
                                                "
                                                clearable
                                                :trigger-class="
                                                    inlineFieldClass(
                                                        'destination_account_uuid',
                                                    )
                                                "
                                            />
                                        </td>
                                        <td
                                            class="w-[11.5rem] min-w-[11.5rem] px-3 py-3"
                                        >
                                            <div class="space-y-2">
                                                <MoneyInput
                                                    v-if="
                                                        !isInlineBalanceAdjustment
                                                    "
                                                    v-model="inlineForm.amount"
                                                    :format-locale="
                                                        moneyFormatLocale
                                                    "
                                                    :currency-code="
                                                        resolveFormCurrency(
                                                            inlineForm.account_uuid,
                                                        )
                                                    "
                                                    placeholder="0,00"
                                                    :class="
                                                        cn(
                                                            inlineFieldClass(
                                                                'amount',
                                                            ),
                                                            'min-w-[10rem] px-4 text-right font-mono text-base font-semibold tracking-tight',
                                                        )
                                                    "
                                                    @blur="
                                                        normalizeInlineAmount
                                                    "
                                                    @keydown.enter.prevent="
                                                        submitInlineTransaction
                                                    "
                                                />
                                                <template v-else>
                                                    <MoneyInput
                                                        v-model="
                                                            inlineForm.desired_balance
                                                        "
                                                        :format-locale="
                                                            moneyFormatLocale
                                                        "
                                                        :currency-code="
                                                            resolveFormCurrency(
                                                                inlineForm.account_uuid,
                                                            )
                                                        "
                                                        placeholder="0,00"
                                                        :class="
                                                            cn(
                                                                inlineFieldClass(
                                                                    'desired_balance',
                                                                ),
                                                                'min-w-[10rem] px-4 text-right font-mono text-base font-semibold tracking-tight',
                                                            )
                                                        "
                                                        @blur="
                                                            normalizeInlineDesiredBalance
                                                        "
                                                        @keydown.enter.prevent="
                                                            submitInlineTransaction
                                                        "
                                                    />
                                                    <p
                                                        class="text-center text-[10px] font-medium tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.sheet.grid.balanceAdjustmentCurrentAmountLabel',
                                                            )
                                                        }}
                                                    </p>
                                                </template>
                                                <div
                                                    v-if="
                                                        !isInlineBalanceAdjustment &&
                                                        !isInlineTransfer &&
                                                        (inlineExchangePreviewLoading ||
                                                            inlineExchangePreview?.should_preview ||
                                                            inlineExchangePreviewError)
                                                    "
                                                    class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-left dark:border-slate-800 dark:bg-slate-900/80"
                                                >
                                                    <p
                                                        class="text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.form.helper.fxPreviewTitle',
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            inlineExchangePreviewLoading
                                                        "
                                                        class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.form.placeholders.balanceAdjustmentLoading',
                                                            )
                                                        }}
                                                    </p>
                                                    <template
                                                        v-else-if="
                                                            inlineExchangePreview &&
                                                            inlineExchangePreview.should_preview
                                                        "
                                                    >
                                                        <p
                                                            class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100"
                                                        >
                                                            {{
                                                                t(
                                                                    'transactions.form.helper.fxPreviewAmount',
                                                                    {
                                                                        source: formatCurrency(
                                                                            inlineExchangePreview.amount_raw,
                                                                            inlineExchangePreview.currency_code,
                                                                            moneyFormatLocale,
                                                                        ),
                                                                        target: formatCurrency(
                                                                            inlineExchangePreview.converted_base_amount_raw,
                                                                            inlineExchangePreview.base_currency_code,
                                                                            moneyFormatLocale,
                                                                        ),
                                                                    },
                                                                )
                                                            }}
                                                        </p>
                                                        <p
                                                            class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                                                        >
                                                            {{
                                                                t(
                                                                    'transactions.form.helper.fxPreviewRateDate',
                                                                    {
                                                                        date: formatDateLong(
                                                                            inlineExchangePreview.exchange_rate_date,
                                                                        ),
                                                                    },
                                                                )
                                                            }}
                                                        </p>
                                                    </template>
                                                    <p
                                                        v-else-if="
                                                            inlineExchangePreviewError
                                                        "
                                                        class="mt-1 text-xs text-rose-600 dark:text-rose-400"
                                                    >
                                                        {{
                                                            inlineExchangePreviewError
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <Input
                                                v-model="inlineForm.description"
                                                :placeholder="
                                                    t(
                                                        'transactions.form.labels.detail',
                                                    )
                                                "
                                                class="h-10 rounded-xl border-sky-200 bg-white dark:border-sky-500/20 dark:bg-slate-950/60"
                                                @keydown.enter.prevent="
                                                    submitInlineTransaction
                                                "
                                            />
                                        </td>
                                        <td class="px-3 py-3">
                                            <div
                                                v-if="isInlineBalanceAdjustment"
                                                class="flex h-10 items-center rounded-xl border border-dashed border-sky-200 px-3 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:text-sky-300"
                                            >
                                                {{
                                                    t(
                                                        'transactions.sheet.grid.balanceAdjustmentBadge',
                                                    )
                                                }}
                                            </div>
                                            <SearchableSelect
                                                v-else-if="!isInlineTransfer"
                                                v-model="inlineReferenceValue"
                                                :options="[
                                                    {
                                                        value: '',
                                                        label: t(
                                                            'transactions.sheet.grid.noSelection',
                                                        ),
                                                    },
                                                    ...inlineReferenceOptions,
                                                ]"
                                                :placeholder="
                                                    t(
                                                        'transactions.sheet.grid.columns.trackedItem',
                                                    )
                                                "
                                                :search-placeholder="
                                                    t(
                                                        'transactions.form.placeholders.searchTrackedItem',
                                                    )
                                                "
                                                :disabled="
                                                    inlineForm.type_key === ''
                                                "
                                                clearable
                                                creatable
                                                :creating="
                                                    creatingInlineTrackedItem
                                                "
                                                :create-label="
                                                    t(
                                                        'transactions.form.placeholders.createTrackedItem',
                                                    )
                                                "
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
                                                {{
                                                    t(
                                                        'transactions.sheet.grid.transferBetweenAccounts',
                                                    )
                                                }}
                                            </div>
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
                                                    {{
                                                        t(
                                                            'transactions.sheet.actions.save',
                                                        )
                                                    }}
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
                                            {{
                                                t(
                                                    'transactions.sheet.grid.readOnlyClosedYear',
                                                )
                                            }}
                                        </td>
                                    </tr>

                                    <tr
                                        v-if="
                                            displayedTransactions.length === 0
                                        "
                                    >
                                        <td
                                            colspan="8"
                                            class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.sheet.grid.emptyState',
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-3 p-4 xl:hidden">
                            <Card
                                v-if="canEdit"
                                class="hidden border-sky-200/80 bg-sky-50/70 shadow-none md:block dark:border-sky-500/20 dark:bg-sky-500/5"
                            >
                                <CardContent class="space-y-3 p-4">
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-white"
                                    >
                                        {{
                                            t(
                                                'transactions.sheet.grid.mobileCreateTitle',
                                            )
                                        }}
                                    </p>
                                    <Button
                                        type="button"
                                        class="w-full rounded-2xl"
                                        @click="openCreate"
                                    >
                                        <Plus class="mr-2 size-4" />
                                        {{
                                            t(
                                                'transactions.sheet.actions.openCreate',
                                            )
                                        }}
                                    </Button>
                                </CardContent>
                            </Card>

                            <Card
                                v-for="transaction in displayedTransactions"
                                :key="transaction.uuid"
                                :data-transaction-row="transaction.uuid"
                                :class="
                                    cn(
                                        'border-slate-200/80 bg-white/95 shadow-none transition-all duration-500 dark:border-white/10 dark:bg-slate-950/80',
                                        transactionRowToneClass(transaction),
                                        transactionAccentClass(transaction),
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
                                            <div class="flex flex-wrap gap-2">
                                                <Badge
                                                    v-if="
                                                        !transaction.is_opening_balance
                                                    "
                                                    :class="
                                                        cn(
                                                            'rounded-full px-2.5 py-1 text-[11px]',
                                                            transactionTypeBadgeTone(
                                                                transaction,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{ transaction.type }}
                                                </Badge>
                                                <Badge
                                                    v-if="
                                                        transaction.is_opening_balance
                                                    "
                                                    class="rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-[11px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.sheet.grid.openingBadge',
                                                        )
                                                    }}
                                                </Badge>
                                                <TooltipProvider
                                                    v-if="
                                                        isBalanceAdjustmentTransaction(
                                                            transaction,
                                                        )
                                                    "
                                                    :delay-duration="0"
                                                >
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            as-child
                                                        >
                                                            <Badge
                                                                :class="
                                                                    cn(
                                                                        'cursor-help rounded-full px-2.5 py-1 text-[11px]',
                                                                        balanceAdjustmentBadgeTone(
                                                                            transaction,
                                                                        ),
                                                                    )
                                                                "
                                                            >
                                                                <Scale
                                                                    class="mr-1 h-3.5 w-3.5"
                                                                />
                                                                {{
                                                                    t(
                                                                        'transactions.sheet.grid.balanceAdjustmentBadge',
                                                                    )
                                                                }}
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent
                                                            side="top"
                                                            align="start"
                                                            :collision-boundary="[]"
                                                            :update-position-strategy="'always'"
                                                            :avoid-collisions="
                                                                true
                                                            "
                                                            :hide-when-detached="
                                                                true
                                                            "
                                                            :position-strategy="'fixed'"
                                                            :arrow-padding="8"
                                                            :sticky="'partial'"
                                                            :collision-padding="
                                                                8
                                                            "
                                                            :align-offset="4"
                                                            class="max-w-xs space-y-1"
                                                        >
                                                            <p
                                                                class="font-medium"
                                                            >
                                                                {{
                                                                    t(
                                                                        'transactions.sheet.grid.balanceAdjustmentTooltipTitle',
                                                                    )
                                                                }}
                                                            </p>
                                                            <p>
                                                                {{
                                                                    t(
                                                                        'transactions.sheet.grid.balanceAdjustmentTooltipBody',
                                                                    )
                                                                }}
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <Badge
                                                    v-if="
                                                        isBalanceAdjustmentTransaction(
                                                            transaction,
                                                        )
                                                    "
                                                    :class="
                                                        cn(
                                                            'rounded-full px-2.5 py-1 text-[11px]',
                                                            balanceAdjustmentEffectTone(
                                                                transaction,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{
                                                        balanceAdjustmentEffectLabel(
                                                            transaction,
                                                        )
                                                    }}
                                                </Badge>
                                                <Badge
                                                    v-if="
                                                        recurringTransactionBadge(
                                                            transaction,
                                                        )
                                                    "
                                                    :class="
                                                        cn(
                                                            'rounded-full px-2.5 py-1 text-[11px]',
                                                            recurringTransactionBadge(
                                                                transaction,
                                                            )?.tone,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        recurringTransactionBadge(
                                                            transaction,
                                                        )?.label
                                                    }}
                                                </Badge>
                                                <Badge
                                                    v-if="
                                                        transaction.is_deleted
                                                    "
                                                    class="rounded-full border border-slate-300 bg-slate-200/80 px-2.5 py-1 text-[11px] text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.sheet.grid.deletedBadge',
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                            <p
                                                class="font-medium text-slate-950 dark:text-slate-100"
                                            >
                                                {{
                                                    transactionCategoryLabel(
                                                        transaction,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="truncate text-sm text-slate-600 dark:text-slate-300"
                                                :title="
                                                    transaction.detail ??
                                                    transaction.description ??
                                                    t(
                                                        'transactions.sheet.grid.noDetail',
                                                    )
                                                "
                                            >
                                                {{
                                                    transaction.detail ??
                                                    transaction.description ??
                                                    t(
                                                        'transactions.sheet.grid.noDetail',
                                                    )
                                                }}
                                            </p>
                                            <div
                                                v-if="
                                                    recurringTransactionHelper(
                                                        transaction,
                                                    ) ||
                                                    transaction.recurring_entry_show_url
                                                "
                                                class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                <span
                                                    v-if="
                                                        recurringTransactionHelper(
                                                            transaction,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        recurringTransactionHelper(
                                                            transaction,
                                                        )
                                                    }}
                                                </span>
                                                <Link
                                                    v-if="
                                                        transaction.recurring_entry_show_url
                                                    "
                                                    :href="
                                                        transaction.recurring_entry_show_url
                                                    "
                                                    class="font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.sheet.grid.recurringLink',
                                                        )
                                                    }}
                                                </Link>
                                            </div>
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
                                                <SensitiveValue
                                                    :value="
                                                        formatCurrency(
                                                            transaction.amount_raw,
                                                            transactionAmountCurrency(
                                                                transaction,
                                                            ),
                                                            moneyFormatLocale,
                                                        )
                                                    "
                                                />
                                            </p>
                                            <p
                                                v-if="
                                                    transactionConvertedAmountLabel(
                                                        transaction,
                                                    )
                                                "
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                <SensitiveValue
                                                    :value="
                                                        transactionConvertedAmountLabel(
                                                            transaction,
                                                        )
                                                    "
                                                />
                                            </p>
                                            <p
                                                v-if="
                                                    transactionExchangeRateContextLabel(
                                                        transaction,
                                                    )
                                                "
                                                class="text-[11px] text-slate-400 dark:text-slate-500"
                                            >
                                                {{
                                                    transactionExchangeRateContextLabel(
                                                        transaction,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    isBalanceAdjustmentTransaction(
                                                        transaction,
                                                    )
                                                "
                                                class="text-xs font-medium"
                                                :class="
                                                    balanceAdjustmentEffectTone(
                                                        transaction,
                                                    )
                                                "
                                            >
                                                {{
                                                    balanceAdjustmentEffectLabel(
                                                        transaction,
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
                                            <p
                                                v-if="
                                                    creditCardCycleHelper(
                                                        transaction,
                                                    )
                                                "
                                                class="text-xs text-sky-700 dark:text-sky-300"
                                            >
                                                {{
                                                    creditCardCycleHelper(
                                                        transaction,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="grid gap-2 text-xs text-slate-500 sm:grid-cols-2 dark:text-slate-400"
                                    >
                                        <div>
                                            {{
                                                t(
                                                    'transactions.sheet.grid.accountLabel',
                                                )
                                            }}
                                            <span
                                                class="break-words text-slate-700 dark:text-slate-200"
                                                >{{
                                                    transaction.account_label
                                                }}</span
                                            >
                                        </div>
                                        <div>
                                            {{
                                                transaction.is_transfer
                                                    ? t(
                                                          'transactions.sheet.grid.linkedAccountLabel',
                                                      )
                                                    : transaction.is_opening_balance
                                                      ? t(
                                                            'transactions.sheet.grid.openingReadOnly',
                                                        )
                                                      : t(
                                                            'transactions.sheet.grid.trackedItemLabel',
                                                        )
                                            }}
                                            <span
                                                class="break-words text-slate-700 dark:text-slate-200"
                                            >
                                                {{
                                                    transaction.is_transfer
                                                        ? (transaction.related_account_label ??
                                                          '—')
                                                        : transaction.is_opening_balance
                                                          ? '—'
                                                          : (transaction.tracked_item_label ??
                                                            '—')
                                                }}
                                            </span>
                                        </div>
                                        <div>
                                            {{
                                                t(
                                                    'transactions.sheet.grid.balanceLabel',
                                                )
                                            }}
                                            <span
                                                class="text-slate-700 dark:text-slate-200"
                                            >
                                                <SensitiveValue
                                                    :value="
                                                        transaction.balance_after_raw ===
                                                        null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  transaction.balance_after_raw,
                                                                  currency,
                                                              )
                                                    "
                                                />
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            transactionHasAuditDetails(
                                                transaction,
                                            ) ||
                                            (transaction.kind === 'scheduled' &&
                                                transaction.recurring_entry_show_url) ||
                                            (canEdit &&
                                                (transaction.can_edit ||
                                                    transaction.can_refund ||
                                                    transaction.can_undo_refund ||
                                                    transaction.can_delete ||
                                                    transaction.can_restore ||
                                                    transaction.can_force_delete))
                                        "
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <TooltipProvider
                                            v-if="
                                                shouldShowTransactionAuditIcon(
                                                    transaction,
                                                )
                                            "
                                            :delay-duration="0"
                                        >
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <button
                                                        type="button"
                                                        :aria-label="
                                                            t(
                                                                'transactions.sheet.actions.auditInfo',
                                                            )
                                                        "
                                                        class="inline-flex size-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
                                                    >
                                                        <User class="size-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent
                                                    side="top"
                                                    align="center"
                                                    :collision-boundary="[]"
                                                    :update-position-strategy="'always'"
                                                    :avoid-collisions="true"
                                                    :hide-when-detached="true"
                                                    :position-strategy="'fixed'"
                                                    :arrow-padding="8"
                                                    :sticky="'partial'"
                                                    :collision-padding="8"
                                                    :align-offset="4"
                                                    class="max-w-xs space-y-1"
                                                >
                                                    <p
                                                        v-if="
                                                            transactionAuditCreatedLabel(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            transactionAuditCreatedLabel(
                                                                transaction,
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            transactionAuditUpdatedLabel(
                                                                transaction,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            transactionAuditUpdatedLabel(
                                                                transaction,
                                                            )
                                                        }}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <TooltipProvider
                                            v-if="
                                                transaction.kind ===
                                                    'scheduled' &&
                                                transaction.recurring_entry_show_url
                                            "
                                            :delay-duration="0"
                                        >
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Link
                                                        :href="
                                                            transaction.recurring_entry_show_url
                                                        "
                                                        :aria-label="
                                                            t(
                                                                'transactions.sheet.actions.openRecurring',
                                                            )
                                                        "
                                                        class="inline-flex size-9 items-center justify-center rounded-xl border border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-500/20 dark:text-sky-300 dark:hover:bg-sky-500/10"
                                                    >
                                                        <ArrowUpRight
                                                            class="size-4"
                                                        />
                                                    </Link>
                                                </TooltipTrigger>
                                                <TooltipContent
                                                    side="top"
                                                    align="center"
                                                    :collision-boundary="[]"
                                                    :update-position-strategy="'always'"
                                                    :avoid-collisions="true"
                                                    :hide-when-detached="true"
                                                    :position-strategy="'fixed'"
                                                    :arrow-padding="8"
                                                    :sticky="'partial'"
                                                    :collision-padding="8"
                                                    :align-offset="4"
                                                >
                                                    <p>
                                                        {{
                                                            t(
                                                                'transactions.sheet.actions.openRecurring',
                                                            )
                                                        }}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <Button
                                            v-if="transaction.can_edit"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl"
                                            @click="openEdit(transaction)"
                                        >
                                            <Pencil class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'transactions.sheet.actions.edit',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="transaction.can_refund"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl"
                                            @click="requestRefund(transaction)"
                                        >
                                            <RefreshCcw class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'transactions.sheet.actions.refund',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="transaction.can_undo_refund"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl text-amber-600 hover:text-amber-700"
                                            @click="undoRefund(transaction)"
                                        >
                                            <RotateCcw class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'transactions.sheet.actions.undoRefund',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="transaction.can_restore"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl text-emerald-600 hover:text-emerald-700"
                                            @click="
                                                restoreTransaction(
                                                    transaction.uuid,
                                                )
                                            "
                                        >
                                            <RotateCcw class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'transactions.sheet.actions.restore',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="transaction.can_force_delete"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl text-rose-600 hover:text-rose-700"
                                            @click="
                                                requestForceDelete(transaction)
                                            "
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'transactions.sheet.actions.forceDelete',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="transaction.can_delete"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="rounded-xl text-rose-600 hover:text-rose-700"
                                            @click="requestDelete(transaction)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            {{
                                                t(
                                                    'transactions.sheet.actions.delete',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            <div
                                v-if="displayedTransactions.length === 0"
                                class="py-12 text-center text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('transactions.sheet.grid.emptyState') }}
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
                                    {{ t('transactions.sheet.overview.title') }}
                                </CardTitle>
                                <p
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t(
                                            'transactions.sheet.overview.description',
                                        )
                                    }}
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
                                                {{
                                                    t(
                                                        'transactions.sheet.overview.current',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-slate-950 dark:text-white"
                                            >
                                                <SensitiveValue
                                                    variant="veil"
                                                    :value="
                                                        formatCurrency(
                                                            categoryFocus.item
                                                                .actual_raw,
                                                            currency,
                                                        )
                                                    "
                                                />
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.sheet.overview.planned',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-slate-950 dark:text-white"
                                            >
                                                <SensitiveValue
                                                    variant="veil"
                                                    :value="
                                                        formatCurrency(
                                                            categoryFocus.item
                                                                .budget_raw,
                                                            currency,
                                                        )
                                                    "
                                                />
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.sheet.overview.remaining',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-emerald-700 dark:text-emerald-300"
                                            >
                                                <SensitiveValue
                                                    variant="veil"
                                                    :value="
                                                        formatCurrency(
                                                            categoryFocus.item
                                                                .remaining_raw,
                                                            currency,
                                                        )
                                                    "
                                                />
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.sheet.overview.excess',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-base font-semibold text-rose-700 dark:text-rose-300"
                                            >
                                                <SensitiveValue
                                                    variant="veil"
                                                    :value="
                                                        formatCurrency(
                                                            categoryFocus.item
                                                                .excess_raw,
                                                            currency,
                                                        )
                                                    "
                                                />
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
                                    {{
                                        t(
                                            'transactions.sheet.overview.highlightedGroup',
                                        )
                                    }}
                                </template>
                                <template v-else>
                                    {{
                                        t(
                                            'transactions.sheet.overview.defaultDescription',
                                        )
                                    }}
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
                                                        t(
                                                            'transactions.sheet.overview.records',
                                                            {
                                                                count: group.count,
                                                            },
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p
                                                    class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'transactions.sheet.overview.progress',
                                                        )
                                                    }}
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
                                                        {{
                                                            t(
                                                                'transactions.sheet.overview.current',
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold text-slate-950 dark:text-white"
                                                    >
                                                        <SensitiveValue
                                                            :value="
                                                                formatCurrency(
                                                                    group.actual_raw,
                                                                    currency,
                                                                )
                                                            "
                                                        />
                                                    </p>
                                                </div>
                                                <div>
                                                    <p
                                                        class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        {{
                                                            t(
                                                                'transactions.sheet.overview.planned',
                                                            )
                                                        }}
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold text-slate-950 dark:text-white"
                                                    >
                                                        <SensitiveValue
                                                            :value="
                                                                formatCurrency(
                                                                    group.budget_raw,
                                                                    currency,
                                                                )
                                                            "
                                                        />
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
                                                            {{
                                                                t(
                                                                    'transactions.sheet.overview.remaining',
                                                                )
                                                            }}
                                                        </p>
                                                        <p
                                                            class="mt-1 font-semibold text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            <SensitiveValue
                                                                :value="
                                                                    formatCurrency(
                                                                        group.remaining_raw,
                                                                        currency,
                                                                    )
                                                                "
                                                            />
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p
                                                            class="tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                                        >
                                                            {{
                                                                t(
                                                                    'transactions.sheet.overview.excess',
                                                                )
                                                            }}
                                                        </p>
                                                        <p
                                                            class="mt-1 font-semibold text-rose-700 dark:text-rose-300"
                                                        >
                                                            <SensitiveValue
                                                                :value="
                                                                    formatCurrency(
                                                                        group.excess_raw,
                                                                        currency,
                                                                    )
                                                                "
                                                            />
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
                @request-refund="requestRefund"
            />

            <Dialog
                :open="refundingTransaction !== null"
                @update:open="
                    (value) => {
                        if (!value) {
                            refundingTransaction = null;
                            refundForm.clearErrors();
                        }
                    }
                "
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{
                            t('transactions.sheet.dialog.refundTitle')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                t('transactions.sheet.dialog.refundDescription')
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div
                            v-if="refundingTransaction"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-white/10 dark:bg-slate-900/60"
                        >
                            <p
                                class="font-medium text-slate-950 dark:text-white"
                            >
                                {{
                                    transactionCategoryLabel(
                                        refundingTransaction,
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-slate-600 dark:text-slate-300">
                                {{
                                    refundingTransaction.detail ??
                                    refundingTransaction.description ??
                                    t('transactions.sheet.grid.noDetail')
                                }}
                            </p>
                            <p
                                class="mt-3 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ formatDateLong(refundingTransaction.date) }}
                                ·
                                <SensitiveValue
                                    :value="
                                        formatCurrency(
                                            refundingTransaction.amount_raw,
                                            currency,
                                        )
                                    "
                                />
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                for="refund-transaction-date"
                            >
                                {{ t('transactions.sheet.dialog.refundDate') }}
                            </label>
                            <Input
                                id="refund-transaction-date"
                                v-model="refundForm.transaction_date"
                                type="date"
                                :min="moveDateMin"
                                :max="moveDateMax"
                            />
                            <p
                                v-if="refundForm.errors.transaction_date"
                                class="text-sm text-rose-600 dark:text-rose-300"
                            >
                                {{ refundForm.errors.transaction_date }}
                            </p>
                            <p
                                v-if="refundTransactionError"
                                class="text-sm text-rose-600 dark:text-rose-300"
                            >
                                {{ refundTransactionError }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="refundingTransaction = null"
                        >
                            {{ t('transactions.sheet.actions.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            class="rounded-2xl"
                            @click="confirmRefund"
                        >
                            {{ t('transactions.sheet.dialog.refundConfirm') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
                        <DialogTitle>{{
                            t('transactions.sheet.dialog.deleteTitle')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                t('transactions.sheet.dialog.deleteDescription')
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deletingTransaction"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-white/10 dark:bg-slate-900/60"
                    >
                        <p class="font-medium text-slate-950 dark:text-white">
                            {{ transactionCategoryLabel(deletingTransaction) }}
                        </p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">
                            {{
                                deletingTransaction.detail ??
                                deletingTransaction.description ??
                                t('transactions.sheet.grid.noDetail')
                            }}
                        </p>
                        <p
                            class="mt-3 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ formatDateLong(deletingTransaction.date) }} ·
                            <SensitiveValue
                                :value="
                                    formatCurrency(
                                        deletingTransaction.amount_raw,
                                        currency,
                                    )
                                "
                            />
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="deletingTransaction = null"
                        >
                            {{ t('transactions.sheet.actions.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            class="rounded-2xl bg-rose-600 text-white hover:bg-rose-700"
                            @click="confirmDelete"
                        >
                            {{ t('transactions.sheet.actions.deleteRow') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="forceDeletingTransaction !== null"
                @update:open="
                    (value) => {
                        if (!value) {
                            forceDeletingTransaction = null;
                        }
                    }
                "
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{
                            t('transactions.sheet.forceDeleteDialog.title')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                t(
                                    'transactions.sheet.forceDeleteDialog.description',
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="forceDeletingTransaction"
                        class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm dark:border-rose-500/20 dark:bg-rose-500/10"
                    >
                        <p class="font-medium text-rose-950 dark:text-rose-100">
                            {{
                                transactionCategoryLabel(
                                    forceDeletingTransaction,
                                ) ?? forceDeletingTransaction.account_label
                            }}
                        </p>
                        <p class="mt-1 text-rose-700 dark:text-rose-200">
                            {{
                                forceDeletingTransaction.detail ??
                                forceDeletingTransaction.description ??
                                t('transactions.sheet.grid.noDetail')
                            }}
                        </p>
                        <p
                            class="mt-3 text-xs text-rose-700/80 dark:text-rose-200/80"
                        >
                            {{ formatDateLong(forceDeletingTransaction.date) }}
                            ·
                            <SensitiveValue
                                :value="
                                    formatCurrency(
                                        forceDeletingTransaction.amount_raw,
                                        currency,
                                    )
                                "
                            />
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="forceDeletingTransaction = null"
                        >
                            {{
                                t('transactions.sheet.forceDeleteDialog.cancel')
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            class="rounded-2xl"
                            @click="confirmForceDelete"
                        >
                            {{
                                t(
                                    'transactions.sheet.forceDeleteDialog.confirm',
                                )
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
