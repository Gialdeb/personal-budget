<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { previewExchangeSnapshot } from '@/actions/App/Http/Controllers/TransactionsController';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import MobileTextFieldEditor from '@/components/MobileTextFieldEditor.vue';
import { Button } from '@/components/ui/button';
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
import { useMobileSheetViewport } from '@/composables/useMobileSheetViewport';
import { formatCurrency, formatCurrencyLabel } from '@/lib/currency';
import type {
    MonthlyTransactionSheetData,
    MonthlyTransactionSheetTrackedItemOption,
    MonthlyTransactionSheetTransaction,
} from '@/types';

type ReferenceOption = {
    value: string;
    label: string;
};

type AccountSelectOption = {
    value: string;
    label: string;
    groupLabel?: string;
    badgeLabel?: string;
    badgeClass?: string;
};

type TypeOption = {
    value: string;
    label: string;
    create_only?: boolean;
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
    props.sheet.filters.available_years.map((option) => option.value),
);
const moveDateMin = computed(() => {
    const firstYear = moveAvailableYears.value[0];

    return firstYear ? `${firstYear}-01-01` : undefined;
});
const moveDateMax = computed(() => {
    const lastYear = moveAvailableYears.value.at(-1);

    return lastYear ? `${lastYear}-12-31` : undefined;
});
const { locale, t } = useI18n();
const page = usePage();
const isMobile = useMediaQuery('(max-width: 767px)');
const { mobileFooterStyle, mobileScrollStyle, handleFocusIn } =
    useMobileSheetViewport();

const props = defineProps<{
    open: boolean;
    year: number;
    month: number;
    sheet: MonthlyTransactionSheetData;
    transaction?: MonthlyTransactionSheetTransaction | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
    'request-refund': [transaction: MonthlyTransactionSheetTransaction];
}>();

const form = useForm({
    transaction_day: '',
    target_month: '',
    transaction_date: '',
    type_key: 'expense',
    category_uuid: '',
    destination_account_uuid: '',
    account_uuid: '',
    scope_uuid: '',
    tracked_item_uuid: '',
    amount: '',
    desired_balance: '',
    description: '',
    notes: '',
});
const creatingTrackedItem = ref(false);
const mobileDescriptionEditorOpen = ref(false);
const mobileNotesEditorOpen = ref(false);
const trackedItemCatalog = ref<MonthlyTransactionSheetTrackedItemOption[]>([]);
const balanceAdjustmentPreview = ref<{
    theoretical_balance_raw: number;
    desired_balance_raw: number;
    adjustment_amount_raw: number;
    direction: string;
} | null>(null);
const accountCurrentBalance = ref<number | null>(null);
const accountCurrentBalanceLoading = ref(false);
const balanceAdjustmentLoading = ref(false);
const exchangePreview = ref<TransactionExchangePreview | null>(null);
const exchangePreviewError = ref<string | null>(null);
const exchangePreviewLoading = ref(false);

const isEditing = computed(
    () => props.transaction !== null && props.transaction !== undefined,
);

