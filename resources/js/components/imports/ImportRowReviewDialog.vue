<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ImportCategoryOption,
    ImportDestinationAccountOption,
    ImportReferenceOption,
    ImportRowItem,
} from '@/types';

const props = defineProps<{
    open: boolean;
    row: ImportRowItem | null;
    destinationAccounts: ImportDestinationAccountOption[];
    categories: ImportCategoryOption[];
    referenceOptions: ImportReferenceOption[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();
const { t } = useI18n();
const page = usePage();
const isMobile = useMediaQuery('(max-width: 767px)');
const creatingTrackedItem = ref(false);
const trackedItemCatalog = ref<ImportReferenceOption[]>([]);
const moneyFormatLocale = computed(() =>
    String(page.props.auth.user?.format_locale ?? 'it-IT'),
);
const moneyCurrencyCode = computed(() =>
    String(page.props.auth.user?.base_currency_code ?? 'EUR'),
);

const transferValue = 'Giroconto';
const typeOptions = computed(() => [
    { value: 'Entrata', label: t('app.enums.transactionTypes.income') },
    { value: 'Spesa', label: t('app.enums.transactionTypes.expense') },
    { value: 'Bolletta', label: t('app.enums.transactionTypes.bill') },
    { value: 'Debito', label: t('app.enums.transactionTypes.debt') },
    { value: 'Risparmio', label: t('app.enums.transactionTypes.saving') },
    { value: transferValue, label: t('app.enums.transactionTypes.transfer') },
]);

const form = useForm({
    account_id: '',
    date: '',
    value_date: '',
    type: '',
    amount: '',
    detail: '',
    category_uuid: '',
    category: '',
    reference: '',
    tracked_item_uuid: '',
    merchant: '',
    external_reference: '',
    balance: '',
    currency: '',
    destination_account_id: '',
});

const isTransfer = computed(() => form.type === transferValue);
const sourceAccountId = computed(() =>
    form.account_id !== '' ? form.account_id : null,
);
const accountOptions = computed(() =>
    props.destinationAccounts.map((account) => ({
        value: String(account.id),
        label: account.label,
    })),
);
const destinationAccountOptions = computed(() =>
    props.destinationAccounts.map((account) => ({
        ...account,
        isSource: sourceAccountId.value === String(account.id),
    })),
);
const hasSelectableCategories = computed(() => props.categories.length > 0);
const selectedTypeKey = computed(() =>
    mapImportTypeToTransactionTypeKey(form.type),
);
const shouldShowCategoryField = computed(() => !isTransfer.value);
const categoryOptions = computed(() => {
    if (!shouldShowCategoryField.value) {
        return [];
    }

    return props.categories.filter((category) => {
        const categoryGroupType = category.group_type ?? null;

        if (categoryGroupType === null) {
            return true;
        }

        return categoryGroupType === selectedTypeKey.value;
    });
});
const categorySelectOptions = computed(() => {
    if (
        form.category_uuid !== '' &&
        !categoryOptions.value.some(
            (category) => category.value === form.category_uuid,
        )
    ) {
        return [
            {
                value: form.category_uuid,
                uuid: form.category_uuid,
                label: form.category || form.category_uuid,
                full_path: form.category || form.category_uuid,
                ancestor_uuids: [],
            },
            ...categoryOptions.value,
        ];
    }

    return categoryOptions.value;
});
const trackedItemSelectOptions = computed(() => {
    const filteredTrackedItems = [...trackedItemCatalog.value].sort(
        (first, second) =>
            Number(isTrackedItemCompatible(second)) -
                Number(isTrackedItemCompatible(first)) ||
            first.label.localeCompare(second.label, 'it'),
    );

    if (
        form.tracked_item_uuid !== '' &&
        !filteredTrackedItems.some(
            (trackedItem) => trackedItem.uuid === form.tracked_item_uuid,
        )
    ) {
        return [
            {
                value: form.tracked_item_uuid,
                uuid: form.tracked_item_uuid,
                label: form.tracked_item_uuid,
            },
            ...filteredTrackedItems,
        ];
    }

    return filteredTrackedItems;
});
const currentImportedCategoryMissing = computed(
    () => form.category !== '' && form.category_uuid === '',
);
const categoryPlaceholder = computed(() => {
    if (currentImportedCategoryMissing.value) {
        return t('imports.reviewDialog.placeholders.categoryInvalid');
    }

    return t('imports.reviewDialog.placeholders.category');
});

function syncFormFromRow(): void {
    if (!props.row) {
        return;
    }

    const suggestedCategoryUuid =
        props.row.suggested_category?.category_uuid ?? null;
    const matchedCategory =
        props.row.review_values.category_uuid !== null ||
        suggestedCategoryUuid !== null
            ? (props.categories.find(
                  (category) =>
                      category.uuid ===
                      (props.row?.review_values.category_uuid ??
                          suggestedCategoryUuid),
              ) ?? null)
            : (props.categories.find((category) => {
                  const reviewCategory = props.row?.review_values.category;

                  if (!reviewCategory) {
                      return false;
                  }

                  return (
                      category.label === reviewCategory ||
                      category.full_path === reviewCategory
                  );
              }) ?? null);

    form.defaults({
        account_id:
            props.row.review_values.account_id !== null
                ? String(props.row.review_values.account_id)
                : '',
        date: props.row.review_values.date ?? '',
        value_date: props.row.review_values.value_date ?? '',
        type: props.row.review_values.type ?? '',
        amount: props.row.review_values.amount ?? '',
        detail: props.row.review_values.detail ?? '',
        category_uuid: matchedCategory?.uuid ?? '',
        category:
            matchedCategory?.label ?? props.row.review_values.category ?? '',
        reference: props.row.review_values.reference ?? '',
        tracked_item_uuid: props.row.review_values.tracked_item_uuid ?? '',
        merchant: props.row.review_values.merchant ?? '',
        external_reference: props.row.review_values.external_reference ?? '',
        balance: props.row.review_values.balance ?? '',
        currency: props.row.review_values.currency ?? '',
        destination_account_id:
            props.row.review_values.destination_account_id !== null
                ? String(props.row.review_values.destination_account_id)
                : '',
    });

    form.reset();
    form.clearErrors();
}

watch(
    () => [props.row?.uuid, props.open],
    ([, open]) => {
        if (open) {
            syncFormFromRow();
        }
    },
    { immediate: true },
);

watch(
    () => props.referenceOptions,
    (options) => {
        trackedItemCatalog.value = [...options];
    },
    { immediate: true },
);

watch(
    () => form.type,
    (type) => {
        if (type !== transferValue) {
            form.destination_account_id = '';
            form.clearErrors('destination_account_id');
        }

        if (type === transferValue) {
            form.clearErrors('category', 'category_uuid', 'tracked_item_uuid');
        }
    },
);

watch(
    () => form.account_id,
    (accountId) => {
        if (
            form.destination_account_id !== '' &&
            form.destination_account_id === accountId
        ) {
            form.destination_account_id = '';
            form.clearErrors('destination_account_id');
        }
    },
);

watch(
    () => form.category_uuid,
    (categoryUuid) => {
        const selectedCategory =
            props.categories.find(
                (category) => category.uuid === categoryUuid,
            ) ?? null;

        form.category = selectedCategory?.label ?? '';
        form.clearErrors('category', 'category_uuid');
    },
);

watch(
    () => [form.type, form.category_uuid],
    () => {
        if (!shouldShowCategoryField.value) {
            return;
        }

        if (
            form.category_uuid !== '' &&
            !categoryOptions.value.some(
                (category) => category.uuid === form.category_uuid,
            )
        ) {
            form.category_uuid = '';
            form.category = '';
        }
    },
);

function closeDialog(): void {
    emit('update:open', false);
}

function submit(): void {
    if (!props.row) {
        return;
    }

    form.transform((data) =>
        data.type === transferValue
            ? data
            : {
                  ...data,
                  destination_account_id: null,
              },
    ).patch(props.row.review_update_url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('saved');
            closeDialog();
        },
    });
}

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function createTrackedItemFromContext(name: string): Promise<void> {
    if (form.type === '' || form.type === transferValue) {
        form.setError(
            'tracked_item_uuid',
            t('imports.reviewDialog.errors.invalidTypeForTrackedItem'),
        );

        return;
    }

    if (form.account_id === '' || form.category_uuid === '') {
        form.setError(
            'tracked_item_uuid',
            t('imports.reviewDialog.errors.trackedItemContextRequired'),
        );

        return;
    }

    creatingTrackedItem.value = true;

    try {
        const selectedAccount = props.destinationAccounts.find(
            (account) => String(account.id) === form.account_id,
        );

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
                account_uuid: selectedAccount?.uuid ?? '',
                category_uuid: form.category_uuid,
                type_key: mapImportTypeToTransactionTypeKey(form.type),
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const firstError = payload?.errors
                ? Object.values(payload.errors)[0]
                : null;

            form.setError(
                'tracked_item_uuid',
                Array.isArray(firstError)
                    ? String(firstError[0] ?? '')
                    : t('imports.reviewDialog.errors.createTrackedItemFailed'),
            );

            return;
        }

        const payload = await response.json();
        const option = payload.item;
        trackedItemCatalog.value = [...trackedItemCatalog.value, option].filter(
            (trackedItem, index, collection) =>
                collection.findIndex(
                    (candidate) => candidate.uuid === trackedItem.uuid,
                ) === index,
        );
        form.tracked_item_uuid = String(option.value ?? option.uuid ?? '');
        form.clearErrors('tracked_item_uuid');
    } catch (error) {
        form.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : t('imports.reviewDialog.errors.createTrackedItemFailed'),
        );
    } finally {
        creatingTrackedItem.value = false;
    }
}

