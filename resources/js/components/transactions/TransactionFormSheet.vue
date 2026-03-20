<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
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
import type {
    MonthlyTransactionSheetData,
    MonthlyTransactionSheetTrackedItemOption,
    MonthlyTransactionSheetTransaction,
} from '@/types';

const transferTypeKey = 'transfer';

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
}>();

const form = useForm({
    transaction_day: '',
    type_key: 'expense',
    category_uuid: '',
    destination_account_uuid: '',
    account_uuid: '',
    tracked_item_uuid: '',
    amount: '',
    description: '',
    notes: '',
});
const creatingTrackedItem = ref(false);
const trackedItemCatalog = ref<MonthlyTransactionSheetTrackedItemOption[]>([]);

const isEditing = computed(
    () => props.transaction !== null && props.transaction !== undefined,
);

const title = computed(() =>
    isEditing.value ? 'Modifica registrazione' : 'Nuova registrazione',
);

const description = computed(() =>
    isEditing.value
        ? 'Aggiorna i campi della riga selezionata senza uscire dal foglio mensile.'
        : 'Inserisci una nuova riga operativa del mese corrente.',
);

const filteredCategories = computed(() =>
    props.sheet.editor.categories.filter((category) => {
        if (!form.type_key) {
            return true;
        }

        return category.type_key === form.type_key;
    }),
);

const isTransfer = computed(() => form.type_key === transferTypeKey);

const destinationAccounts = computed(() =>
    props.sheet.editor.accounts.filter((account) => account.value !== form.account_uuid),
);

const trackedItemOptions = computed(() =>
    filterTrackedItemOptions(
        trackedItemCatalog.value,
        form.type_key,
        form.category_uuid,
        form.tracked_item_uuid,
    ),
);

const monthDayRange = computed(() => {
    return {
        min: 1,
        max: new Date(props.year, props.month, 0).getDate(),
    };
});

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

    const selectedOption = options.find((option) => option.value === selectedValue) ?? null;
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

    const category = props.sheet.editor.categories.find(
        (option) => option.value === categoryUuid,
    );

    if (!category) {
        return [categoryUuid];
    }

    return [categoryUuid, ...category.ancestor_uuids];
}