const title = computed(() =>
    isEditing.value
        ? t('transactions.form.titleEdit')
        : t('transactions.form.titleNew'),
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

const adjustmentTypeOptions = computed<TypeOption[]>(() => {
    const options = props.sheet.editor.type_options.filter(
        (option) => !isEditing.value || option.create_only !== true,
    );

    if (isEditing.value && props.transaction?.can_refund) {
        options.push({
            value: refundTypeKey,
            label: t('transactions.form.actions.refund'),
        });
    }

    if (!isEditing.value || !canMoveTransaction(props.transaction)) {
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

const description = computed(() =>
    isEditing.value
        ? t('transactions.form.descriptionEdit')
        : t('transactions.form.descriptionNew'),
);

function resolveDefaultAccountUuid(): string {
    const defaultAccountUuid = props.sheet.editor.default_account_uuid;

    if (
        defaultAccountUuid &&
        props.sheet.editor.accounts.some(
            (account) => account.value === defaultAccountUuid,
        )
    ) {
        return defaultAccountUuid;
    }

    return props.sheet.editor.accounts[0]?.value ?? '';
}

function resolveAccountCategoryContributorUserIds(
    accountUuid: string,
): number[] {
    if (accountUuid === '') {
        return [];
    }

    return (
        props.sheet.editor.accounts.find(
            (account) => account.value === accountUuid,
        )?.category_contributor_user_ids ?? []
    );
}

function resolveAccountScopeContributorUserIds(accountUuid: string): number[] {
    if (accountUuid === '') {
        return [];
    }

    return (
        props.sheet.editor.accounts.find(
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
        props.sheet.editor.accounts.find(
            (account) => account.value === accountUuid,
        )?.tracked_item_contributor_user_ids ?? []
    );
}

function categoriesForSelectedAccount(accountUuid: string) {
    if (accountUuid === '') {
        return [];
    }

    return props.sheet.editor.categories[accountUuid] ?? [];
}

const filteredCategories = computed(() => {
    const contributorUserIds = resolveAccountCategoryContributorUserIds(
        form.account_uuid,
    );

    return categoriesForSelectedAccount(form.account_uuid).filter(
        (category) => {
            if (
                contributorUserIds.length > 0 &&
                !contributorUserIds.includes(category.owner_user_id ?? -1)
            ) {
                return false;
            }

            if (!form.type_key) {
                return true;
            }

            return category.type_key === form.type_key;
        },
    );
});

const filteredScopes = computed(() => {
    const contributorUserIds = resolveAccountScopeContributorUserIds(
        form.account_uuid,
    );

    return props.sheet.editor.scopes.filter(
        (scope) =>
            contributorUserIds.length === 0 ||
            contributorUserIds.includes(scope.owner_user_id ?? -1),
    );
});

const isTransfer = computed(() => form.type_key === transferTypeKey);
const isBalanceAdjustment = computed(
    () => form.type_key === balanceAdjustmentTypeKey,
);
const isMoveMode = computed(() => form.type_key === moveTypeKey);

function accountOwnershipBadgeLabel(
    account: MonthlyTransactionSheetData['editor']['accounts'][number],
): string {
    return account.is_shared
        ? t('dashboard.filters.sharedBadge')
        : t('dashboard.filters.ownedBadge');
}

function accountOwnershipBadgeClass(
    account: MonthlyTransactionSheetData['editor']['accounts'][number],
): string {
    return account.is_shared
        ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
}

function accountGroupLabel(
    account: MonthlyTransactionSheetData['editor']['accounts'][number],
): string {
    return account.account_type_code === 'credit_card'
        ? t('dashboard.filters.creditCardsGroup')
        : t('dashboard.filters.paymentAccountsGroup');
}

function mapAccountSelectOption(
    account: MonthlyTransactionSheetData['editor']['accounts'][number],
): AccountSelectOption {
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

const accountSelectOptions = computed<AccountSelectOption[]>(() =>
    sortAccountOptionsByGroup(props.sheet.editor.accounts).map((account) =>
        mapAccountSelectOption(account),
    ),
);

const destinationAccounts = computed(() =>
    props.sheet.editor.accounts.filter(
        (account) => account.value !== form.account_uuid,
    ),
);
const destinationAccountOptions = computed<AccountSelectOption[]>(() =>
    sortAccountOptionsByGroup(destinationAccounts.value).map((account) =>
        mapAccountSelectOption(account),
    ),
);
const selectedAccountCurrency = computed(
    () =>
        props.sheet.editor.accounts.find(
            (account) => account.value === form.account_uuid,
        )?.currency ??
        String(page.props.auth.user?.base_currency_code ?? 'EUR'),
);
const baseCurrencyCode = computed(() =>
    String(page.props.auth.user?.base_currency_code ?? 'EUR'),
);
const selectedAccountCurrencyLabel = computed(() =>
    formatCurrencyLabel(selectedAccountCurrency.value),
);
const moneyFormatLocale = computed(() =>
    String(page.props.auth.user?.format_locale ?? 'it-IT'),
);
const visibleTransactionDateError = computed(() =>
    isMoveMode.value
        ? form.errors.transaction_date
        : form.errors.transaction_date || form.errors.transaction_day,
);

const trackedItemOptions = computed(() =>
    filterTrackedItemOptions(
        trackedItemCatalog.value,
        form.account_uuid,
        form.type_key,
        form.category_uuid,
        form.tracked_item_uuid,
    ),
);

const referenceOptions = computed<ReferenceOption[]>(() => [
    ...filteredScopes.value.map((scope) => ({
        value: `scope:${scope.uuid ?? scope.value}`,
        label: scope.label,
    })),
    ...trackedItemOptions.value.map((trackedItem) => ({
        value: `tracked_item:${trackedItem.uuid ?? trackedItem.value}`,
        label: trackedItem.label,
    })),
]);

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

const selectedReferenceValue = computed({
    get(): string {
        if (form.scope_uuid !== '') {
            return `scope:${form.scope_uuid}`;
        }

        if (form.tracked_item_uuid !== '') {
            return `tracked_item:${form.tracked_item_uuid}`;
        }

        return '';
    },
    set(value: string): void {
        if (value === '') {
            form.scope_uuid = '';
            form.tracked_item_uuid = '';
            form.clearErrors('scope_uuid', 'tracked_item_uuid');

            return;
        }

        if (value.startsWith('scope:')) {
            form.scope_uuid = value.slice('scope:'.length);
            form.tracked_item_uuid = '';
            form.clearErrors('scope_uuid', 'tracked_item_uuid');

            return;
        }

        if (value.startsWith('tracked_item:')) {
            form.scope_uuid = '';
            form.tracked_item_uuid = value.slice('tracked_item:'.length);
            form.clearErrors('scope_uuid', 'tracked_item_uuid');
        }
    },
});

const monthDayRange = computed(() => {
    const month = isMoveMode.value
        ? Number(form.target_month || props.month)
        : props.month;

    return {
        min: 1,
        max: new Date(props.year, month, 0).getDate(),
    };
});

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function formatPreviewDate(date: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
    }).format(new Date(`${date}T00:00:00`));
}

function resetExchangePreview(): void {
    exchangePreview.value = null;
    exchangePreviewError.value = null;
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

function ensureScopeMatchesAccountContext(): void {
    if (form.scope_uuid === '') {
        return;
    }

    if (filteredScopes.value.some((scope) => scope.value === form.scope_uuid)) {
        return;
    }

    form.scope_uuid = '';
    form.clearErrors('scope_uuid');
}

function resolveCategoryContextUuids(categoryUuid: string): string[] {
    if (categoryUuid === '') {
        return [];
    }

    const category = categoriesForSelectedAccount(form.account_uuid).find(
        (option) => option.value === categoryUuid,
    );

    if (!category) {
        return [categoryUuid];
    }

    return [categoryUuid, ...category.ancestor_uuids];
}

function ensureCategoryMatchesAccountContext(): void {
    if (form.category_uuid === '') {
        return;
    }

    if (
        filteredCategories.value.some(
            (category) => category.value === form.category_uuid,
        )
    ) {
        return;
    }

    form.category_uuid = '';
    form.clearErrors('category_uuid');
}

function lockedMoveValue(value: string | null | undefined): string {
    return value && value !== ''
        ? value
        : t('transactions.sheet.grid.noSelection');
}

async function createTrackedItemFromContext(name: string): Promise<void> {
    if (form.type_key === '' || form.type_key === transferTypeKey) {
        form.setError(
            'tracked_item_uuid',
            t('transactions.form.errors.invalidTypeForTrackedItem'),
        );

        return;
    }

    creatingTrackedItem.value = true;

    try {
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
                account_uuid: form.account_uuid,
                category_uuid: form.category_uuid,
                type_key: form.type_key,
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

            form.setError(
                'tracked_item_uuid',
                typeof slugError === 'string'
                    ? slugError
                    : Array.isArray(firstError)
                      ? firstError[0]
                      : t('transactions.form.errors.createTrackedItemFailed'),
            );

            return;
        }

        const payload = await response.json();
        const option = payload.item as MonthlyTransactionSheetTrackedItemOption;

        trackedItemCatalog.value = [...trackedItemCatalog.value, option].sort(
            (first, second) => first.label.localeCompare(second.label, 'it'),
        );
        selectedReferenceValue.value = `tracked_item:${option.value}`;
    } catch (error) {
        form.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : t('transactions.form.errors.createTrackedItemFailed'),
        );
    } finally {
        creatingTrackedItem.value = false;
    }
}

function normalizeAmountField(): number | null {
    const parsedAmount = Number(form.amount);

    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
        form.setError(
            'amount',
            t('transactions.form.errors.amountMustBePositive'),
        );

        return null;
    }

    form.amount = String(parsedAmount);
    form.clearErrors('amount');

    return parsedAmount;
}

function normalizeDesiredBalanceField(): number | null {
    const parsedBalance = Number(form.desired_balance);

    if (!Number.isFinite(parsedBalance)) {
        form.setError(
            'desired_balance',
            t('transactions.form.errors.desiredBalanceRequired'),
        );

        return null;
    }

    form.desired_balance = String(parsedBalance);
    form.clearErrors('desired_balance');

    return parsedBalance;
}

async function refreshBalanceAdjustmentPreview(): Promise<void> {
    if (
        !props.open ||
        !isBalanceAdjustment.value ||
        form.account_uuid === '' ||
        form.transaction_day === '' ||
        form.desired_balance === ''
    ) {
        balanceAdjustmentPreview.value = null;

        return;
    }

    const desiredBalance = Number(form.desired_balance);

    if (!Number.isFinite(desiredBalance)) {
        balanceAdjustmentPreview.value = null;

        return;
    }

    balanceAdjustmentLoading.value = true;

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
                    account_uuid: form.account_uuid,
                    transaction_day: Number(form.transaction_day),
                    desired_balance: desiredBalance,
                }),
            },
        );

        const payload = await response.json();

        if (!response.ok) {
            const errors = payload?.errors ?? {};
            form.clearErrors(
                'account_uuid',
                'transaction_day',
                'desired_balance',
            );

            Object.entries(errors).forEach(([field, messages]) => {
                form.setError(
                    field as
                        | 'account_uuid'
                        | 'transaction_day'
                        | 'desired_balance',
                    Array.isArray(messages)
                        ? String(messages[0] ?? '')
                        : String(messages ?? ''),
                );
            });
            balanceAdjustmentPreview.value = null;

            return;
        }

        form.clearErrors('desired_balance');
        balanceAdjustmentPreview.value = payload;
    } catch {
        balanceAdjustmentPreview.value = null;
    } finally {
        balanceAdjustmentLoading.value = false;
    }
}

