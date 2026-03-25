<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
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
    ImportRowItem,
} from '@/types';

const props = defineProps<{
    open: boolean;
    row: ImportRowItem | null;
    destinationAccounts: ImportDestinationAccountOption[];
    categories: ImportCategoryOption[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();
const { t } = useI18n();
const page = usePage();
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
    date: '',
    type: '',
    amount: '',
    detail: '',
    category: '',
    reference: '',
    merchant: '',
    external_reference: '',
    balance: '',
    destination_account_id: '',
});

const isTransfer = computed(() => form.type === transferValue);
const sourceAccountId = computed(() =>
    props.row?.review_values.source_account_id !== undefined &&
    props.row?.review_values.source_account_id !== null
        ? String(props.row.review_values.source_account_id)
        : null,
);
const sourceAccountUuid = computed(
    () => props.row?.review_values.source_account_uuid ?? null,
);
const destinationAccountOptions = computed(() =>
    props.destinationAccounts.map((account) => ({
        ...account,
        isSource:
            sourceAccountId.value === String(account.id) ||
            sourceAccountUuid.value === account.uuid,
    })),
);
const hasSelectableCategories = computed(() => props.categories.length > 0);
const categoryOptions = computed(() =>
    props.categories.map((category) => ({
        value: category.value,
        label: category.label,
    })),
);
const currentImportedCategoryMissing = computed(
    () =>
        form.category !== '' &&
        !props.categories.some((category) => category.value === form.category),
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

    form.defaults({
        date: props.row.review_values.date ?? '',
        type: props.row.review_values.type ?? '',
        amount: props.row.review_values.amount ?? '',
        detail: props.row.review_values.detail ?? '',
        category: props.row.review_values.category ?? '',
        reference: props.row.review_values.reference ?? '',
        merchant: props.row.review_values.merchant ?? '',
        external_reference: props.row.review_values.external_reference ?? '',
        balance: props.row.review_values.balance ?? '',
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
    () => form.type,
    (type) => {
        if (type !== transferValue) {
            form.destination_account_id = '';
            form.clearErrors('destination_account_id');
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
                    <MoneyInput
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

                <div class="space-y-2">
                    <Label for="review-category">{{
                        t('imports.reviewDialog.fields.category')
                    }}</Label>
                    <SearchableSelect
                        v-if="hasSelectableCategories"
                        v-model="form.category"
                        :options="categoryOptions"
                        :placeholder="categoryPlaceholder"
                        :search-placeholder="
                            t(
                                'imports.reviewDialog.placeholders.categorySearch',
                            )
                        "
                        clearable
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
                    <Input
                        id="review-reference"
                        v-model="form.reference"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.reference')
                        "
                    />
                    <p
                        v-if="form.errors.reference"
                        class="text-sm text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.reference }}
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

                <div class="space-y-2">
                    <MoneyInput
                        id="review-balance"
                        v-model="form.balance"
                        :label="t('imports.reviewDialog.fields.balance')"
                        :format-locale="moneyFormatLocale"
                        :currency-code="moneyCurrencyCode"
                        :placeholder="
                            t('imports.reviewDialog.placeholders.balance')
                        "
                        :error="form.errors.balance"
                    />
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