async function createTrackedItemFromContext(name: string): Promise<void> {
    if (form.type_key === '' || form.type_key === transferTypeKey) {
        form.setError(
            'tracked_item_uuid',
            'Seleziona prima un tipo valido per associare il nuovo elemento.',
        );

        return;
    }

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
            body: JSON.stringify({
                name,
                parent_uuid: null,
                type: null,
                is_active: true,
                settings: {
                    transaction_group_keys: [form.type_key],
                    transaction_category_uuids: form.category_uuid !== '' ? [form.category_uuid] : [],
                },
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const firstError = payload?.errors
                ? Object.values(payload.errors)[0]
                : null;

            form.setError(
                'tracked_item_uuid',
                Array.isArray(firstError) ? firstError[0] : 'Impossibile creare l’elemento da tracciare.',
            );

            return;
        }

        const payload = await response.json();
        const option = payload.item as MonthlyTransactionSheetTrackedItemOption;

        trackedItemCatalog.value = [...trackedItemCatalog.value, option].sort(
            (first, second) => first.label.localeCompare(second.label, 'it'),
        );
        form.tracked_item_uuid = option.value;
        form.clearErrors('tracked_item_uuid');
    } catch (error) {
        form.setError(
            'tracked_item_uuid',
            error instanceof Error ? error.message : 'Impossibile creare l’elemento da tracciare.',
        );
    } finally {
        creatingTrackedItem.value = false;
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
    const shouldTreatAsDecimal = hasAnySeparator && (
        hasTrailingSeparator ||
        decimalsLength <= 2 ||
        (separatorsCount > 1 && decimalsLength <= 2)
    );

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

    const separators = [...sanitized.matchAll(/[.,]/g)].map((match) => match.index ?? 0);

    if (separators.length === 0) {
        const parsedInteger = Number.parseFloat(sanitized);

        return Number.isFinite(parsedInteger) ? parsedInteger : null;
    }

    const lastSeparatorIndex = separators[separators.length - 1] ?? -1;
    const digitsAfterSeparator = sanitized.length - lastSeparatorIndex - 1;
    const singleSeparator = separators.length === 1;

    if (singleSeparator && (digitsAfterSeparator === 3 || digitsAfterSeparator === 0)) {
        const thousandsValue = Number.parseFloat(sanitized.replace(/[.,]/g, ''));

        return Number.isFinite(thousandsValue) ? thousandsValue : null;
    }

    const integerPart = sanitized.slice(0, lastSeparatorIndex).replace(/[.,]/g, '');
    const decimalPart = sanitized.slice(lastSeparatorIndex + 1).replace(/[.,]/g, '');
    const normalized = decimalPart === '' ? integerPart : `${integerPart}.${decimalPart}`;
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

function normalizeAmountField(): number | null {
    const parsedAmount = parseLocalizedAmount(form.amount);

    if (parsedAmount === null || parsedAmount <= 0) {
        form.setError('amount', "L'importo deve essere maggiore di zero.");

        return null;
    }

    form.amount = formatAmountForDisplay(parsedAmount);
    form.clearErrors('amount');

    return parsedAmount;
}

function handleAmountInput(value: string | number): void {
    form.amount = formatAmountDraftProgressive(String(value ?? ''));
}

watch(
    () => [props.open, props.transaction] as const,
    ([open, transaction]) => {
        if (!open) {
            return;
        }

        form.clearErrors();

        if (transaction) {
            form.defaults({
                transaction_day: transaction.date ? String(new Date(transaction.date).getDate()) : '1',
                type_key: transaction.type_key ?? 'expense',
                category_uuid: transaction.is_transfer
                    ? ''
                    : (transaction.category_uuid ? String(transaction.category_uuid) : ''),
                destination_account_uuid: transaction.related_account_uuid ? String(transaction.related_account_uuid) : '',
                account_uuid: transaction.account_uuid ? String(transaction.account_uuid) : '',
                tracked_item_uuid: transaction.is_transfer
                    ? ''
                    : (transaction.tracked_item_uuid ? String(transaction.tracked_item_uuid) : ''),
                amount: formatAmountForDisplay(transaction.amount_value_raw ?? null),
                description: transaction.description ?? '',
                notes: transaction.notes ?? '',
            });
            form.reset();

            return;
        }

        form.defaults({
            transaction_day: '1',
            type_key: props.sheet.editor.group_options[0]?.value ?? 'expense',
            category_uuid: '',
            destination_account_uuid: '',
            account_uuid: props.sheet.editor.accounts[0]?.value ?? '',
            tracked_item_uuid: '',
            amount: '',
            description: '',
            notes: '',
        });
        form.reset();
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
            form.tracked_item_uuid = '';
            form.clearErrors('category_uuid', 'tracked_item_uuid');
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
    () => [form.type_key, form.category_uuid] as const,
    ([typeKey, categoryId], [, previousCategoryId]) => {
        if (form.tracked_item_uuid === '') {
            return;
        }

        if (
            previousCategoryId === undefined
            || trackedItemMatchesContext(
                trackedItemCatalog.value.find(
                    (option) => option.value === form.tracked_item_uuid,
                ) ?? { value: '', label: '' },
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
    },
);

function closeSheet(): void {
    emit('update:open', false);
}

function submit(): void {
    if (
        form.transaction_day === '' ||
        Number(form.transaction_day) < monthDayRange.value.min ||
        Number(form.transaction_day) > monthDayRange.value.max
    ) {
        form.setError(
            'transaction_day',
            `Il giorno deve restare tra ${monthDayRange.value.min} e ${monthDayRange.value.max}.`,
        );

        return;
    }

    if (isTransfer.value) {
        if (form.destination_account_uuid === '') {
            form.setError('destination_account_uuid', 'Seleziona il conto di destinazione.');

            return;
        }

        if (form.destination_account_uuid === form.account_uuid) {
            form.setError(
                'destination_account_uuid',
                'Il conto di destinazione deve essere diverso dal conto sorgente.',
            );

            return;
        }
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
                    emit('saved', 'Transazione aggiornata correttamente.');
                    closeSheet();
                },
            },
        );

        return;
    }

    form.transform(() => payload).post(`/transactions/${props.year}/${props.month}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', 'Transazione creata correttamente.');
            closeSheet();
        },
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full border-l p-0 sm:max-w-2xl">
            <div class="flex h-full flex-col">
                <SheetHeader class="border-b border-slate-200/80 px-6 py-6 dark:border-slate-800">
                    <SheetTitle>{{ title }}</SheetTitle>
                    <SheetDescription>
                        {{ description }}
                    </SheetDescription>
                </SheetHeader>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="transaction_day">Giorno</Label>
                                <Input
                                    id="transaction_day"
                                    v-model="form.transaction_day"
                                    type="number"
                                    inputmode="numeric"
                                    placeholder="GG"
                                    :min="monthDayRange.min"
                                    :max="monthDayRange.max"
                                    class="h-11 rounded-2xl border-slate-200 text-center dark:border-slate-800"
                                />
                                <InputError :message="form.errors.transaction_day" />
                            </div>

                            <div class="grid gap-2">
                                <Label>Tipo</Label>
                                <Select
                                    :model-value="form.type_key"
                                    @update:model-value="form.type_key = String($event)"
                                >
                                    <SelectTrigger class="h-11 rounded-2xl border-slate-200 dark:border-slate-800">
                                        <SelectValue placeholder="Seleziona un tipo" />
                                    </SelectTrigger>
                                    <SelectContent class="z-[170]">
                                        <SelectItem
                                            v-for="option in sheet.editor.group_options"
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
                                <Label>{{ isTransfer ? 'Conto destinazione' : 'Categoria' }}</Label>
                                <SearchableSelect
                                    v-if="!isTransfer"
                                    v-model="form.category_uuid"
                                    :options="filteredCategories"
                                    placeholder="Seleziona categoria"
                                    search-placeholder="Cerca categoria"
                                    :disabled="form.type_key === ''"
                                    clearable
                                    trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                />
                                <SearchableSelect
                                    v-else
                                    v-model="form.destination_account_uuid"
                                    :options="destinationAccounts"
                                    placeholder="Seleziona conto destinazione"
                                    search-placeholder="Cerca conto destinazione"
                                    clearable
                                    trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                />
                                <InputError :message="isTransfer ? form.errors.destination_account_uuid : form.errors.category_uuid" />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{ isTransfer ? 'Conto sorgente' : 'Conto' }}</Label>
                                <SearchableSelect
                                    v-model="form.account_uuid"
                                    :options="sheet.editor.accounts"
                                    :placeholder="isTransfer ? 'Seleziona conto sorgente' : 'Seleziona conto'"
                                    :search-placeholder="isTransfer ? 'Cerca conto sorgente' : 'Cerca conto'"
                                    clearable
                                    trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                />
                                <InputError :message="form.errors.account_uuid" />
                            </div>
                        </div>

                        <div v-if="!isTransfer" class="grid gap-2">
                            <Label>Elementi da tracciare</Label>
                            <SearchableSelect
                                v-model="form.tracked_item_uuid"
                                :options="[{ value: '', label: 'Nessuno' }, ...trackedItemOptions]"
                                placeholder="Opzionale"
                                search-placeholder="Cerca elemento da tracciare"
                                :disabled="form.type_key === ''"
                                clearable
                                creatable
                                :creating="creatingTrackedItem"
                                create-label="Crea elemento"
                                trigger-class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                @create-option="createTrackedItemFromContext"
                            />
                            <InputError :message="form.errors.tracked_item_uuid" />
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-sky-200/80 bg-sky-50/70 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-200"
                        >
                            Il giroconto crea un’uscita dal conto sorgente e un’entrata sul conto destinazione nello stesso giorno.
                        </div>

                        <div class="grid gap-5">
                            <div class="grid gap-2">
                                <Label for="amount">Importo</Label>
                                <Input
                                    id="amount"
                                    :model-value="form.amount"
                                    inputmode="decimal"
                                    placeholder="0,00"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    @update:model-value="handleAmountInput"
                                    @blur="normalizeAmountField"
                                />
                                <InputError :message="form.errors.amount" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Dettaglio</Label>
                            <Input
                                id="description"
                                v-model="form.description"
                                placeholder="Es. Spesa supermercato, bonifico cliente, bolletta"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes">Note</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="min-h-28 rounded-2xl border border-slate-200 bg-transparent px-3 py-3 text-sm shadow-xs outline-none transition-colors placeholder:text-slate-400 focus:border-slate-400 dark:border-slate-800 dark:placeholder:text-slate-500"
                                placeholder="Annotazioni operative opzionali"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </form>
                </div>

                <div class="border-t border-slate-200/80 px-6 py-4 dark:border-slate-800">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-2xl"
                            @click="closeSheet"
                        >
                            Annulla
                        </Button>
                        <Button
                            type="button"
                            class="rounded-2xl"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            {{ isEditing ? 'Salva modifiche' : 'Crea registrazione' }}
                        </Button>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