async function refreshAccountCurrentBalance(): Promise<void> {
    if (
        !props.open ||
        form.account_uuid === '' ||
        form.transaction_day === ''
    ) {
        accountCurrentBalance.value = null;

        return;
    }

    accountCurrentBalanceLoading.value = true;

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
                    account_uuid: form.account_uuid,
                    transaction_day: Number(form.transaction_day),
                    desired_balance: 0,
                }),
            },
        );

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            accountCurrentBalance.value = null;

            return;
        }

        accountCurrentBalance.value = Number(
            payload?.theoretical_balance_raw ?? 0,
        );
    } finally {
        accountCurrentBalanceLoading.value = false;
    }
}

async function refreshExchangePreview(): Promise<void> {
    if (
        !props.open ||
        isTransfer.value ||
        isBalanceAdjustment.value ||
        isMoveMode.value ||
        form.account_uuid === '' ||
        form.transaction_day === '' ||
        form.amount === ''
    ) {
        resetExchangePreview();

        return;
    }

    const parsedAmount = Number(form.amount);

    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
        resetExchangePreview();

        return;
    }

    exchangePreviewLoading.value = true;
    exchangePreviewError.value = null;

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
            resetExchangePreview();

            Object.entries(payload?.errors ?? {}).forEach(([field, messages]) => {
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
            });

            exchangePreviewError.value =
                (payload?.errors?.transaction_date?.[0] as string | undefined) ??
                (payload?.errors?.transaction_day?.[0] as string | undefined) ??
                (payload?.errors?.amount?.[0] as string | undefined) ??
                (payload?.errors?.account_uuid?.[0] as string | undefined) ??
                null;

            return;
        }

        exchangePreview.value = {
            amount_raw: Number(payload?.amount_raw ?? parsedAmount),
            converted_base_amount_raw: Number(
                payload?.converted_base_amount_raw ?? 0,
            ),
            currency_code: String(payload?.currency_code ?? selectedAccountCurrency.value),
            base_currency_code: String(
                payload?.base_currency_code ?? baseCurrencyCode.value,
            ),
            exchange_rate: String(payload?.exchange_rate ?? '1.00000000'),
            exchange_rate_date: String(payload?.exchange_rate_date ?? ''),
            exchange_rate_source: String(payload?.exchange_rate_source ?? 'identity'),
            is_multi_currency: Boolean(payload?.is_multi_currency ?? false),
            should_preview: Boolean(payload?.should_preview ?? false),
        };
        exchangePreviewError.value = null;
        form.clearErrors('transaction_date');
    } catch {
        resetExchangePreview();
    } finally {
        exchangePreviewLoading.value = false;
    }
}