function isTrackedItemCompatible(trackedItem: ImportReferenceOption): boolean {
    const categoryUuids = trackedItem.category_uuids ?? [];
    const groupKeys = trackedItem.group_keys ?? [];

    if (categoryUuids.length > 0 && form.category_uuid !== '') {
        return categoryUuids.includes(form.category_uuid);
    }

    if (groupKeys.length > 0 && selectedTypeKey.value !== '') {
        return groupKeys.includes(selectedTypeKey.value);
    }

    return true;
}

function mapImportTypeToTransactionTypeKey(type: string): string {
    switch (type) {
        case 'income':
        case 'Entrata':
        case 'Income':
            return 'income';
        case 'bill':
        case 'Bolletta':
        case 'Bill':
            return 'bill';
        case 'debt':
        case 'Debito':
        case 'Debt':
            return 'debt';
        case 'saving':
        case 'Risparmio':
        case 'Saving':
            return 'saving';
        case 'expense':
        case 'Spesa':
        case 'Expense':
        default:
            return 'expense';
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogScrollContent class="sm:max-w-3xl">
            <DialogHeader class="space-y-3">
                <DialogTitle>
                    {{ t('imports.reviewDialog.title') }}
                    <span v-if="row" class="text-slate-500 dark:text-slate-400">
                        #{{ row.row_index }}
                    </span>
                </DialogTitle>
                <DialogDescription class="leading-6">
                    {{ t('imports.reviewDialog.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2 md:grid-cols-2">
                <div class="space-y-2 md:col-span-2">
                    <Label for="review-account">{{
                        t('imports.reviewDialog.fields.account')
                    }}</Label>
                    <Select v-model="form.account_id">
                        <SelectTrigger id="review-account">
                            <SelectValue
                                :placeholder="
                                    t(
                                        'imports.reviewDialog.placeholders.account',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="account in accountOptions"
                                :key="account.value"
                                :value="account.value"
                            >
                                {{ account.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ t('imports.reviewDialog.accountHelper') }}
                    </p>
                    <p
                        v-if="form.errors.account_id"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.account_id }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="review-date">{{
                        t('imports.reviewDialog.fields.date')
                    }}</Label>
                    <Input
                        id="review-date"
                        v-model="form.date"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.date')
                        "
                    />
                    <p
                        v-if="form.errors.date"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.date }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="review-type">{{
                        t('imports.reviewDialog.fields.type')
                    }}</Label>
                    <Select v-model="form.type">
                        <SelectTrigger id="review-type">
                            <SelectValue
                                :placeholder="
                                    t('imports.reviewDialog.placeholders.type')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in typeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="form.errors.type"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.type }}
                    </p>
                </div>

                <div class="space-y-2">
                    <MobileAmountInput
                        v-if="isMobile"
                        id="review-amount"
                        v-model="form.amount"
                        :label="t('imports.reviewDialog.fields.amount')"
                        :mobile-title="t('imports.reviewDialog.fields.amount')"
                        :format-locale="moneyFormatLocale"
                        :currency-code="moneyCurrencyCode"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.amount')
                        "
                        :error="form.errors.amount"
                    />
                    <MoneyInput
                        v-else
                        id="review-amount"
                        v-model="form.amount"
                        :label="t('imports.reviewDialog.fields.amount')"
                        :format-locale="moneyFormatLocale"
                        :currency-code="moneyCurrencyCode"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.amount')
                        "
                        :error="form.errors.amount"
                    />
                </div>

                <div v-if="shouldShowCategoryField" class="space-y-2">
                    <Label for="review-category">{{
                        t('imports.reviewDialog.fields.category')
                    }}</Label>
                    <MobileSearchableSelect
                        v-if="hasSelectableCategories && isMobile"
                        v-model="form.category_uuid"
                        :options="categorySelectOptions"
                        :placeholder="categoryPlaceholder"
                        :search-placeholder="
                            t(
                                'imports.reviewDialog.placeholders.categorySearch',
                            )
                        "
                        clearable
                        hierarchical
                        :teleport="false"
                        :mobile-title="
                            t('imports.reviewDialog.fields.category')
                        "
                        trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                    />
                    <SearchableSelect
                        v-else-if="hasSelectableCategories"
                        v-model="form.category_uuid"
                        :options="categorySelectOptions"
                        :placeholder="categoryPlaceholder"
                        :search-placeholder="
                            t(
                                'imports.reviewDialog.placeholders.categorySearch',
                            )
                        "
                        clearable
                        hierarchical
                        :teleport="false"
                        trigger-class="h-10 rounded-md border-slate-200 dark:border-slate-800"
                        content-class="max-h-80 overflow-hidden"
                    />
                    <div
                        v-else
                        class="rounded-md border border-dashed border-slate-300 px-3 py-3 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                    >
                        {{ t('imports.reviewDialog.emptyCategories') }}
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ t('imports.reviewDialog.categoryHelper') }}
                    </p>
                    <p
                        v-if="currentImportedCategoryMissing"
                        class="text-xs text-amber-700 dark:text-amber-300"
                    >
                        {{ t('imports.reviewDialog.importedCategory') }}:
                        {{ row?.review_values.category }}
                    </p>
                    <p
                        v-if="form.errors.category"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.category }}
                    </p>
                </div>

                <div v-if="isTransfer" class="space-y-2 md:col-span-2">
                    <Label for="review-destination-account">{{
                        t('imports.reviewDialog.fields.destinationAccount')
                    }}</Label>
                    <Select v-model="form.destination_account_id">
                        <SelectTrigger id="review-destination-account">
                            <SelectValue
                                :placeholder="
                                    t(
                                        'imports.reviewDialog.placeholders.destinationAccount',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="account in destinationAccountOptions"
                                :key="account.uuid"
                                :value="String(account.id)"
                                :disabled="account.isSource"
                            >
                                {{ account.label }}
                                <span v-if="account.isSource">
                                    ·
                                    {{
                                        t(
                                            'imports.reviewDialog.destinationSource',
                                        )
                                    }}</span
                                >
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ t('imports.reviewDialog.destinationHelper') }}
                    </p>
                    <p
                        v-if="form.errors.destination_account_id"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.destination_account_id }}
                    </p>
                </div>

                <div class="space-y-2 md:col-span-2">
                    <Label for="review-detail">{{
                        t('imports.reviewDialog.fields.detail')
                    }}</Label>
                    <Input
                        id="review-detail"
                        v-model="form.detail"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.detail')
                        "
                    />
                    <p
                        v-if="form.errors.detail"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.detail }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="review-reference">{{
                        t('imports.reviewDialog.fields.reference')
                    }}</Label>
                    <MobileSearchableSelect
                        v-if="isMobile"
                        v-model="form.tracked_item_uuid"
                        :options="trackedItemSelectOptions"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.reference')
                        "
                        :search-placeholder="
                            t(
                                'imports.reviewDialog.placeholders.referenceSearch',
                            )
                        "
                        clearable
                        creatable
                        :creating="creatingTrackedItem"
                        hierarchical
                        :teleport="false"
                        :mobile-title="
                            t('imports.reviewDialog.fields.reference')
                        "
                        :create-label="
                            t(
                                'imports.reviewDialog.placeholders.referenceCreate',
                            )
                        "
                        trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        @create-option="createTrackedItemFromContext"
                    />
                    <SearchableSelect
                        v-else
                        v-model="form.tracked_item_uuid"
                        :options="trackedItemSelectOptions"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.reference')
                        "
                        :search-placeholder="
                            t(
                                'imports.reviewDialog.placeholders.referenceSearch',
                            )
                        "
                        clearable
                        creatable
                        :creating="creatingTrackedItem"
                        hierarchical
                        :teleport="false"
                        trigger-class="h-10 rounded-md border-slate-200 dark:border-slate-800"
                        content-class="max-h-80 overflow-hidden"
                        :create-label="
                            t(
                                'imports.reviewDialog.placeholders.referenceCreate',
                            )
                        "
                        @create-option="createTrackedItemFromContext"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ t('imports.reviewDialog.referenceHelper') }}
                    </p>
                    <p
                        v-if="form.errors.tracked_item_uuid"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.tracked_item_uuid }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="review-merchant">{{
                        t('imports.reviewDialog.fields.merchant')
                    }}</Label>
                    <Input
                        id="review-merchant"
                        v-model="form.merchant"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.merchant')
                        "
                    />
                    <p
                        v-if="form.errors.merchant"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.merchant }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="review-external-reference">{{
                        t('imports.reviewDialog.fields.externalReference')
                    }}</Label>
                    <Input
                        id="review-external-reference"
                        v-model="form.external_reference"
                        :placeholder="
                            t(
                                'imports.reviewDialog.placeholders.externalReference',
                            )
                        "
                    />
                    <p
                        v-if="form.errors.external_reference"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.external_reference }}
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    class="rounded-full"
                    @click="closeDialog"
                >
                    {{ t('imports.reviewDialog.close') }}
                </Button>
                <Button
                    class="rounded-full"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{
                        form.processing
                            ? t('imports.reviewDialog.saving')
                            : t('imports.reviewDialog.save')
                    }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
