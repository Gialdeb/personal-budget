<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowLeft,
    ArrowUpRight,
    CalendarClock,
    Check,
    CircleHelp,
    Filter,
    Plus,
    Search,
    Sparkles,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, defineComponent, h, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoneyValue, normalizeMoneyValue } from '@/lib/money';
import { cn } from '@/lib/utils';
import {
    destroy as destroyCreditDebt,
    index as creditsDebtsIndex,
    store as storeCreditDebt,
    update as updateCreditDebt,
} from '@/routes/credits-debts';
import {
    destroy as destroyPayment,
    store as storePayment,
} from '@/routes/credits-debts/payments';
import { store as storeTrackedItem } from '@/routes/transactions/tracked-items';
import type { BreadcrumbItem } from '@/types';

type Option = {
    value: string;
    uuid?: string;
    label: string;
    full_path?: string;
    icon?: string | null;
    color?: string | null;
    ancestor_uuids?: string[];
    currency_code?: string | null;
    direction_type?: string | null;
    is_selectable?: boolean;
    category_uuids?: string[];
    group_keys?: string[];
};

type CreditDebtPayment = {
    uuid: string;
    amount: string;
    currency_code: string;
    paid_at: string;
    note: string | null;
    account: Option | null;
    transaction: { uuid: string } | null;
    created_at: string | null;
};

type CreditDebtItem = {
    uuid: string;
    type: 'credit' | 'debit';
    description: string;
    total_amount: string;
    paid_amount: string;
    remaining_amount: string;
    currency_code: string;
    status: 'open' | 'partial' | 'settled';
    due_date: string | null;
    note: string | null;
    reference: { uuid?: string; name?: string; label?: string } | null;
    account: Option | null;
    category: Option | null;
    payments_count: number;
    payments?: CreditDebtPayment[];
};

type Summary = {
    credits_remaining_total: string;
    debts_remaining_total: string;
    overdue_count: number;
    overdue_credits_total: string;
    overdue_debts_total: string;
    current_month_credits_total: string;
    current_month_debts_total: string;
    future_credits_total: string;
    future_debts_total: string;
    net_expected_total: string;
};

const props = defineProps<{
    items: CreditDebtItem[];
    summary: Summary;
    filters: Record<string, string | null>;
    options: {
        accounts: Option[];
        categories: Record<string, Option[]>;
        references: Record<string, Option[]>;
        currencies: Option[];
        years: Array<{ value: number; label: string }>;
        months: Array<{ value: number | null; label: string }>;
    };
    today: string;
}>();

const { t } = useI18n();
const page = usePage();
const selectedUuid = ref<string | null>(
    props.items.find((item) => item.uuid === props.filters.selected)?.uuid ??
        props.items[0]?.uuid ??
        null,
);
const activeMobileType = ref<'all' | 'credit' | 'debit'>('all');
const mobileTypes = ['all', 'credit', 'debit'] as const;
const isFilterOpen = ref(false);
const isItemSheetOpen = ref(false);
const isPaymentSheetOpen = ref(false);
const isMobileDetailOpen = ref(false);
const editingItem = ref<CreditDebtItem | null>(null);
const creatingReference = ref(false);
const localReferences = ref<Record<string, Option[]>>({
    ...props.options.references,
});
const filters = ref({
    search: props.filters.search ?? '',
    type: props.filters.type ?? 'all',
    status: props.filters.status ?? 'all',
    due_bucket: props.filters.due_bucket ?? 'all',
    reference_uuid: props.filters.reference_uuid ?? 'all',
    account_uuid: props.filters.account_uuid ?? 'all',
    category_uuid: props.filters.category_uuid ?? 'all',
    month: props.filters.month ?? 'all',
    year:
        props.filters.year ??
        String(
            props.options.years.find(
                (option) => option.value === new Date().getFullYear(),
            )?.value ??
                props.options.years.at(-1)?.value ??
                new Date().getFullYear(),
        ),
});

const authUser = computed(() => page.props.auth?.user ?? {});
const formatLocale = computed(() => authUser.value.format_locale ?? 'it-IT');
const dateFormat = computed(() => authUser.value.date_format ?? 'D MMM YYYY');
const fallbackCurrency = computed(
    () => authUser.value.base_currency_code ?? 'EUR',
);
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('nav.creditsDebts'),
        href: creditsDebtsIndex(),
    },
];

const itemForm = useForm({
    type: 'credit',
    description: '',
    total_amount: '',
    currency_code: fallbackCurrency.value,
    reference_uuid: '',
    account_uuid: '',
    category_uuid: '',
    due_date: '',
    note: '',
});

const paymentForm = useForm({
    amount: '',
    account_uuid: '',
    paid_at: props.today,
    note: '',
});

const selectedItem = computed(
    () =>
        props.items.find((item) => item.uuid === selectedUuid.value) ??
        props.items[0] ??
        null,
);

watch(
    () => props.items,
    (items) => {
        if (
            selectedUuid.value &&
            items.some((item) => item.uuid === selectedUuid.value)
        ) {
            return;
        }

        selectedUuid.value = items[0]?.uuid ?? null;
    },
);

watch(
    () => itemForm.account_uuid,
    () => {
        itemForm.category_uuid = '';
        itemForm.reference_uuid = '';
        itemForm.clearErrors('account_uuid', 'category_uuid', 'reference_uuid');
    },
);

watch(
    () => itemForm.type,
    () => {
        itemForm.category_uuid = '';
        itemForm.reference_uuid = '';
        itemForm.clearErrors('type', 'category_uuid', 'reference_uuid');
    },
);

watch(
    () => itemForm.category_uuid,
    () => {
        if (
            itemForm.reference_uuid !== '' &&
            !selectedAccountReferences.value.some(
                (option) => option.value === itemForm.reference_uuid,
            )
        ) {
            itemForm.reference_uuid = '';
        }

        itemForm.clearErrors('category_uuid', 'reference_uuid');
    },
);

const accountOptions = computed(() => props.options.accounts);
const selectedItemAccount = computed(
    () =>
        props.options.accounts.find(
            (option) => option.value === itemForm.account_uuid,
        ) ?? null,
);
const itemCurrencyCode = computed(() => fallbackCurrency.value);
const hasAccountCurrencyConversion = computed(
    () =>
        selectedItemAccount.value?.currency_code !== undefined &&
        selectedItemAccount.value.currency_code !== null &&
        selectedItemAccount.value.currency_code !== itemCurrencyCode.value,
);
const selectedAccountCategories = computed(() => {
    if (!itemForm.account_uuid) {
        return [];
    }

    return (props.options.categories[itemForm.account_uuid] ?? []).filter(
        (option) =>
            option.is_selectable !== false && categoryMatchesType(option),
    );
});
const selectedAccountReferences = computed(() => {
    if (!itemForm.account_uuid) {
        return [];
    }

    return (localReferences.value[itemForm.account_uuid] ?? []).filter(
        (option) => referenceMatchesContext(option),
    );
});
const filterReferences = computed(() =>
    uniqueOptions(Object.values(localReferences.value).flat()),
);
const filterCategories = computed(() =>
    uniqueOptions(Object.values(props.options.categories).flat()),
);
const itemAccountError = computed(
    () =>
        itemForm.errors.account_uuid ??
        (itemForm.errors as Record<string, string | undefined>).account_id,
);
const itemCategoryError = computed(
    () =>
        itemForm.errors.category_uuid ??
        (itemForm.errors as Record<string, string | undefined>).category_id,
);
const itemReferenceError = computed(
    () =>
        itemForm.errors.reference_uuid ??
        (itemForm.errors as Record<string, string | undefined>).reference_id,
);
const paymentAccountError = computed(
    () =>
        paymentForm.errors.account_uuid ??
        (paymentForm.errors as Record<string, string | undefined>).account_id,
);
const itemTotalAmountLocked = computed(() =>
    Boolean(editingItem.value && editingItem.value.payments_count > 0),
);

const credits = computed(() =>
    props.items.filter((item) => item.type === 'credit'),
);
const debts = computed(() =>
    props.items.filter((item) => item.type === 'debit'),
);