watch(
    () => [props.open, props.transaction] as const,
    ([open, transaction]) => {
        if (!open) {
            return;
        }

        form.clearErrors();

        if (transaction) {
            const transactionDateParts = parseIsoDateParts(transaction.date);
            form.defaults({
                transaction_day: transaction.date
                    ? String(new Date(transaction.date).getDate())
                    : '1',
                target_month: transactionDateParts
                    ? String(transactionDateParts.month)
                    : String(props.month),
                transaction_date: transaction.date ?? '',
                type_key: transaction.type_key ?? 'expense',
                category_uuid: transaction.is_transfer
                    ? ''
                    : transaction.category_uuid
                      ? String(transaction.category_uuid)
                      : '',
                destination_account_uuid: transaction.related_account_uuid
                    ? String(transaction.related_account_uuid)
                    : '',
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
                amount:
                    transaction.amount_value_raw !== null
                        ? String(transaction.amount_value_raw)
                        : '',
                desired_balance: '',
                description: transaction.description ?? '',
                notes: transaction.notes ?? '',
            });
            form.reset();
            balanceAdjustmentPreview.value = null;
            accountCurrentBalance.value = null;
            resetExchangePreview();

            return;
        }

        form.defaults({
            transaction_day: '1',
            target_month: String(props.month),
            transaction_date: '',
            type_key: props.sheet.editor.type_options[0]?.value ?? 'expense',
            category_uuid: '',
            destination_account_uuid: '',
            account_uuid: resolveDefaultAccountUuid(),
            scope_uuid: '',
            tracked_item_uuid: '',
            amount: '',
            desired_balance: '',
            description: '',
            notes: '',
        });
        form.reset();
        balanceAdjustmentPreview.value = null;
        accountCurrentBalance.value = null;
        resetExchangePreview();
    },
    { immediate: true },
);

watch(
    () => props.sheet.editor.tracked_items,
    (options) => {
        trackedItemCatalog.value = [...options];
    },
    { deep: true, immediate: true },
);

watch(
    () => form.type_key,
    (typeKey) => {
        if (typeKey === transferTypeKey) {
            form.category_uuid = '';
            form.scope_uuid = '';
            form.tracked_item_uuid = '';
            form.clearErrors(
                'category_uuid',
                'scope_uuid',
                'tracked_item_uuid',
            );
        } else if (typeKey === balanceAdjustmentTypeKey) {
            form.category_uuid = '';
            form.destination_account_uuid = '';
            form.scope_uuid = '';
            form.tracked_item_uuid = '';
            form.amount = '';
            form.clearErrors(
                'category_uuid',
                'destination_account_uuid',
                'scope_uuid',
                'tracked_item_uuid',
                'amount',
            );
        } else if (typeKey === moveTypeKey) {
            form.clearErrors(
                'category_uuid',
                'destination_account_uuid',
                'scope_uuid',
                'tracked_item_uuid',
                'amount',
                'description',
                'notes',
            );
        } else {
            form.destination_account_uuid = '';
            form.clearErrors('destination_account_uuid');
        }

        if (
            form.category_uuid &&
            !filteredCategories.value.some(
                (category) => category.value === form.category_uuid,
            )
        ) {
            form.category_uuid = '';
        }
    },
);

watch(
    () => [props.open, form.account_uuid, form.transaction_day] as const,
    () => {
        void refreshAccountCurrentBalance();
    },
);

watch(
    () =>
        [
            props.open,
            form.type_key,
            form.account_uuid,
            form.transaction_day,
            form.amount,
        ] as const,
    () => {
        void refreshExchangePreview();
    },
);

watch(
    () => form.transaction_date,
    (value) => {
        if (!isMoveMode.value || value === '') {
            return;
        }

        const dateParts = parseIsoDateParts(value);

        if (!dateParts) {
            return;
        }

        form.transaction_day = String(dateParts.day);
        form.target_month = String(dateParts.month);
        form.clearErrors('transaction_date');
    },
);

watch(
    () =>
        [
            form.type_key,
            form.account_uuid,
            form.transaction_day,
            form.desired_balance,
        ] as const,
    () => {
        void refreshBalanceAdjustmentPreview();
    },
);

watch(
    () => [form.type_key, form.category_uuid] as const,
    ([typeKey, categoryId], [, previousCategoryId]) => {
        if (form.tracked_item_uuid === '') {
            return;
        }

        if (
            previousCategoryId === undefined ||
            trackedItemMatchesContext(
                trackedItemCatalog.value.find(
                    (option) => option.value === form.tracked_item_uuid,
                ) ?? { value: '', label: '' },
                form.account_uuid,
                typeKey,
                categoryId,
            )
        ) {
            return;
        }

        form.tracked_item_uuid = '';
        form.clearErrors('tracked_item_uuid');
    },
);

watch(
    () => form.account_uuid,
    () => {
        if (form.destination_account_uuid === form.account_uuid) {
            form.destination_account_uuid = '';
        }

        ensureCategoryMatchesAccountContext();
        ensureScopeMatchesAccountContext();

        if (
            form.tracked_item_uuid !== '' &&
            !trackedItemOptions.value.some(
                (option) => option.value === form.tracked_item_uuid,
            )
        ) {
            form.tracked_item_uuid = '';
            form.clearErrors('tracked_item_uuid');
        }
    },
);

function closeSheet(): void {
    emit('update:open', false);
}

function handleTypeSelection(value: string): void {
    if (value === refundTypeKey && props.transaction) {
        emit('request-refund', props.transaction);
        closeSheet();

        return;
    }

    form.type_key = value;
}

function submit(): void {
    if (
        form.transaction_day === '' ||
        Number(form.transaction_day) < monthDayRange.value.min ||
        Number(form.transaction_day) > monthDayRange.value.max
    ) {
        form.setError(
            'transaction_day',
            t('transactions.form.errors.dayRange', {
                min: monthDayRange.value.min,
                max: monthDayRange.value.max,
            }),
        );

        return;
    }

    if (isTransfer.value) {
        if (form.destination_account_uuid === '') {
            form.setError(
                'destination_account_uuid',
                t('transactions.form.errors.destinationAccountRequired'),
            );

            return;
        }

        if (form.destination_account_uuid === form.account_uuid) {
            form.setError(
                'destination_account_uuid',
                t('transactions.form.errors.destinationAccountDifferent'),
            );

            return;
        }
    }

    if (isMoveMode.value) {
        if (form.transaction_date === '') {
            form.setError(
                'transaction_date',
                t('transactions.form.labels.moveDate'),
            );

            return;
        }

        if (!isMoveDateYearAllowed(form.transaction_date)) {
            form.setError(
                'transaction_date',
                t('transactions.form.errors.moveYearUnavailable'),
            );

            return;
        }

        if (!isEditing.value || !props.transaction) {
            form.setError('type_key', t('transactions.form.actions.move'));

            return;
        }

        form.transform(() => ({
            transaction_date: form.transaction_date,
            type_key: moveTypeKey,
        })).patch(
            `/transactions/${props.year}/${props.month}/${props.transaction.uuid}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('saved', t('transactions.form.feedback.updated'));
                    closeSheet();
                },
            },
        );

        return;
    }

    if (isBalanceAdjustment.value) {
        const normalizedDesiredBalance = normalizeDesiredBalanceField();

        if (normalizedDesiredBalance === null) {
            return;
        }

        const payload = {
            transaction_day: Number(form.transaction_day),
            type_key: form.type_key,
            account_uuid: form.account_uuid,
            desired_balance: normalizedDesiredBalance,
            description: form.description.trim() || null,
            notes: form.notes.trim() || null,
        };

        form.transform(() => payload).post(
            `/transactions/${props.year}/${props.month}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('saved', t('transactions.form.feedback.created'));
                    closeSheet();
                },
            },
        );

        return;
    }

    const normalizedAmount = normalizeAmountField();

    if (normalizedAmount === null) {
        return;
    }

    const payload = {
        transaction_day: Number(form.transaction_day),
        type_key: form.type_key,
        category_uuid: form.category_uuid || null,
        destination_account_uuid: form.destination_account_uuid || null,
        account_uuid: form.account_uuid,
        scope_uuid: form.scope_uuid || null,
        tracked_item_uuid: form.tracked_item_uuid || null,
        amount: normalizedAmount,
        description: form.description.trim() || null,
        notes: form.notes.trim() || null,
    };

    if (isEditing.value && props.transaction) {
        form.transform(() => payload).patch(
            `/transactions/${props.year}/${props.month}/${props.transaction.uuid}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('saved', t('transactions.form.feedback.updated'));
                    closeSheet();
                },
            },
        );

        return;
    }

    form.transform(() => payload).post(
        `/transactions/${props.year}/${props.month}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved', t('transactions.form.feedback.created'));
                closeSheet();
            },
        },
    );
}
</script>