function uniqueOptions(options: Option[]): Option[] {
    const seen = new Set<string>();

    return options.filter((option) => {
        const key = option.value;

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

function categoryMatchesType(option: Option): boolean {
    if (!option.direction_type) {
        return true;
    }

    return itemForm.type === 'credit'
        ? option.direction_type === 'income'
        : option.direction_type === 'expense';
}

function referenceTypeKey(): string {
    return itemForm.type === 'credit' ? 'income' : 'debt';
}

function referenceMatchesContext(option: Option): boolean {
    const categoryUuids = option.category_uuids ?? [];
    const groupKeys = option.group_keys ?? [];
    const categoryContextUuids = resolveCategoryContextUuids(
        itemForm.category_uuid,
    );

    if (categoryUuids.length > 0) {
        return categoryContextUuids.some((uuid) =>
            categoryUuids.includes(uuid),
        );
    }

    if (groupKeys.length > 0) {
        return groupKeys.includes(referenceTypeKey());
    }

    return false;
}

function resolveCategoryContextUuids(categoryUuid: string): string[] {
    if (categoryUuid === '') {
        return [];
    }

    const category = selectedAccountCategories.value.find(
        (option) => option.value === categoryUuid,
    );

    if (!category) {
        return [categoryUuid];
    }

    return [categoryUuid, ...(category.ancestor_uuids ?? [])];
}

function money(
    value: string | number | null,
    currency = fallbackCurrency.value,
): string {
    return formatMoneyValue(
        value ?? '0',
        currency,
        formatLocale.value,
        undefined,
        {
            preferCodeWhenAmbiguous: true,
        },
    );
}

function amount(
    value: string | number | null,
    currency = fallbackCurrency.value,
): string {
    return money(value, currency);
}

function numericMoney(value: string | number | null): number {
    const normalized = normalizeMoneyValue(
        value,
        formatLocale.value,
        2,
        fallbackCurrency.value,
    );

    return normalized === '' ? Number.NaN : Number(normalized);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(`${value}T00:00:00`);
    const day = String(date.getDate());
    const paddedDay = day.padStart(2, '0');
    const month = String(date.getMonth() + 1);
    const paddedMonth = month.padStart(2, '0');
    const year = String(date.getFullYear());
    const shortMonth = new Intl.DateTimeFormat(formatLocale.value, {
        month: 'short',
    }).format(date);

    return String(dateFormat.value || 'D MMM YYYY')
        .replace('YYYY', year)
        .replace('MMM', shortMonth)
        .replace('DD', paddedDay)
        .replace('MM', paddedMonth)
        .replace('D', day);
}

function referenceName(item: CreditDebtItem): string {
    return item.reference?.label ?? item.reference?.name ?? item.description;
}

function initials(item: CreditDebtItem): string {
    return referenceName(item)
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
}

function paymentLabel(item: CreditDebtItem): string {
    return item.type === 'credit'
        ? t('creditsDebts.received')
        : t('creditsDebts.paid');
}

function paymentActionLabel(item: CreditDebtItem): string {
    return item.type === 'credit'
        ? t('creditsDebts.registerCreditPayment')
        : t('creditsDebts.registerDebtPayment');
}

function settleLabel(item: CreditDebtItem): string {
    return item.type === 'credit'
        ? t('creditsDebts.settleCredit')
        : t('creditsDebts.settleDebt');
}

function statusLabel(item: CreditDebtItem): string {
    if (isOverdue(item)) {
        return t('creditsDebts.overdue');
    }

    return t(`creditsDebts.${item.status}`);
}

function statusClass(item: CreditDebtItem): string {
    if (item.status === 'settled') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    if (isOverdue(item)) {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';
    }

    if (item.status === 'partial') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
}

function isOverdue(item: CreditDebtItem): boolean {
    return (
        item.due_date !== null &&
        item.status !== 'settled' &&
        item.due_date < props.today
    );
}

function dueBucket(
    item: CreditDebtItem,
): 'overdue' | 'current_month' | 'future' | 'settled' {
    if (item.status === 'settled') {
        return 'settled';
    }

    if (isOverdue(item)) {
        return 'overdue';
    }

    if (item.due_date === null) {
        return 'future';
    }

    if (item.due_date > props.today) {
        return 'future';
    }

    const due = new Date(`${item.due_date}T00:00:00`);
    const selectedMonth =
        filters.value.month && filters.value.month !== 'all'
            ? Number(filters.value.month) - 1
            : new Date(`${props.today}T00:00:00`).getMonth();
    const selectedYear = Number(filters.value.year);

    return due.getMonth() === selectedMonth &&
        due.getFullYear() === selectedYear
        ? 'current_month'
        : 'future';
}

function dueText(item: CreditDebtItem): string {
    if (!item.due_date) {
        return '—';
    }

    const due = new Date(`${item.due_date}T00:00:00`);
    const today = new Date(`${props.today}T00:00:00`);
    const days = Math.round((due.getTime() - today.getTime()) / 86400000);

    if (days === 0) {
        return t('creditsDebts.today');
    }

    if (days < 0) {
        return t('creditsDebts.daysLate', { count: Math.abs(days) });
    }

    return t('creditsDebts.inDays', { count: days });
}

function progress(item: CreditDebtItem): number {
    const total = Number(item.total_amount);

    if (!total) {
        return 0;
    }

    return Math.min(100, Math.round((Number(item.paid_amount) / total) * 100));
}

function groupedItems(items: CreditDebtItem[]) {
    const groups = [
        {
            key: 'overdue',
            label: t('creditsDebts.overdue'),
            items: [] as CreditDebtItem[],
        },
        {
            key: 'current_month',
            label: t('creditsDebts.currentMonth'),
            items: [] as CreditDebtItem[],
        },
        {
            key: 'future',
            label: t('creditsDebts.future'),
            items: [] as CreditDebtItem[],
        },
        {
            key: 'settled',
            label: t('creditsDebts.settled'),
            items: [] as CreditDebtItem[],
        },
    ];

    for (const item of items) {
        groups.find((group) => group.key === dueBucket(item))?.items.push(item);
    }

    return groups.filter((group) => group.items.length > 0);
}

function groupTotal(items: CreditDebtItem[]): string {
    const currency = items[0]?.currency_code ?? fallbackCurrency.value;
    const total = items.reduce(
        (sum, item) => sum + Number(item.remaining_amount),
        0,
    );

    return amount(total.toFixed(2), currency);
}

function applyFilters(): void {
    const query = Object.fromEntries(
        Object.entries(filters.value).filter(
            ([, value]) => value && value !== 'all',
        ),
    );

    router.get(creditsDebtsIndex().url, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
    isFilterOpen.value = false;
}

function resetFilters(): void {
    filters.value = {
        search: '',
        type: 'all',
        status: 'all',
        due_bucket: 'all',
        reference_uuid: 'all',
        account_uuid: 'all',
        category_uuid: 'all',
        month: 'all',
        year:
            String(
                props.options.years.find(
                    (option) => option.value === new Date().getFullYear(),
                )?.value ??
                    props.options.years.at(-1)?.value ??
                    new Date().getFullYear(),
            ) ?? '',
    };
    applyFilters();
}

function resetItemForm(type: 'credit' | 'debit' = 'credit'): void {
    itemForm.defaults({
        type,
        description: '',
        total_amount: '',
        currency_code: fallbackCurrency.value,
        reference_uuid: '',
        account_uuid: '',
        category_uuid: '',
        due_date: '',
        note: '',
    });
    itemForm.reset();
}

function openCreate(type: 'credit' | 'debit' = 'credit'): void {
    editingItem.value = null;
    resetItemForm(type);
    itemForm.clearErrors();
    isItemSheetOpen.value = true;
}

function selectItem(uuid: string, openMobileDetail = false): void {
    selectedUuid.value = uuid;

    if (
        openMobileDetail &&
        typeof window !== 'undefined' &&
        window.matchMedia('(max-width: 1023px)').matches
    ) {
        isMobileDetailOpen.value = true;
    }
}

function openEdit(item: CreditDebtItem): void {
    editingItem.value = item;
    itemForm.clearErrors();
    itemForm.type = item.type;
    itemForm.description = item.description;
    itemForm.total_amount = item.total_amount;
    itemForm.currency_code = item.currency_code;
    itemForm.reference_uuid = item.reference?.uuid ?? '';
    itemForm.account_uuid = item.account?.value ?? item.account?.uuid ?? '';
    itemForm.category_uuid = item.category?.value ?? item.category?.uuid ?? '';
    itemForm.due_date = item.due_date ?? '';
    itemForm.note = item.note ?? '';
    itemForm.currency_code = item.currency_code || fallbackCurrency.value;
    isItemSheetOpen.value = true;
}

function validateItemForm(): boolean {
    let isValid = true;

    itemForm.clearErrors();

    if (!itemForm.type) {
        itemForm.setError('type', t('creditsDebts.validation.typeRequired'));
        isValid = false;
    }

    if (!itemForm.description.trim()) {
        itemForm.setError(
            'description',
            t('creditsDebts.validation.descriptionRequired'),
        );
        isValid = false;
    }

    const itemTotalAmount = numericMoney(itemForm.total_amount);

    if (
        !itemForm.total_amount ||
        !Number.isFinite(itemTotalAmount) ||
        itemTotalAmount <= 0
    ) {
        itemForm.setError(
            'total_amount',
            t('creditsDebts.validation.amountRequired'),
        );
        isValid = false;
    }

    if (
        itemTotalAmountLocked.value &&
        editingItem.value &&
        itemTotalAmount !== numericMoney(editingItem.value.total_amount)
    ) {
        itemForm.setError(
            'total_amount',
            t('creditsDebts.validation.totalLocked'),
        );
        isValid = false;
    }

    if (!itemForm.account_uuid) {
        itemForm.setError(
            'account_uuid',
            t('creditsDebts.validation.accountRequired'),
        );
        isValid = false;
    }

    if (!itemForm.category_uuid) {
        itemForm.setError(
            'category_uuid',
            t('creditsDebts.validation.categoryRequired'),
        );
        isValid = false;
    }

    if (!itemForm.due_date) {
        itemForm.setError(
            'due_date',
            t('creditsDebts.validation.dueDateRequired'),
        );
        isValid = false;
    }

    return isValid;
}

function validatePaymentForm(): boolean {
    if (!selectedItem.value) {
        return false;
    }

    paymentForm.clearErrors('amount', 'account_uuid', 'account_id', 'paid_at');

    const paymentAmount = numericMoney(paymentForm.amount);
    const remainingAmount = numericMoney(selectedItem.value.remaining_amount);
    let isValid = true;

    if (
        !paymentForm.amount ||
        !Number.isFinite(paymentAmount) ||
        paymentAmount <= 0
    ) {
        paymentForm.setError(
            'amount',
            t('creditsDebts.validation.amountRequired'),
        );

        isValid = false;
    }

    if (Number.isFinite(paymentAmount) && paymentAmount > remainingAmount) {
        paymentForm.setError(
            'amount',
            t('creditsDebts.validation.paymentExceedsRemaining'),
        );

        isValid = false;
    }

    if (!paymentForm.account_uuid) {
        paymentForm.setError(
            'account_uuid',
            t('creditsDebts.validation.accountRequired'),
        );

        isValid = false;
    }

    if (!paymentForm.paid_at) {
        paymentForm.setError(
            'paid_at',
            t('creditsDebts.validation.paidAtRequired'),
        );

        isValid = false;
    }

    return isValid;
}

function submitItem(): void {
    itemForm.currency_code = itemCurrencyCode.value;

    if (!validateItemForm()) {
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isItemSheetOpen.value = false;
            resetItemForm();
            editingItem.value = null;
        },
    };

    if (editingItem.value) {
        itemForm.put(updateCreditDebt(editingItem.value.uuid).url, options);

        return;
    }

    itemForm.post(storeCreditDebt().url, options);
}

function openPayment(item: CreditDebtItem, settle = false): void {
    selectedUuid.value = item.uuid;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.amount = settle ? item.remaining_amount : '';
    paymentForm.paid_at = props.today;
    paymentForm.account_uuid =
        item.account?.value ??
        item.account?.uuid ??
        props.options.accounts[0]?.value ??
        '';
    isPaymentSheetOpen.value = true;
}

function submitPayment(): void {
    if (!selectedItem.value) {
        return;
    }

    if (!validatePaymentForm()) {
        return;
    }

    paymentForm.post(storePayment(selectedItem.value.uuid).url, {
        preserveScroll: true,
        onSuccess: () => {
            isPaymentSheetOpen.value = false;
            paymentForm.defaults({
                amount: '',
                account_uuid: '',
                paid_at: props.today,
                note: '',
            });
            paymentForm.reset();
            router.reload({
                only: ['items', 'summary'],
                preserveScroll: true,
                preserveState: true,
            });
        },
    });
}

function deleteItem(item: CreditDebtItem): void {
    if (!window.confirm(t('creditsDebts.confirmDeleteItem'))) {
        return;
    }

    router.delete(destroyCreditDebt(item.uuid).url, {
        preserveScroll: true,
    });
}

function deletePayment(payment: CreditDebtPayment): void {
    if (
        !selectedItem.value ||
        !window.confirm(t('creditsDebts.confirmDeletePayment'))
    ) {
        return;
    }

    router.delete(
        destroyPayment({
            creditDebtItem: selectedItem.value.uuid,
            payment: payment.uuid,
        }).url,
        { preserveScroll: true },
    );
}

async function createReferenceFromContext(name: string): Promise<void> {
    const referenceName = name.trim();
    itemForm.clearErrors('reference_uuid');

    if (!referenceName || !itemForm.account_uuid || !itemForm.category_uuid) {
        itemForm.setError(
            'reference_uuid',
            t('creditsDebts.createReferenceHelp'),
        );

        return;
    }

    creatingReference.value = true;

    try {
        const response = await fetch(storeTrackedItem().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name: referenceName,
                account_uuid: itemForm.account_uuid,
                category_uuid: itemForm.category_uuid,
                type_key: referenceTypeKey(),
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const firstError = payload?.errors
                ? Object.values(payload.errors)[0]
                : null;
            itemForm.setError(
                'reference_uuid',
                Array.isArray(firstError) && typeof firstError[0] === 'string'
                    ? firstError[0]
                    : t('creditsDebts.createReferenceHelp'),
            );

            return;
        }

        const payload = await response.json();
        const option = payload.item as Option;
        const accountUuid = itemForm.account_uuid;
        localReferences.value = {
            ...localReferences.value,
            [accountUuid]: uniqueOptions([
                ...(localReferences.value[accountUuid] ?? []),
                option,
            ]),
        };
        itemForm.reference_uuid = option.value;
        itemForm.clearErrors('reference_uuid');
    } catch (error) {
        itemForm.setError(
            'reference_uuid',
            error instanceof Error
                ? error.message
                : t('creditsDebts.createReferenceHelp'),
        );
    } finally {
        creatingReference.value = false;
    }
}

const FormSelect = defineComponent({
    props: {
        modelValue: { type: String, required: true },
        label: { type: String, required: true },
        options: { type: Array as () => Option[], required: true },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    setup(selectProps, { emit }) {
        return () =>
            h(
                'label',
                {
                    class: 'grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200',
                },
                [
                    h('span', selectProps.label),
                    h(
                        'select',
                        {
                            class: 'h-10 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                            value: selectProps.modelValue,
                            disabled: selectProps.disabled,
                            onChange: (event: Event) =>
                                emit(
                                    'update:modelValue',
                                    (event.target as HTMLSelectElement).value,
                                ),
                        },
                        selectProps.options.map((option) =>
                            h('option', { value: option.value }, option.label),
                        ),
                    ),
                ],
            );
    },
});

const ListColumn = defineComponent({
    props: {
        title: { type: String, required: true },
        items: { type: Array as () => CreditDebtItem[], required: true },
        type: { type: String as () => 'credit' | 'debit', required: true },
        selectedUuid: { type: String, default: null },
    },
    emits: ['create', 'select'],
    setup(columnProps, { emit }) {
        return () =>
            h('div', { class: 'overflow-hidden' }, [
                h(
                    'div',
                    {
                        class: 'flex items-center justify-between gap-3 border-b border-slate-100 p-5 dark:border-slate-800',
                    },
                    [
                        h('div', { class: 'flex items-center gap-3' }, [
                            h(
                                'div',
                                {
                                    class: cn(
                                        'flex size-12 items-center justify-center rounded-2xl',
                                        columnProps.type === 'credit'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-rose-50 text-rose-700',
                                    ),
                                },
                                columnProps.type === 'credit' ? '↘' : '↗',
                            ),
                            h('div', [
                                h(
                                    'h2',
                                    {
                                        class: 'text-xl font-bold text-slate-950 dark:text-slate-50',
                                    },
                                    columnProps.title,
                                ),
                                h(
                                    'p',
                                    { class: 'text-sm text-slate-500' },
                                    `${columnProps.items.filter((item) => item.status !== 'settled').length} ${t('creditsDebts.toSettle')} · ${columnProps.items.filter((item) => item.status === 'settled').length} ${t('creditsDebts.settledPlural')}`,
                                ),
                            ]),
                        ]),
                        h(
                            'button',
                            {
                                class: 'rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-800',
                                onClick: () => emit('create'),
                            },
                            columnProps.type === 'credit'
                                ? t('creditsDebts.newCredit')
                                : t('creditsDebts.newDebt'),
                        ),
                    ],
                ),
                columnProps.items.length === 0
                    ? h(
                          'p',
                          { class: 'p-8 text-center text-sm text-slate-500' },
                          columnProps.type === 'credit'
                              ? t('creditsDebts.noCredits')
                              : t('creditsDebts.noDebts'),
                      )
                    : groupedItems(columnProps.items).map((group) =>
                          h('section', { key: group.key }, [
                              h(
                                  'div',
                                  {
                                      class: 'flex items-center justify-between bg-slate-50 px-5 py-3 text-sm font-bold dark:bg-slate-900',
                                  },
                                  [
                                      h('span', group.label),
                                      h(SensitiveValue, {
                                          value: groupTotal(group.items),
                                      }),
                                  ],
                              ),
                              ...group.items.map((item) =>
                                  h(
                                      'button',
                                      {
                                          key: item.uuid,
                                          class: cn(
                                              'flex w-full items-center gap-3 border-t border-slate-100 px-5 py-4 text-left transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900',
                                              columnProps.selectedUuid ===
                                                  item.uuid
                                                  ? 'bg-slate-50 ring-1 ring-slate-200 ring-inset dark:bg-slate-900 dark:ring-slate-700'
                                                  : '',
                                          ),
                                          onClick: () =>
                                              emit('select', item.uuid),
                                      },
                                      [
                                          h(
                                              'div',
                                              {
                                                  class: cn(
                                                      'flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white',
                                                      item.type === 'credit'
                                                          ? 'bg-emerald-700'
                                                          : 'bg-rose-700',
                                                  ),
                                              },
                                              initials(item),
                                          ),
                                          h(
                                              'div',
                                              { class: 'min-w-0 flex-1' },
                                              [
                                                  h(
                                                      'div',
                                                      {
                                                          class: 'flex items-center gap-2',
                                                      },
                                                      [
                                                          h(
                                                              Badge,
                                                              {
                                                                  class: statusClass(
                                                                      item,
                                                                  ),
                                                              },
                                                              () =>
                                                                  statusLabel(
                                                                      item,
                                                                  ),
                                                          ),
                                                          h(
                                                              'span',
                                                              {
                                                                  class: 'truncate text-sm font-semibold text-slate-900 dark:text-slate-100',
                                                              },
                                                              referenceName(
                                                                  item,
                                                              ),
                                                          ),
                                                      ],
                                                  ),
                                                  h(
                                                      'p',
                                                      {
                                                          class: 'truncate text-sm text-slate-500',
                                                      },
                                                      item.description,
                                                  ),
                                                  h(
                                                      'p',
                                                      {
                                                          class: 'mt-1 text-xs text-slate-500',
                                                      },
                                                      `${amount(item.paid_amount, item.currency_code)} / ${amount(item.total_amount, item.currency_code)} · ${formatDate(item.due_date)} · ${dueText(item)}`,
                                                  ),
                                              ],
                                          ),
                                          h(SensitiveValue, {
                                              class: cn(
                                                  'font-bold',
                                                  item.type === 'credit'
                                                      ? 'text-emerald-600'
                                                      : 'text-rose-600',
                                              ),
                                              value: amount(
                                                  item.remaining_amount,
                                                  item.currency_code,
                                              ),
                                          }),
                                      ],
                                  ),
                              ),
                          ]),
                      ),
            ]);
    },
});
</script>

<template>
    <Head :title="t('creditsDebts.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5 px-3 py-4 sm:px-6 lg:px-8">
            <header
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h1
                            class="text-2xl font-bold text-slate-950 sm:text-3xl dark:text-slate-50"
                        >
                            {{ t('creditsDebts.title') }}
                        </h1>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:outline-none dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100"
                                        :aria-label="
                                            t('creditsDebts.sectionHelpLabel')
                                        "
                                    >
                                        <CircleHelp class="size-4" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="bottom"
                                    align="start"
                                    class="max-w-80 text-sm leading-5"
                                >
                                    {{ t('creditsDebts.sectionHelp') }}
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ t('creditsDebts.subtitle') }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Select
                        v-model="filters.year"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="h-11 rounded-2xl sm:w-[132px]">
                            <SelectValue
                                :placeholder="t('creditsDebts.year')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.options.years"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        v-model="filters.month"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="h-11 rounded-2xl sm:w-[148px]">
                            <SelectValue
                                :placeholder="t('creditsDebts.month')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.options.months"
                                :key="option.value ?? 'all'"
                                :value="
                                    option.value === null
                                        ? 'all'
                                        : String(option.value)
                                "
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="filters.search"
                            class="h-11 min-w-[280px] rounded-2xl pl-9"
                            :placeholder="t('creditsDebts.searchPlaceholder')"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <Button
                        variant="outline"
                        class="h-11 rounded-2xl"
                        @click="isFilterOpen = true"
                    >
                        <Filter class="mr-2 size-4" />
                        {{ t('creditsDebts.filter') }}
                    </Button>
                    <Button
                        class="h-11 rounded-2xl bg-slate-950 text-white hover:bg-slate-800"
                        @click="openCreate('credit')"
                    >
                        <Plus class="mr-2 size-4" />
                        {{ t('creditsDebts.newItem') }}
                    </Button>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-3xl bg-emerald-50 p-5 text-emerald-800 ring-1 ring-emerald-100 dark:bg-emerald-500/10 dark:ring-emerald-500/20"
                >
                    <div class="flex items-center gap-3 text-sm font-bold">
                        <ArrowDownLeft class="size-5" />
                        {{ t('creditsDebts.receive') }}
                    </div>
                    <p class="mt-5 text-4xl font-bold">
                        <SensitiveValue
                            :value="
                                amount(props.summary.credits_remaining_total)
                            "
                        />
                    </p>
                    <p class="mt-3 text-sm">
                        {{ t('creditsDebts.currentMonth') }}
                        <SensitiveValue
                            :value="
                                amount(
                                    props.summary.current_month_credits_total,
                                )
                            "
                        />
                    </p>
                    <div
                        class="mt-5 grid grid-cols-2 gap-3 border-t border-emerald-900/10 pt-4 text-sm font-semibold"
                    >
                        <span
                            >{{ t('creditsDebts.overduePlural')
                            }}<br /><SensitiveValue
                                :value="
                                    amount(props.summary.overdue_credits_total)
                                "
                        /></span>
                        <span
                            >{{ t('creditsDebts.future') }}<br /><SensitiveValue
                                :value="
                                    amount(props.summary.future_credits_total)
                                "
                        /></span>
                    </div>
                </div>

                <div
                    class="rounded-3xl bg-rose-50 p-5 text-rose-800 ring-1 ring-rose-100 dark:bg-rose-500/10 dark:ring-rose-500/20"
                >
                    <div class="flex items-center gap-3 text-sm font-bold">
                        <ArrowUpRight class="size-5" />
                        {{ t('creditsDebts.pay') }}
                    </div>
                    <p class="mt-5 text-4xl font-bold">
                        <SensitiveValue
                            :value="amount(props.summary.debts_remaining_total)"
                        />
                    </p>
                    <p class="mt-3 text-sm">
                        {{ t('creditsDebts.currentMonth') }}
                        <SensitiveValue
                            :value="
                                amount(props.summary.current_month_debts_total)
                            "
                        />
                    </p>
                    <div
                        class="mt-5 grid grid-cols-2 gap-3 border-t border-rose-900/10 pt-4 text-sm font-semibold"
                    >
                        <span
                            >{{ t('creditsDebts.overduePlural')
                            }}<br /><SensitiveValue
                                :value="
                                    amount(props.summary.overdue_debts_total)
                                "
                        /></span>
                        <span
                            >{{ t('creditsDebts.future') }}<br /><SensitiveValue
                                :value="
                                    amount(props.summary.future_debts_total)
                                "
                        /></span>
                    </div>
                </div>

                <div
                    class="rounded-3xl bg-amber-50 p-5 text-amber-900 ring-1 ring-amber-100 dark:bg-amber-500/10 dark:ring-amber-500/20"
                >
                    <div class="flex items-center gap-3 text-sm font-bold">
                        <CalendarClock class="size-5" />
                        {{ t('creditsDebts.overdue') }}
                    </div>
                    <p class="mt-5 text-4xl font-bold">
                        {{ props.summary.overdue_count }}
                    </p>
                    <p class="mt-3 text-sm">
                        <SensitiveValue
                            :value="
                                amount(
                                    Number(
                                        props.summary.overdue_credits_total,
                                    ) +
                                        Number(
                                            props.summary.overdue_debts_total,
                                        ),
                                )
                            "
                        />
                    </p>
                    <div
                        class="mt-5 grid grid-cols-2 gap-3 border-t border-amber-900/10 pt-4 text-sm font-semibold"
                    >
                        <span
                            >{{ t('creditsDebts.creditsLabel')
                            }}<br /><SensitiveValue
                                :value="
                                    amount(props.summary.overdue_credits_total)
                                "
                        /></span>
                        <span
                            >{{ t('creditsDebts.debtsLabel')
                            }}<br /><SensitiveValue
                                :value="
                                    amount(props.summary.overdue_debts_total)
                                "
                        /></span>
                    </div>
                </div>

                <div
                    class="rounded-3xl bg-indigo-50 p-5 text-indigo-800 ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:ring-indigo-500/20"
                >
                    <div class="flex items-center gap-3 text-sm font-bold">
                        <Sparkles class="size-5" />
                        {{ t('creditsDebts.expectedNet') }}
                    </div>
                    <p class="mt-5 text-4xl font-bold">
                        <SensitiveValue
                            :value="amount(props.summary.net_expected_total)"
                        />
                    </p>
                    <p class="mt-3 text-sm">
                        {{ t('creditsDebts.netBreakdown') }}
                    </p>
                    <div
                        class="mt-5 grid grid-cols-2 gap-3 border-t border-indigo-900/10 pt-4 text-sm font-semibold"
                    >
                        <span
                            >{{ t('creditsDebts.creditsLabel')
                            }}<br /><SensitiveValue
                                :value="
                                    amount(
                                        props.summary.credits_remaining_total,
                                    )
                                "
                        /></span>
                        <span
                            >{{ t('creditsDebts.debtsLabel')
                            }}<br /><SensitiveValue
                                :value="
                                    amount(props.summary.debts_remaining_total)
                                "
                        /></span>
                    </div>
                </div>
            </section>

            <div class="lg:hidden">
                <div
                    class="grid grid-cols-3 rounded-2xl bg-slate-100 p-1 dark:bg-slate-900"
                >
                    <button
                        v-for="type in mobileTypes"
                        :key="type"
                        type="button"
                        :class="
                            cn(
                                'rounded-xl px-3 py-2 text-sm font-semibold',
                                activeMobileType === type
                                    ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-800 dark:text-slate-50'
                                    : 'text-slate-500',
                            )
                        "
                        @click="activeMobileType = type"
                    >
                        {{
                            type === 'all'
                                ? t('creditsDebts.all')
                                : type === 'credit'
                                  ? t('creditsDebts.receive')
                                  : t('creditsDebts.pay')
                        }}
                    </button>
                </div>
            </div>

            <section
                class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(360px,0.95fr)]"
            >
                <div
                    :class="
                        cn(
                            'rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950',
                            activeMobileType !== 'debit'
                                ? ''
                                : 'hidden lg:block',
                        )
                    "
                >
                    <ListColumn
                        :title="t('creditsDebts.credits')"
                        :items="credits"
                        type="credit"
                        :selected-uuid="selectedUuid ?? ''"
                        @create="openCreate('credit')"
                        @select="selectItem($event, true)"
                    />
                </div>

                <div
                    :class="
                        cn(
                            'rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950',
                            activeMobileType !== 'credit'
                                ? ''
                                : 'hidden lg:block',
                        )
                    "
                >
                    <ListColumn
                        :title="t('creditsDebts.debts')"
                        :items="debts"
                        type="debit"
                        :selected-uuid="selectedUuid ?? ''"
                        @create="openCreate('debit')"
                        @select="selectItem($event, true)"
                    />
                </div>

                <aside
                    class="hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:block dark:border-slate-800 dark:bg-slate-950"
                >
                    <div v-if="selectedItem" class="flex h-full flex-col">
                        <div
                            class="flex items-start gap-4 border-b border-slate-100 p-6 dark:border-slate-800"
                        >
                            <div
                                :class="
                                    cn(
                                        'flex size-14 shrink-0 items-center justify-center rounded-full text-lg font-bold text-white',
                                        selectedItem.type === 'credit'
                                            ? 'bg-emerald-700'
                                            : 'bg-rose-700',
                                    )
                                "
                            >
                                {{ initials(selectedItem) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        :class="
                                            cn(
                                                'text-xs font-bold tracking-wide uppercase',
                                                selectedItem.type === 'credit'
                                                    ? 'text-emerald-700'
                                                    : 'text-rose-700',
                                            )
                                        "
                                    >
                                        {{
                                            selectedItem.type === 'credit'
                                                ? t('creditsDebts.credit')
                                                : t('creditsDebts.debit')
                                        }}
                                    </span>
                                    <Badge :class="statusClass(selectedItem)">{{
                                        statusLabel(selectedItem)
                                    }}</Badge>
                                </div>
                                <h2
                                    class="mt-1 truncate text-2xl font-bold text-slate-950 dark:text-slate-50"
                                >
                                    {{ referenceName(selectedItem) }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{ selectedItem.description }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-5 p-6">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-400 uppercase"
                                    >
                                        {{ t('creditsDebts.totalAmount') }}
                                    </p>
                                    <p class="mt-1 text-xl font-bold">
                                        <SensitiveValue
                                            :value="
                                                amount(
                                                    selectedItem.total_amount,
                                                    selectedItem.currency_code,
                                                )
                                            "
                                        />
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-400 uppercase"
                                    >
                                        {{ paymentLabel(selectedItem) }}
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-bold text-emerald-600"
                                    >
                                        <SensitiveValue
                                            :value="
                                                amount(
                                                    selectedItem.paid_amount,
                                                    selectedItem.currency_code,
                                                )
                                            "
                                        />
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-400 uppercase"
                                    >
                                        {{ t('creditsDebts.remaining') }}
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-bold text-amber-700"
                                    >
                                        <SensitiveValue
                                            :value="
                                                amount(
                                                    selectedItem.remaining_amount,
                                                    selectedItem.currency_code,
                                                )
                                            "
                                        />
                                    </p>
                                </div>
                            </div>

                            <div>
                                <div
                                    class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                    role="progressbar"
                                    :aria-valuenow="progress(selectedItem)"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <div
                                        class="h-full rounded-full bg-emerald-600"
                                        :style="{
                                            width: `${progress(selectedItem)}%`,
                                        }"
                                    />
                                </div>
                                <div
                                    class="mt-2 flex justify-between text-sm text-slate-500"
                                >
                                    <span>{{ progress(selectedItem) }}%</span>
                                    <span
                                        >{{
                                            formatDate(selectedItem.due_date)
                                        }}
                                        · {{ dueText(selectedItem) }}</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-bold">
                                        {{ t('creditsDebts.paymentsHistory') }}
                                    </h3>
                                    <p class="text-sm text-slate-500">
                                        {{ selectedItem.payments_count }}
                                        {{
                                            selectedItem.type === 'credit'
                                                ? t(
                                                      'creditsDebts.registerCreditPayment',
                                                  ).toLowerCase()
                                                : t(
                                                      'creditsDebts.registerDebtPayment',
                                                  ).toLowerCase()
                                        }}
                                    </p>
                                </div>
                                <Button
                                    v-if="selectedItem.status !== 'settled'"
                                    class="rounded-2xl bg-slate-950 text-white"
                                    @click="openPayment(selectedItem)"
                                >
                                    <Plus class="mr-2 size-4" />
                                    {{ paymentActionLabel(selectedItem) }}
                                </Button>
                            </div>

                            <div class="space-y-3">
                                <p
                                    v-if="!selectedItem.payments?.length"
                                    class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-800"
                                >
                                    {{ t('creditsDebts.noPayments') }}
                                </p>
                                <div
                                    v-for="payment in selectedItem.payments"
                                    :key="payment.uuid"
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="flex size-8 items-center justify-center rounded-xl border-2 border-emerald-500 text-emerald-600"
                                    >
                                        <Check class="size-4" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold">
                                            {{ formatDate(payment.paid_at) }}
                                        </p>
                                        <p
                                            class="truncate text-sm text-slate-500"
                                        >
                                            {{
                                                payment.note ??
                                                payment.account?.label
                                            }}
                                        </p>
                                    </div>
                                    <SensitiveValue
                                        class="font-bold text-emerald-600"
                                        :value="
                                            amount(
                                                payment.amount,
                                                payment.currency_code,
                                            )
                                        "
                                    />
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="rounded-xl text-rose-500"
                                        :aria-label="t('creditsDebts.delete')"
                                        @click="deletePayment(payment)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                                <div
                                    v-if="
                                        selectedItem.payments?.length &&
                                        Number(selectedItem.remaining_amount) >
                                            0
                                    "
                                    class="ml-4 flex items-center gap-3"
                                >
                                    <div
                                        class="size-8 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700"
                                    />
                                    <div
                                        class="flex flex-1 items-center justify-between rounded-2xl border border-dashed border-slate-300 px-4 py-3 dark:border-slate-700"
                                    >
                                        <div>
                                            <p class="font-bold">
                                                {{
                                                    t('creditsDebts.remaining')
                                                }}
                                            </p>
                                            <p class="text-sm text-slate-500">
                                                {{
                                                    selectedItem.type ===
                                                    'credit'
                                                        ? t(
                                                              'creditsDebts.remainingReceiveBy',
                                                              {
                                                                  date: formatDate(
                                                                      selectedItem.due_date,
                                                                  ),
                                                              },
                                                          )
                                                        : t(
                                                              'creditsDebts.remainingPayBy',
                                                              {
                                                                  date: formatDate(
                                                                      selectedItem.due_date,
                                                                  ),
                                                              },
                                                          )
                                                }}
                                            </p>
                                        </div>
                                        <SensitiveValue
                                            class="font-bold text-amber-700"
                                            :value="
                                                amount(
                                                    selectedItem.remaining_amount,
                                                    selectedItem.currency_code,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-auto grid grid-cols-3 gap-3 border-t border-slate-100 p-5 dark:border-slate-800"
                        >
                            <Button
                                variant="outline"
                                class="rounded-2xl"
                                :disabled="selectedItem.status === 'settled'"
                                @click="openPayment(selectedItem, true)"
                            >
                                <Check class="mr-2 size-4" />
                                {{ settleLabel(selectedItem) }}
                            </Button>
                            <Button
                                variant="outline"
                                class="rounded-2xl"
                                @click="openEdit(selectedItem)"
                            >
                                {{ t('creditsDebts.edit') }}
                            </Button>
                            <Button
                                variant="outline"
                                class="rounded-2xl text-rose-600 hover:text-rose-700"
                                @click="deleteItem(selectedItem)"
                            >
                                {{ t('creditsDebts.delete') }}
                            </Button>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex min-h-[420px] items-center justify-center p-8 text-center text-sm text-slate-500"
                    >
                        {{ t('creditsDebts.noSelection') }}
                    </div>
                </aside>
            </section>
        </div>

        <Sheet v-model:open="isMobileDetailOpen">
            <SheetContent
                side="right"
                class="w-full overflow-y-auto p-0 sm:max-w-md lg:hidden"
            >
                <SheetTitle class="sr-only">
                    {{
                        selectedItem
                            ? referenceName(selectedItem)
                            : t('creditsDebts.title')
                    }}
                </SheetTitle>
                <SheetDescription class="sr-only">
                    {{ t('creditsDebts.detailDescription') }}
                </SheetDescription>
                <div
                    v-if="selectedItem"
                    class="flex min-h-dvh flex-col bg-white dark:bg-slate-950"
                >
                    <header
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800"
                    >
                        <button
                            type="button"
                            class="flex size-11 items-center justify-center rounded-full border border-slate-200 text-slate-500 dark:border-slate-800"
                            :aria-label="t('creditsDebts.close')"
                            @click="isMobileDetailOpen = false"
                        >
                            <ArrowLeft class="size-4" />
                        </button>
                        <h2
                            class="text-lg font-bold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                selectedItem.type === 'credit'
                                    ? t('creditsDebts.credit')
                                    : t('creditsDebts.debit')
                            }}
                        </h2>
                        <span class="size-11" aria-hidden="true" />
                    </header>

                    <section
                        :class="
                            cn(
                                'px-5 py-6',
                                selectedItem.type === 'credit'
                                    ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100'
                                    : 'bg-rose-50 text-rose-900 dark:bg-rose-500/10 dark:text-rose-100',
                            )
                        "
                    >
                        <div class="flex items-start gap-4">
                            <div
                                :class="
                                    cn(
                                        'flex size-16 shrink-0 items-center justify-center rounded-full text-xl font-bold text-white',
                                        selectedItem.type === 'credit'
                                            ? 'bg-emerald-700'
                                            : 'bg-rose-700',
                                    )
                                "
                            >
                                {{ initials(selectedItem) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-xl font-bold">
                                    {{ referenceName(selectedItem) }}
                                </h3>
                                <p class="truncate text-sm opacity-80">
                                    {{ selectedItem.description }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 flex items-end gap-2">
                            <SensitiveValue
                                class="text-5xl font-bold"
                                :value="
                                    amount(
                                        selectedItem.remaining_amount,
                                        selectedItem.currency_code,
                                    )
                                "
                            />
                            <span class="pb-2 text-sm font-semibold opacity-75">
                                ·
                                {{ t('creditsDebts.remaining').toLowerCase() }}
                            </span>
                        </div>
                        <div
                            class="mt-4 h-2 overflow-hidden rounded-full bg-white/70 dark:bg-slate-900"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-600"
                                :style="{ width: `${progress(selectedItem)}%` }"
                            />
                        </div>
                        <div
                            class="mt-3 flex items-center justify-between text-sm font-semibold"
                        >
                            <span>
                                <SensitiveValue
                                    :value="
                                        amount(
                                            selectedItem.paid_amount,
                                            selectedItem.currency_code,
                                        )
                                    "
                                />
                                /
                                <SensitiveValue
                                    :value="
                                        amount(
                                            selectedItem.total_amount,
                                            selectedItem.currency_code,
                                        )
                                    "
                                />
                            </span>
                            <Badge :class="statusClass(selectedItem)">
                                {{ statusLabel(selectedItem) }}
                            </Badge>
                        </div>
                    </section>

                    <section class="grid grid-cols-2 gap-3 px-5 py-5">
                        <div
                            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <p
                                class="text-xs font-bold tracking-wide text-slate-400 uppercase"
                            >
                                {{ t('creditsDebts.dueDate') }}
                            </p>
                            <p
                                class="mt-2 text-lg font-bold text-slate-950 dark:text-slate-50"
                            >
                                {{ formatDate(selectedItem.due_date) }}
                            </p>
                            <p class="text-sm font-semibold text-amber-700">
                                {{ dueText(selectedItem) }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <p
                                class="text-xs font-bold tracking-wide text-slate-400 uppercase"
                            >
                                {{ t('creditsDebts.totalAmount') }}
                            </p>
                            <p
                                class="mt-2 text-lg font-bold text-slate-950 dark:text-slate-50"
                            >
                                <SensitiveValue
                                    :value="
                                        amount(
                                            selectedItem.total_amount,
                                            selectedItem.currency_code,
                                        )
                                    "
                                />
                            </p>
                            <p class="text-sm text-slate-500">
                                {{ selectedItem.payments_count }}
                                {{ t('creditsDebts.paymentsShort') }}
                            </p>
                        </div>
                    </section>

                    <section class="flex-1 px-5 pb-28">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-bold">
                                {{ t('creditsDebts.paymentsHistory') }}
                            </h3>
                            <Button
                                v-if="selectedItem.status !== 'settled'"
                                class="rounded-2xl bg-slate-950 text-white"
                                @click="openPayment(selectedItem)"
                            >
                                <Plus class="mr-2 size-4" />
                                {{ paymentActionLabel(selectedItem) }}
                            </Button>
                        </div>
                        <div class="space-y-4">
                            <p
                                v-if="!selectedItem.payments?.length"
                                class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-800"
                            >
                                {{ t('creditsDebts.noPayments') }}
                            </p>
                            <div
                                v-for="payment in selectedItem.payments"
                                :key="payment.uuid"
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex size-9 items-center justify-center rounded-xl border-2 border-emerald-500 text-emerald-600"
                                >
                                    <Check class="size-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="font-bold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ formatDate(payment.paid_at) }}
                                    </p>
                                    <p class="truncate text-sm text-slate-500">
                                        {{
                                            payment.note ??
                                            payment.account?.label
                                        }}
                                    </p>
                                </div>
                                <SensitiveValue
                                    class="font-bold text-emerald-600"
                                    :value="
                                        amount(
                                            payment.amount,
                                            payment.currency_code,
                                        )
                                    "
                                />
                            </div>
                            <div
                                v-if="
                                    selectedItem.payments?.length &&
                                    Number(selectedItem.remaining_amount) > 0
                                "
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="size-9 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700"
                                />
                                <div
                                    class="flex flex-1 items-center justify-between rounded-2xl border border-dashed border-slate-300 px-4 py-3 dark:border-slate-700"
                                >
                                    <div>
                                        <p
                                            class="font-bold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ t('creditsDebts.remaining') }}
                                        </p>
                                        <p class="text-sm text-slate-500">
                                            {{
                                                selectedItem.type === 'credit'
                                                    ? t(
                                                          'creditsDebts.remainingReceiveBy',
                                                          {
                                                              date: formatDate(
                                                                  selectedItem.due_date,
                                                              ),
                                                          },
                                                      )
                                                    : t(
                                                          'creditsDebts.remainingPayBy',
                                                          {
                                                              date: formatDate(
                                                                  selectedItem.due_date,
                                                              ),
                                                          },
                                                      )
                                            }}
                                        </p>
                                    </div>
                                    <SensitiveValue
                                        class="font-bold text-amber-700"
                                        :value="
                                            amount(
                                                selectedItem.remaining_amount,
                                                selectedItem.currency_code,
                                            )
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <div
                        class="fixed inset-x-0 bottom-0 z-10 grid grid-cols-[1fr_auto] gap-3 border-t border-slate-200 bg-white/95 px-5 py-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] dark:border-slate-800 dark:bg-slate-950/95"
                    >
                        <Button
                            variant="outline"
                            class="rounded-2xl"
                            :disabled="selectedItem.status === 'settled'"
                            @click="openPayment(selectedItem, true)"
                        >
                            <Check class="mr-2 size-4" />
                            {{ t('creditsDebts.settleAll') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="rounded-2xl"
                            @click="openEdit(selectedItem)"
                        >
                            {{ t('creditsDebts.edit') }}
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <Sheet v-model:open="isFilterOpen">
            <SheetContent
                side="right"
                class="w-full overflow-y-auto p-6 sm:max-w-md"
            >
                <SheetHeader class="text-left">
                    <SheetTitle>{{ t('creditsDebts.filter') }}</SheetTitle>
                    <SheetDescription>{{
                        t('creditsDebts.subtitle')
                    }}</SheetDescription>
                </SheetHeader>
                <div class="mt-6 grid gap-4">
                    <FormSelect
                        v-model="filters.year"
                        :label="t('creditsDebts.year')"
                        :options="
                            props.options.years.map((option) => ({
                                value: String(option.value),
                                label: option.label,
                            }))
                        "
                    />
                    <FormSelect
                        v-model="filters.month"
                        :label="t('creditsDebts.month')"
                        :options="
                            props.options.months.map((option) => ({
                                value:
                                    option.value === null
                                        ? 'all'
                                        : String(option.value),
                                label: option.label,
                            }))
                        "
                    />
                    <FormSelect
                        v-model="filters.type"
                        :label="t('creditsDebts.type')"
                        :options="[
                            { value: 'all', label: t('creditsDebts.all') },
                            {
                                value: 'credit',
                                label: t('creditsDebts.credit'),
                            },
                            { value: 'debit', label: t('creditsDebts.debit') },
                        ]"
                    />
                    <FormSelect
                        v-model="filters.status"
                        :label="t('creditsDebts.status')"
                        :options="[
                            { value: 'all', label: t('creditsDebts.all') },
                            { value: 'open', label: t('creditsDebts.open') },
                            {
                                value: 'partial',
                                label: t('creditsDebts.partial'),
                            },
                            {
                                value: 'settled',
                                label: t('creditsDebts.settled'),
                            },
                        ]"
                    />
                    <FormSelect
                        v-model="filters.due_bucket"
                        :label="t('creditsDebts.dueBucket')"
                        :options="[
                            { value: 'all', label: t('creditsDebts.all') },
                            {
                                value: 'overdue',
                                label: t('creditsDebts.overdue'),
                            },
                            {
                                value: 'current_month',
                                label: t('creditsDebts.currentMonth'),
                            },
                            {
                                value: 'future',
                                label: t('creditsDebts.future'),
                            },
                        ]"
                    />
                    <FormSelect
                        v-model="filters.account_uuid"
                        :label="t('creditsDebts.account')"
                        :options="[
                            { value: 'all', label: t('creditsDebts.all') },
                            ...accountOptions,
                        ]"
                    />
                    <FormSelect
                        v-model="filters.category_uuid"
                        :label="t('creditsDebts.category')"
                        :options="[
                            { value: 'all', label: t('creditsDebts.all') },
                            ...filterCategories,
                        ]"
                    />
                    <FormSelect
                        v-model="filters.reference_uuid"
                        :label="t('creditsDebts.reference')"
                        :options="[
                            { value: 'all', label: t('creditsDebts.all') },
                            ...filterReferences,
                        ]"
                    />
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <Button
                            variant="outline"
                            class="rounded-2xl"
                            @click="resetFilters"
                            >{{ t('creditsDebts.cancel') }}</Button
                        >
                        <Button class="rounded-2xl" @click="applyFilters">{{
                            t('creditsDebts.filter')
                        }}</Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <Sheet v-model:open="isItemSheetOpen">
            <SheetContent
                side="right"
                class="w-full overflow-y-auto p-6 sm:max-w-xl"
            >
                <SheetHeader class="text-left">
                    <SheetTitle>{{
                        editingItem
                            ? t('creditsDebts.edit')
                            : t('creditsDebts.newItem')
                    }}</SheetTitle>
                    <SheetDescription>{{
                        t('creditsDebts.subtitle')
                    }}</SheetDescription>
                </SheetHeader>
                <form
                    class="mt-6 grid gap-4"
                    novalidate
                    @submit.prevent="submitItem"
                >
                    <div class="grid gap-2">
                        <Label>{{ t('creditsDebts.type') }}</Label>
                        <div
                            class="grid grid-cols-2 gap-3"
                            role="radiogroup"
                            :aria-label="t('creditsDebts.type')"
                        >
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="itemForm.type === 'credit'"
                                class="rounded-[22px] border px-4 py-3 text-left transition-all"
                                :class="
                                    cn(
                                        itemForm.type === 'credit'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200',
                                        editingItem?.payments_count
                                            ? 'cursor-not-allowed opacity-60'
                                            : '',
                                    )
                                "
                                :disabled="Boolean(editingItem?.payments_count)"
                                @click="itemForm.type = 'credit'"
                            >
                                <span class="block text-sm font-bold">{{
                                    t('creditsDebts.credit')
                                }}</span>
                                <span class="mt-1 block text-xs opacity-75">{{
                                    t('creditsDebts.receive')
                                }}</span>
                            </button>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="itemForm.type === 'debit'"
                                class="rounded-[22px] border px-4 py-3 text-left transition-all"
                                :class="
                                    cn(
                                        itemForm.type === 'debit'
                                            ? 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-100'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200',
                                        editingItem?.payments_count
                                            ? 'cursor-not-allowed opacity-60'
                                            : '',
                                    )
                                "
                                :disabled="Boolean(editingItem?.payments_count)"
                                @click="itemForm.type = 'debit'"
                            >
                                <span class="block text-sm font-bold">{{
                                    t('creditsDebts.debit')
                                }}</span>
                                <span class="mt-1 block text-xs opacity-75">{{
                                    t('creditsDebts.pay')
                                }}</span>
                            </button>
                        </div>
                        <InputError :message="itemForm.errors.type" />
                    </div>
                    <div>
                        <Label for="credit-debt-description">{{
                            t('creditsDebts.description')
                        }}</Label>
                        <Input
                            id="credit-debt-description"
                            v-model="itemForm.description"
                            class="mt-1"
                        />
                        <InputError :message="itemForm.errors.description" />
                    </div>
                    <MobileAmountInput
                        id="credit-debt-total-amount"
                        v-model="itemForm.total_amount"
                        :label="t('creditsDebts.total')"
                        :mobile-title="t('creditsDebts.total')"
                        :format-locale="formatLocale"
                        :currency-code="itemCurrencyCode"
                        :error="itemForm.errors.total_amount"
                        :disabled="itemTotalAmountLocked"
                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                    />
                    <p
                        v-if="itemTotalAmountLocked"
                        class="-mt-2 text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ t('creditsDebts.totalLockedHint') }}
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>{{ t('creditsDebts.account') }}</Label>
                            <MobileSearchableSelect
                                v-model="itemForm.account_uuid"
                                :options="accountOptions"
                                :placeholder="
                                    t('creditsDebts.placeholders.selectAccount')
                                "
                                :search-placeholder="
                                    t('creditsDebts.placeholders.searchAccount')
                                "
                                :empty-label="
                                    t(
                                        'creditsDebts.placeholders.noSearchResults',
                                    )
                                "
                                clearable
                                :teleport="false"
                                :mobile-title="t('creditsDebts.account')"
                                trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                content-class="z-[260]"
                            />
                            <InputError :message="itemAccountError" />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ t('creditsDebts.currency') }}</Label>
                            <div
                                class="flex h-11 items-center rounded-2xl border border-dashed border-slate-200 px-3 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200"
                            >
                                {{ itemCurrencyCode }}
                            </div>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    hasAccountCurrencyConversion
                                        ? t(
                                              'creditsDebts.currencyConversionHint',
                                              {
                                                  account:
                                                      selectedItemAccount?.currency_code,
                                                  base: itemCurrencyCode,
                                              },
                                          )
                                        : t('creditsDebts.currencyBaseHint', {
                                              base: itemCurrencyCode,
                                          })
                                }}
                            </p>
                            <InputError
                                :message="itemForm.errors.currency_code"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ t('creditsDebts.category') }}</Label>
                        <MobileSearchableSelect
                            v-model="itemForm.category_uuid"
                            :options="selectedAccountCategories"
                            :placeholder="
                                t('creditsDebts.placeholders.selectCategory')
                            "
                            :search-placeholder="
                                t('creditsDebts.placeholders.searchCategory')
                            "
                            :empty-label="
                                t('creditsDebts.placeholders.noSearchResults')
                            "
                            :disabled="itemForm.account_uuid === ''"
                            clearable
                            hierarchical
                            :teleport="false"
                            :mobile-title="t('creditsDebts.category')"
                            trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                            content-class="z-[260]"
                        />
                        <InputError :message="itemCategoryError" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ t('creditsDebts.reference') }}</Label>
                        <MobileSearchableSelect
                            v-model="itemForm.reference_uuid"
                            :options="[
                                {
                                    value: '',
                                    label: t('creditsDebts.placeholders.none'),
                                },
                                ...selectedAccountReferences,
                            ]"
                            :placeholder="
                                t('creditsDebts.referencePlaceholder')
                            "
                            :search-placeholder="
                                t('creditsDebts.placeholders.searchReference')
                            "
                            :empty-label="
                                t('creditsDebts.placeholders.noSearchResults')
                            "
                            :disabled="
                                itemForm.account_uuid === '' ||
                                itemForm.category_uuid === ''
                            "
                            clearable
                            creatable
                            :creating="creatingReference"
                            :create-label="
                                t('creditsDebts.createReferenceAction')
                            "
                            :teleport="false"
                            :mobile-title="t('creditsDebts.reference')"
                            trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                            content-class="z-[260]"
                            @create-option="createReferenceFromContext"
                        />
                        <InputError :message="itemReferenceError" />
                    </div>
                    <div>
                        <Label for="credit-debt-due-date">{{
                            t('creditsDebts.dueDate')
                        }}</Label>
                        <Input
                            id="credit-debt-due-date"
                            v-model="itemForm.due_date"
                            class="mt-1"
                            type="date"
                        />
                        <InputError :message="itemForm.errors.due_date" />
                    </div>
                    <div>
                        <Label for="credit-debt-note">{{
                            t('creditsDebts.note')
                        }}</Label>
                        <textarea
                            id="credit-debt-note"
                            v-model="itemForm.note"
                            class="mt-1 min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="isItemSheetOpen = false"
                            >{{ t('creditsDebts.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            class="rounded-2xl"
                            :disabled="itemForm.processing"
                            >{{
                                editingItem
                                    ? t('creditsDebts.update')
                                    : t('creditsDebts.create')
                            }}</Button
                        >
                    </div>
                </form>
            </SheetContent>
        </Sheet>

        <Sheet v-model:open="isPaymentSheetOpen">
            <SheetContent
                side="right"
                class="w-full overflow-y-auto p-6 sm:max-w-md"
            >
                <SheetHeader class="text-left">
                    <SheetTitle>{{
                        selectedItem
                            ? paymentActionLabel(selectedItem)
                            : t('creditsDebts.registerDebtPayment')
                    }}</SheetTitle>
                    <SheetDescription v-if="selectedItem">
                        {{ t('creditsDebts.maxRemaining') }}:
                        {{
                            amount(
                                selectedItem.remaining_amount,
                                selectedItem.currency_code,
                            )
                        }}
                    </SheetDescription>
                </SheetHeader>
                <form
                    v-if="selectedItem"
                    class="mt-6 grid gap-4"
                    novalidate
                    @submit.prevent="submitPayment"
                >
                    <MobileAmountInput
                        id="credit-debt-payment-amount"
                        v-model="paymentForm.amount"
                        :label="
                            selectedItem.type === 'credit'
                                ? t('creditsDebts.amountReceived')
                                : t('creditsDebts.amountPaid')
                        "
                        :mobile-title="
                            selectedItem.type === 'credit'
                                ? t('creditsDebts.amountReceived')
                                : t('creditsDebts.amountPaid')
                        "
                        :mobile-description="`${t('creditsDebts.maxRemaining')}: ${amount(
                            selectedItem.remaining_amount,
                            selectedItem.currency_code,
                        )}`"
                        :format-locale="formatLocale"
                        :currency-code="selectedItem.currency_code"
                        :error="paymentForm.errors.amount"
                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-2xl"
                        @click="
                            paymentForm.amount = selectedItem.remaining_amount
                        "
                    >
                        {{ t('creditsDebts.settleRemaining') }}
                    </Button>
                    <div class="grid gap-2">
                        <Label>{{ t('creditsDebts.account') }}</Label>
                        <MobileSearchableSelect
                            v-model="paymentForm.account_uuid"
                            :options="accountOptions"
                            :placeholder="
                                t('creditsDebts.placeholders.selectAccount')
                            "
                            :search-placeholder="
                                t('creditsDebts.placeholders.searchAccount')
                            "
                            :empty-label="
                                t('creditsDebts.placeholders.noSearchResults')
                            "
                            :teleport="false"
                            :mobile-title="t('creditsDebts.account')"
                            trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                            content-class="z-[260]"
                        />
                        <InputError :message="paymentAccountError" />
                    </div>
                    <div>
                        <Label for="credit-debt-paid-at">{{
                            t('creditsDebts.paidAt')
                        }}</Label>
                        <Input
                            id="credit-debt-paid-at"
                            v-model="paymentForm.paid_at"
                            class="mt-1"
                            type="date"
                        />
                        <InputError :message="paymentForm.errors.paid_at" />
                    </div>
                    <div>
                        <Label for="credit-debt-payment-note">{{
                            t('creditsDebts.note')
                        }}</Label>
                        <textarea
                            id="credit-debt-payment-note"
                            v-model="paymentForm.note"
                            class="mt-1 min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="isPaymentSheetOpen = false"
                            >{{ t('creditsDebts.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            class="rounded-2xl"
                            :disabled="paymentForm.processing"
                            >{{ t('creditsDebts.save') }}</Button
                        >
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