<template>
    <Sheet
        :open="open"
        :modal="!isMobile"
        @update:open="emit('update:open', $event)"
    >
        <SheetContent
            :side="isMobile ? 'bottom' : 'right'"
            :class="
                isMobile
                    ? 'h-[100dvh] max-h-[100dvh] w-full rounded-t-[1.75rem] border-t border-l-0 p-0'
                    : 'w-full border-l p-0 sm:max-w-2xl'
            "
            @open-auto-focus.prevent
        >
            <div class="flex h-full flex-col">
                <SheetHeader
                    class="border-b border-slate-200/80 px-6 py-6 dark:border-slate-800"
                >
                    <SheetTitle>{{ title }}</SheetTitle>
                    <SheetDescription>
                        {{ description }}
                    </SheetDescription>
                </SheetHeader>

                <div
                    :style="mobileScrollStyle"
                    class="flex-1 overflow-y-auto px-6 py-6"
                >
                    <form
                        class="space-y-6"
                        @focusin.capture="handleFocusIn"
                        @submit.prevent="submit"
                    >
                        <div
                            class="grid gap-5"
                            :class="
                                isMoveMode ? 'md:grid-cols-2' : 'md:grid-cols-2'
                            "
                        >
                            <div class="grid gap-2">
                                <Label
                                    :for="
                                        isMoveMode
                                            ? 'transaction_date'
                                            : 'transaction_day'
                                    "
                                    >{{
                                        isMoveMode
                                            ? t(
                                                  'transactions.form.labels.moveDate',
                                              )
                                            : t('transactions.form.labels.day')
                                    }}</Label
                                >
                                <Input
                                    v-if="isMoveMode"
                                    :id="
                                        isMoveMode
                                            ? 'transaction_date'
                                            : 'transaction_day'
                                    "
                                    v-model="form.transaction_date"
                                    type="date"
                                    :min="moveDateMin"
                                    :max="moveDateMax"
                                    class="h-11 rounded-2xl border-slate-200 text-center dark:border-slate-800"
                                />
                                <Input
                                    v-else
                                    id="transaction_day"
                                    v-model="form.transaction_day"
                                    :type="isMobile ? 'text' : 'number'"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    autocomplete="off"
                                    enterkeyhint="next"
                                    :placeholder="
                                        t('transactions.form.placeholders.day')
                                    "
                                    :min="monthDayRange.min"
                                    :max="monthDayRange.max"
                                    class="h-11 rounded-2xl border-slate-200 text-center dark:border-slate-800"
                                />
                                <InputError
                                    :message="visibleTransactionDateError"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t('transactions.form.labels.type')
                                }}</Label>
                                <Select
                                    :model-value="form.type_key"
                                    @update:model-value="
                                        handleTypeSelection(String($event))
                                    "
                                >
                                    <SelectTrigger
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'transactions.form.placeholders.selectType',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent class="z-[170]">
                                        <SelectItem
                                            v-for="option in adjustmentTypeOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.type_key" />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>{{
                                    isTransfer
                                        ? t(
                                              'transactions.form.labels.sourceAccount',
                                          )
                                        : t('transactions.form.labels.account')
                                }}</Label>
                                <MobileSearchableSelect
                                    v-model="form.account_uuid"
                                    :options="accountSelectOptions"
                                    :placeholder="
                                        isTransfer
                                            ? t(
                                                  'transactions.form.placeholders.selectSourceAccount',
                                              )
                                            : t(
                                                  'transactions.form.placeholders.selectAccount',
                                              )
                                    "
                                    :search-placeholder="
                                        isTransfer
                                            ? t(
                                                  'transactions.form.placeholders.searchSourceAccount',
                                              )
                                            : t(
                                                  'transactions.form.placeholders.searchAccount',
                                              )
                                    "
                                    clearable
                                    :disabled="isMoveMode"
                                    :teleport="false"
                                    :mobile-title="
                                        isTransfer
                                            ? t(
                                                  'transactions.form.labels.sourceAccount',
                                              )
                                            : t(
                                                  'transactions.form.labels.account',
                                              )
                                    "
                                    trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                />
                                <InputError
                                    :message="form.errors.account_uuid"
                                />
                                <p
                                    v-if="form.account_uuid !== ''"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.form.helper.accountCurrency',
                                            {
                                                currency: selectedAccountCurrencyLabel,
                                            },
                                        )
                                    }}
                                </p>
                                <div
                                    v-if="form.account_uuid !== ''"
                                    class="rounded-2xl border border-dashed border-slate-200 px-3 py-3 dark:border-slate-800"
                                >
                                    <div
                                        class="text-right text-sm font-semibold text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            accountCurrentBalanceLoading
                                                ? t(
                                                      'transactions.form.placeholders.balanceAdjustmentLoading',
                                                  )
                                                : accountCurrentBalance !== null
                                                  ? formatCurrency(
                                                        accountCurrentBalance,
                                                        selectedAccountCurrency,
                                                    )
                                                  : t(
                                                        'transactions.form.placeholders.balanceAdjustmentPending',
                                                    )
                                        }}
                                    </div>
                                    <p
                                        class="mt-1 text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'transactions.form.labels.currentBalance',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    isBalanceAdjustment
                                        ? t(
                                              'transactions.form.labels.theoreticalBalance',
                                          )
                                        : isTransfer
                                          ? t(
                                                'transactions.form.labels.destinationAccount',
                                            )
                                          : t(
                                                'transactions.form.labels.category',
                                            )
                                }}</Label>
                                <div
                                    v-if="isBalanceAdjustment"
                                    class="flex h-11 items-center rounded-2xl border border-dashed border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:text-slate-200"
                                >
                                    {{
                                        balanceAdjustmentPreview
                                            ? formatCurrency(
                                                  balanceAdjustmentPreview.theoretical_balance_raw,
                                                  selectedAccountCurrency,
                                              )
                                            : t(
                                                  'transactions.form.placeholders.balanceAdjustmentPending',
                                              )
                                    }}
                                </div>
                                <div
                                    v-else-if="isMoveMode"
                                    class="flex h-11 items-center rounded-2xl border border-dashed border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:text-slate-200"
                                >
                                    {{
                                        lockedMoveValue(
                                            props.transaction?.category_label,
                                        )
                                    }}
                                </div>
                                <MobileSearchableSelect
                                    v-else-if="!isTransfer"
                                    v-model="form.category_uuid"
                                    :options="filteredCategories"
                                    :placeholder="
                                        t(
                                            'transactions.form.placeholders.selectCategory',
                                        )
                                    "
                                    :search-placeholder="
                                        t(
                                            'transactions.form.placeholders.searchCategory',
                                        )
                                    "
                                    :disabled="form.type_key === ''"
                                    clearable
                                    hierarchical
                                    :teleport="false"
                                    :mobile-title="
                                        t('transactions.form.labels.category')
                                    "
                                    trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                />
                                <MobileSearchableSelect
                                    v-else
                                    v-model="form.destination_account_uuid"
                                    :options="destinationAccountOptions"
                                    :placeholder="
                                        t(
                                            'transactions.form.placeholders.selectDestinationAccount',
                                        )
                                    "
                                    :search-placeholder="
                                        t(
                                            'transactions.form.placeholders.searchDestinationAccount',
                                        )
                                    "
                                    clearable
                                    :teleport="false"
                                    :mobile-title="
                                        t(
                                            'transactions.form.labels.destinationAccount',
                                        )
                                    "
                                    trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                />
                                <InputError
                                    :message="
                                        isBalanceAdjustment
                                            ? undefined
                                            : isTransfer
                                              ? form.errors
                                                    .destination_account_uuid
                                              : form.errors.category_uuid
                                    "
                                />
                            </div>
                        </div>

                        <div
                            v-if="isTransfer"
                            class="rounded-2xl border border-sky-200/80 bg-sky-50/70 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-200"
                        >
                            {{ t('transactions.form.helper.transferInfo') }}
                        </div>

                        <div
                            v-else-if="isBalanceAdjustment"
                            class="rounded-2xl border border-amber-200/80 bg-amber-50/70 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-100"
                        >
                            {{
                                t(
                                    'transactions.form.helper.balanceAdjustmentInfo',
                                )
                            }}
                        </div>

                        <div
                            v-else-if="isMoveMode"
                            class="rounded-2xl border border-sky-200/80 bg-sky-50/70 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-200"
                        >
                            {{ t('transactions.form.helper.moveInfo') }}
                        </div>

                        <div class="grid gap-5">
                            <div class="grid gap-2">
                                <MobileAmountInput
                                    v-if="!isBalanceAdjustment"
                                    id="amount"
                                    v-model="form.amount"
                                    :label="
                                        t('transactions.form.labels.amount')
                                    "
                                    :mobile-title="
                                        t('transactions.form.labels.amount')
                                    "
                                    :disabled="isMoveMode"
                                    :format-locale="moneyFormatLocale"
                                    :currency-code="selectedAccountCurrency"
                                    :placeholder="
                                        t(
                                            'transactions.form.placeholders.amount',
                                        )
                                    "
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    @blur="normalizeAmountField"
                                />
                                <MobileAmountInput
                                    v-else
                                    id="desired_balance"
                                    v-model="form.desired_balance"
                                    :label="
                                        t(
                                            'transactions.form.labels.desiredBalance',
                                        )
                                    "
                                    :mobile-title="
                                        t(
                                            'transactions.form.labels.desiredBalance',
                                        )
                                    "
                                    :format-locale="moneyFormatLocale"
                                    :currency-code="selectedAccountCurrency"
                                    :placeholder="
                                        t(
                                            'transactions.form.placeholders.amount',
                                        )
                                    "
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    @blur="normalizeDesiredBalanceField"
                                />
                                <InputError
                                    :message="
                                        isBalanceAdjustment
                                            ? form.errors.desired_balance
                                            : form.errors.amount
                                    "
                                />
                                <div
                                    v-if="
                                        !isBalanceAdjustment &&
                                        !isTransfer &&
                                        !isMoveMode &&
                                        (exchangePreviewLoading ||
                                            exchangePreview?.should_preview ||
                                            exchangePreviewError)
                                    "
                                    class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80"
                                >
                                    <p
                                        class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'transactions.form.helper.fxPreviewTitle',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="exchangePreviewLoading"
                                        class="mt-2 text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            t(
                                                'transactions.form.placeholders.balanceAdjustmentLoading',
                                            )
                                        }}
                                    </p>
                                    <template
                                        v-else-if="
                                            exchangePreview &&
                                            exchangePreview.should_preview
                                        "
                                    >
                                        <p
                                            class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                t(
                                                    'transactions.form.helper.fxPreviewAmount',
                                                    {
                                                        source: formatCurrency(
                                                            exchangePreview.amount_raw,
                                                            exchangePreview.currency_code,
                                                            moneyFormatLocale,
                                                        ),
                                                        target: formatCurrency(
                                                            exchangePreview.converted_base_amount_raw,
                                                            exchangePreview.base_currency_code,
                                                            moneyFormatLocale,
                                                        ),
                                                    },
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    'transactions.form.helper.fxPreviewRateDate',
                                                    {
                                                        date: formatPreviewDate(
                                                            exchangePreview.exchange_rate_date,
                                                        ),
                                                    },
                                                )
                                            }}
                                        </p>
                                    </template>
                                    <p
                                        v-else-if="exchangePreviewError"
                                        class="mt-2 text-sm text-rose-600 dark:text-rose-400"
                                    >
                                        {{ exchangePreviewError }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <MobileTextFieldEditor
                                v-if="isMobile"
                                v-model="form.description"
                                v-model:open="mobileDescriptionEditorOpen"
                                :label="
                                    isBalanceAdjustment
                                        ? t('transactions.form.labels.note')
                                        : t('transactions.form.labels.detail')
                                "
                                :placeholder="
                                    isBalanceAdjustment
                                        ? t(
                                              'transactions.form.placeholders.balanceAdjustmentNote',
                                          )
                                        : t(
                                              'transactions.form.placeholders.detailExample',
                                          )
                                "
                                :description="
                                    isBalanceAdjustment
                                        ? t('transactions.form.labels.note')
                                        : t('transactions.form.labels.detail')
                                "
                                :disabled="isMoveMode"
                            />
                            <Input
                                v-else
                                id="description"
                                v-model="form.description"
                                :placeholder="
                                    isBalanceAdjustment
                                        ? t(
                                              'transactions.form.placeholders.balanceAdjustmentNote',
                                          )
                                        : t(
                                              'transactions.form.placeholders.detailExample',
                                          )
                                "
                                :disabled="isMoveMode"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div v-if="isBalanceAdjustment" class="grid gap-2">
                            <Label>{{
                                t(
                                    'transactions.form.labels.adjustmentDifference',
                                )
                            }}</Label>
                            <div
                                class="flex h-11 items-center rounded-2xl border border-dashed border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:text-slate-200"
                            >
                                {{
                                    balanceAdjustmentLoading
                                        ? t(
                                              'transactions.form.placeholders.balanceAdjustmentLoading',
                                          )
                                        : balanceAdjustmentPreview
                                          ? formatCurrency(
                                                balanceAdjustmentPreview.adjustment_amount_raw,
                                                selectedAccountCurrency,
                                            )
                                          : t(
                                                'transactions.form.placeholders.balanceAdjustmentPending',
                                            )
                                }}
                            </div>
                        </div>

                        <div v-else-if="!isTransfer" class="grid gap-2">
                            <Label>{{
                                t('transactions.form.labels.trackedItem')
                            }}</Label>
                            <div
                                v-if="isMoveMode"
                                class="flex h-11 items-center rounded-2xl border border-dashed border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:text-slate-200"
                            >
                                {{
                                    lockedMoveValue(
                                        props.transaction?.tracked_item_label ??
                                            props.transaction?.scope_label,
                                    )
                                }}
                            </div>
                            <MobileSearchableSelect
                                v-else
                                v-model="selectedReferenceValue"
                                :options="[
                                    {
                                        value: '',
                                        label: t(
                                            'transactions.form.placeholders.none',
                                        ),
                                    },
                                    ...referenceOptions,
                                ]"
                                :placeholder="
                                    t(
                                        'transactions.form.placeholders.selectTrackedItem',
                                    )
                                "
                                :search-placeholder="
                                    t(
                                        'transactions.form.placeholders.searchTrackedItem',
                                    )
                                "
                                :disabled="form.type_key === ''"
                                clearable
                                :teleport="false"
                                creatable
                                :creating="creatingTrackedItem"
                                :mobile-title="
                                    t('transactions.form.labels.trackedItem')
                                "
                                :create-label="
                                    t(
                                        'transactions.form.placeholders.createTrackedItem',
                                    )
                                "
                                trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                @create-option="createTrackedItemFromContext"
                            />
                            <InputError
                                :message="
                                    form.errors.tracked_item_uuid ||
                                    form.errors.scope_uuid
                                "
                            />
                        </div>

                        <div v-if="!isBalanceAdjustment" class="grid gap-2">
                            <MobileTextFieldEditor
                                v-if="isMobile"
                                v-model="form.notes"
                                v-model:open="mobileNotesEditorOpen"
                                :label="t('transactions.form.labels.notes')"
                                :placeholder="
                                    t(
                                        'transactions.form.placeholders.optionalNotes',
                                    )
                                "
                                :disabled="isMoveMode"
                                multiline
                                :rows="8"
                            />
                            <textarea
                                v-else
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                :disabled="isMoveMode"
                                class="min-h-28 rounded-2xl border border-slate-200 bg-transparent px-3 py-3 text-sm shadow-xs transition-colors outline-none placeholder:text-slate-400 focus:border-slate-400 dark:border-slate-800 dark:placeholder:text-slate-500"
                                :placeholder="
                                    t(
                                        'transactions.form.placeholders.optionalNotes',
                                    )
                                "
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </form>
                </div>

                <div
                    :style="mobileFooterStyle"
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
                            {{ t('transactions.form.actions.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            class="rounded-2xl"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            {{
                                isEditing
                                    ? t('transactions.form.actions.saveChanges')
                                    : t('transactions.form.actions.create')
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
