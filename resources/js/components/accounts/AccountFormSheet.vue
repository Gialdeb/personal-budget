<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BankSearchSelect from '@/components/banks/BankSearchSelect.vue';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { resolveCreditCardCycle } from '@/lib/credit-card-cycle';
import { store, update } from '@/routes/accounts';
import type {
    AccountBankOption,
    AccountItem,
    AccountTypeOption,
    CurrencyOption,
    LinkedPaymentAccountOption,
} from '@/types';

const NONE_OPTION = '__none__';
const { t } = useI18n();
const page = usePage();
const userBaseCurrencyCode = computed(() =>
    String(page.props.auth.user?.base_currency_code ?? 'EUR'),
);

const props = defineProps<{
    open: boolean;
    account?: AccountItem | null;
    banks: AccountBankOption[];
    openingBalanceDateOptions: {
        available_years: number[];
        min: string | null;
        max: string | null;
        today: string;
    };
    accountTypes: AccountTypeOption[];
    currencies: CurrencyOption[];
    linkedPaymentAccountOptions: LinkedPaymentAccountOption[];
    defaultAccountUuid?: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
}>();

const form = useForm({
    name: '',
    user_bank_uuid: NONE_OPTION,
    account_type_uuid: '',
    currency: userBaseCurrencyCode.value,
    iban: '',
    account_number_masked: '',
    opening_balance: '',
    opening_balance_direction: 'positive',
    opening_balance_date: '',
    current_balance: '',
    is_active: true,
    is_reported: true,
    is_default: false,
    notes: '',
    settings: {
        allow_negative_balance: false,
        credit_limit: '',
        linked_payment_account_uuid: NONE_OPTION,
        statement_closing_day: '15',
        payment_day: '16',
        auto_pay: false,
    },
});

let isInitializingForm = false;

const isEditing = computed(
    () => props.account !== null && props.account !== undefined,
);

const selectedAccountType = computed(
    () =>
        props.accountTypes.find(
            (option) => option.uuid === form.account_type_uuid,
        ) ?? null,
);

const isCreditCard = computed(
    () => selectedAccountType.value?.code === 'credit_card',
);

const isCashAccount = computed(
    () => selectedAccountType.value?.code === 'cash_account',
);

const canConfigureNegativeBalance = computed(
    () => selectedAccountType.value?.code !== 'credit_card',
);
const isProtectedCashAccount = computed(
    () => props.account?.is_protected_cash_account === true,
);
const openingBalanceDirectionLocked = computed(
    () => isProtectedCashAccount.value,
);

const isCurrentBalanceReadonly = computed(() => true);
const moneyFormatLocale = computed(() =>
    String(page.props.auth.user?.format_locale ?? 'it-IT'),
);
const moneyCurrencyCode = computed(
    () => form.currency || userBaseCurrencyCode.value,
);
const isCurrencyLocked = computed(
    () => isEditing.value && props.account?.can_update_currency === false,
);
const currencyLockMessage = computed(
    () => props.account?.currency_lock_message ?? null,
);

const isNegativeBalanceLocked = computed(
    () => selectedAccountType.value?.code === 'cash_account',
);
const isOpeningBalanceDateRequired = computed(() => {
    if (form.opening_balance === '') {
        return false;
    }

    return Number(form.opening_balance) > 0;
});
const allowedOpeningBalanceYears = computed(
    () => props.openingBalanceDateOptions.available_years ?? [],
);
const openingBalanceDateMin = computed(
    () => props.openingBalanceDateOptions.min ?? undefined,
);
const openingBalanceDateMax = computed(
    () =>
        props.openingBalanceDateOptions.max ??
        props.openingBalanceDateOptions.today,
);
const openingBalanceDateConstraintMessage = computed(() => {
    if (form.opening_balance_date === '') {
        return null;
    }

    if (!isAllowedOpeningBalanceDate(form.opening_balance_date)) {
        return t('accounts.form.fields.openingBalanceDateInvalid');
    }

    return null;
});
const cashAccountTypeUuid = computed(
    () =>
        props.accountTypes.find((option) => option.code === 'cash_account')
            ?.uuid ?? null,
);

const availableLinkedPaymentAccounts = computed(() =>
    props.linkedPaymentAccountOptions.filter((option) => {
        if (props.account && option.uuid === props.account.uuid) {
            return false;
        }

        const selectedLinkedPaymentAccountUuid =
            form.settings.linked_payment_account_uuid !== NONE_OPTION
                ? form.settings.linked_payment_account_uuid
                : null;

        const isVisibleByState =
            option.is_active ||
            option.uuid === selectedLinkedPaymentAccountUuid;

        if (!isVisibleByState) {
            return false;
        }

        if (!isCreditCard.value) {
            return true;
        }

        if (option.account_type_code === 'cash_account') {
            return false;
        }

        if (form.user_bank_uuid === NONE_OPTION) {
            return false;
        }

        return (
            option.user_bank_uuid === form.user_bank_uuid ||
            option.uuid === selectedLinkedPaymentAccountUuid
        );
    }),
);

const bankSearchOptions = computed(() =>
    props.banks.map((option) => ({
        value: option.uuid,
        name: option.name,
        display_name: option.display_name,
        slug: option.slug,
        country_code: option.country_code,
        logo_url: option.logo_url,
        subtitle:
            option.catalog_display_name ??
            option.catalog_name ??
            option.source_label,
    })),
);
const isBankSelectionLocked = computed(
    () =>
        isEditing.value &&
        !isCashAccount.value &&
        form.user_bank_uuid !== NONE_OPTION,
);
const creditCardLinkedPaymentAccountDisabled = computed(
    () =>
        !isCreditCard.value ||
        form.user_bank_uuid === NONE_OPTION ||
        availableLinkedPaymentAccounts.value.length === 0,
);
const creditCardLinkedPaymentAccountHelper = computed(() => {
    if (!isCreditCard.value) {
        return null;
    }

    if (form.user_bank_uuid === NONE_OPTION) {
        return t(
            'accounts.form.creditCard.linkedPaymentAccountSelectBankFirst',
        );
    }

    if (availableLinkedPaymentAccounts.value.length === 0) {
        return t('accounts.form.creditCard.linkedPaymentAccountEmpty');
    }

    return null;
});

const sheetTitle = computed(() =>
    isEditing.value
        ? t('accounts.form.titleEdit')
        : t('accounts.form.titleCreate'),
);

const sheetDescription = computed(() =>
    isEditing.value
        ? t('accounts.form.descriptionEdit')
        : t('accounts.form.descriptionCreate'),
);
const creditCardCycle = computed(() => {
    if (!isCreditCard.value) {
        return null;
    }

    const closingDay = Number.parseInt(
        form.settings.statement_closing_day || '15',
        10,
    );
    const paymentDay = Number.parseInt(form.settings.payment_day || '16', 10);

    return resolveCreditCardCycle(new Date(), closingDay, paymentDay);
});

const creditCardClosingRangePreview = computed(() => {
    if (creditCardCycle.value === null) {
        return null;
    }

    const locale = String(page.props.auth.user?.format_locale ?? 'it-IT');
    const formatter = new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return t('accounts.form.creditCard.closingRangePreview', {
        start: formatter.format(creditCardCycle.value.current_period_start),
        end: formatter.format(creditCardCycle.value.current_period_end),
    });
});

const creditCardNextBillingPreview = computed(() => {
    if (creditCardCycle.value === null) {
        return null;
    }

    const locale = String(page.props.auth.user?.format_locale ?? 'it-IT');
    const formatter = new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return t('accounts.form.creditCard.nextBillingPreview', {
        date: formatter.format(creditCardCycle.value.next_payment_date),
    });
});

watch(
    () => [props.open, props.account] as const,
    ([open, account]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        isInitializingForm = true;

        if (account) {
            form.defaults({
                name: account.name,
                user_bank_uuid: account.user_bank_uuid
                    ? account.user_bank_uuid
                    : NONE_OPTION,
                account_type_uuid: account.account_type_uuid,
                currency: account.currency,
                iban: account.iban ?? '',
                account_number_masked: account.account_number_masked ?? '',
                opening_balance:
                    account.opening_balance !== null
                        ? String(Math.abs(account.opening_balance))
                        : '',
                opening_balance_direction: account.is_protected_cash_account
                    ? 'positive'
                    : account.opening_balance_direction,
                opening_balance_date: account.opening_balance_date ?? '',
                current_balance:
                    account.current_balance !== null
                        ? String(account.current_balance)
                        : '',
                is_active: account.is_active,
                is_reported: account.is_reported,
                is_default: account.is_default,
                notes: account.notes ?? '',
                settings: {
                    allow_negative_balance: account.allow_negative_balance,
                    credit_limit:
                        account.credit_card_settings?.credit_limit !== null
                            ? String(account.credit_card_settings?.credit_limit)
                            : '',
                    linked_payment_account_uuid: account.credit_card_settings
                        ?.linked_payment_account_uuid
                        ? account.credit_card_settings
                              .linked_payment_account_uuid
                        : NONE_OPTION,
                    statement_closing_day:
                        account.credit_card_settings?.statement_closing_day !==
                        null
                            ? String(
                                  account.credit_card_settings
                                      ?.statement_closing_day,
                              )
                            : '15',
                    payment_day:
                        account.credit_card_settings?.payment_day !== null
                            ? String(account.credit_card_settings?.payment_day)
                            : '16',
                    auto_pay: account.credit_card_settings?.auto_pay ?? false,
                },
            });
            form.reset();
            isInitializingForm = false;

            return;
        }

        form.defaults({
            name: '',
            user_bank_uuid: NONE_OPTION,
            account_type_uuid: '',
            currency: userBaseCurrencyCode.value,
            iban: '',
            account_number_masked: '',
            opening_balance: '',
            opening_balance_direction: 'positive',
            opening_balance_date: '',
            current_balance: '',
            is_active: true,
            is_reported: true,
            is_default: false,
            notes: '',
            settings: {
                allow_negative_balance:
                    selectedAccountType.value?.default_allow_negative_balance ??
                    false,
                credit_limit: '',
                linked_payment_account_uuid: NONE_OPTION,
                statement_closing_day: '15',
                payment_day: '16',
                auto_pay: false,
            },
        });
        form.reset();
        isInitializingForm = false;
    },
    { immediate: true },
);

watch(selectedAccountType, (value) => {
    if (!value || isInitializingForm) {
        return;
    }

    if (value.code === 'credit_card') {
        form.settings.allow_negative_balance = true;
        form.opening_balance = '';
        form.opening_balance_direction = 'positive';
        form.opening_balance_date = '';
        form.settings.statement_closing_day =
            form.settings.statement_closing_day || '15';
        form.settings.payment_day = form.settings.payment_day || '16';

        return;
    }

    form.settings.allow_negative_balance =
        value.code === 'cash_account'
            ? false
            : value.default_allow_negative_balance;
});

watch(isCreditCard, (value) => {
    if (value) {
        return;
    }

    form.settings.allow_negative_balance =
        selectedAccountType.value?.default_allow_negative_balance ?? false;
    form.settings.credit_limit = '';
    form.settings.linked_payment_account_uuid = NONE_OPTION;
    form.settings.statement_closing_day = '15';
    form.settings.payment_day = '16';
    form.settings.auto_pay = false;
});

watch(isCashAccount, (value) => {
    if (!value) {
        return;
    }

    form.user_bank_uuid = NONE_OPTION;
    form.iban = '';
    form.account_number_masked = '';
    form.settings.allow_negative_balance = false;
});

watch(
    () =>
        [
            form.user_bank_uuid,
            isCreditCard.value,
            availableLinkedPaymentAccounts.value,
        ] as const,
    () => {
        if (!isCreditCard.value) {
            return;
        }

        if (form.user_bank_uuid === NONE_OPTION) {
            form.settings.linked_payment_account_uuid = NONE_OPTION;

            return;
        }

        const selectedOption = availableLinkedPaymentAccounts.value.find(
            (option) => {
                return (
                    option.uuid === form.settings.linked_payment_account_uuid
                );
            },
        );

        if (selectedOption) {
            return;
        }

        form.settings.linked_payment_account_uuid =
            availableLinkedPaymentAccounts.value[0]?.uuid ?? NONE_OPTION;
    },
    { immediate: true },
);

watch(userBaseCurrencyCode, (value) => {
    if (isEditing.value || form.currency !== '') {
        return;
    }

    form.currency = value;
});

watch(
    () => form.name,
    (value) => {
        if (isInitializingForm) {
            return;
        }

        if (value.trim().toLowerCase() !== 'cassa contanti') {
            return;
        }

        if (cashAccountTypeUuid.value !== null) {
            form.account_type_uuid = cashAccountTypeUuid.value;
            form.user_bank_uuid = NONE_OPTION;
        }
    },
);

watch(
    () => form.opening_balance_date,
    (value, previousValue) => {
        if (value === '' || isAllowedOpeningBalanceDate(value)) {
            return;
        }

        form.opening_balance_date =
            previousValue && isAllowedOpeningBalanceDate(previousValue)
                ? previousValue
                : '';
    },
);

function closeSheet(): void {
    emit('update:open', false);
}

function setActiveState(checked: boolean | 'indeterminate'): void {
    if (isProtectedCashAccount.value) {
        form.is_active = true;

        return;
    }

    form.is_active = checked === true;

    if (!form.is_active) {
        form.is_default = false;
    }
}

function setReportedState(checked: boolean | 'indeterminate'): void {
    form.is_reported = checked === true;
}

function setDefaultState(checked: boolean | 'indeterminate'): void {
    form.is_default = checked === true;

    if (form.is_default) {
        form.is_active = true;
    }
}

function setAutoPayState(checked: boolean | 'indeterminate'): void {
    form.settings.auto_pay = checked === true;
}

function setAllowNegativeBalanceState(
    checked: boolean | 'indeterminate',
): void {
    if (isNegativeBalanceLocked.value) {
        form.settings.allow_negative_balance = false;

        return;
    }

    form.settings.allow_negative_balance = checked === true;
}

function submit(): void {
    const basePayload = {
        ...form.data(),
        user_bank_uuid:
            isCashAccount.value || form.user_bank_uuid === NONE_OPTION
                ? null
                : form.user_bank_uuid,
        account_type_uuid:
            isProtectedCashAccount.value && props.account
                ? props.account.account_type_uuid
                : form.account_type_uuid,
        currency: form.currency,
        opening_balance: isCreditCard.value
            ? (props.account?.opening_balance ?? null)
            : form.opening_balance !== ''
              ? Number(form.opening_balance)
              : null,
        opening_balance_direction:
            isProtectedCashAccount.value || isCreditCard.value
                ? 'positive'
                : form.opening_balance_direction,
        opening_balance_date: isCreditCard.value
            ? (props.account?.opening_balance_date ?? null)
            : form.opening_balance_date !== ''
              ? form.opening_balance_date
              : null,
        is_active: isProtectedCashAccount.value ? true : form.is_active,
        is_reported: form.is_reported,
        is_default:
            form.is_default &&
            (isProtectedCashAccount.value ? true : form.is_active),
        settings: {
            allow_negative_balance: form.settings.allow_negative_balance,
            credit_limit:
                form.settings.credit_limit !== ''
                    ? Number(form.settings.credit_limit)
                    : null,
            linked_payment_account_uuid:
                form.settings.linked_payment_account_uuid !== NONE_OPTION
                    ? form.settings.linked_payment_account_uuid
                    : null,
            statement_closing_day:
                form.settings.statement_closing_day !== ''
                    ? Number(form.settings.statement_closing_day)
                    : null,
            payment_day:
                form.settings.payment_day !== ''
                    ? Number(form.settings.payment_day)
                    : null,
            auto_pay: form.settings.auto_pay,
        },
    };

    if (isEditing.value && props.account) {
        form.transform(() => basePayload).patch(
            update.url(props.account.uuid),
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('saved', t('accounts.form.feedback.updated'));
                    closeSheet();
                },
            },
        );

        return;
    }

    form.transform(() => ({
        ...basePayload,
        current_balance:
            form.current_balance !== '' ? Number(form.current_balance) : null,
    })).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', t('accounts.form.feedback.created'));
            closeSheet();
        },
    });
}

function isAllowedOpeningBalanceDate(value: string): boolean {
    if (value === '') {
        return true;
    }

    if (value > props.openingBalanceDateOptions.today) {
        return false;
    }

    const year = Number.parseInt(value.slice(0, 4), 10);

    if (Number.isNaN(year)) {
        return false;
    }

    return allowedOpeningBalanceYears.value.includes(year);
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full border-l p-0 sm:max-w-2xl">
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
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="name">{{
                                    t('accounts.form.fields.accountName')
                                }}</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    :placeholder="
                                        t(
                                            'accounts.form.fields.accountNamePlaceholder',
                                        )
                                    "
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t('accounts.form.fields.accountType')
                                }}</Label>
                                <Select
                                    :disabled="isProtectedCashAccount"
                                    :model-value="
                                        String(form.account_type_uuid)
                                    "
                                    @update:model-value="
                                        form.account_type_uuid = String($event)
                                    "
                                >
                                    <SelectTrigger
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'accounts.form.fields.accountTypePlaceholder',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in accountTypes"
                                            :key="option.uuid"
                                            :value="option.uuid"
                                        >
                                            {{ option.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.account_type_uuid"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t('accounts.form.fields.balanceNature')
                                }}</Label>
                                <div
                                    class="flex h-11 items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    {{
                                        selectedAccountType?.balance_nature_label ??
                                        t(
                                            'accounts.form.fields.selectAccountTypeFirst',
                                        )
                                    }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t('accounts.form.fields.bank')
                                }}</Label>
                                <BankSearchSelect
                                    :model-value="String(form.user_bank_uuid)"
                                    @update:model-value="
                                        form.user_bank_uuid = String($event)
                                    "
                                    :options="bankSearchOptions"
                                    :disabled="
                                        isCashAccount || isBankSelectionLocked
                                    "
                                    :include-empty-option="true"
                                    :empty-option-value="NONE_OPTION"
                                    :empty-option-label="
                                        t('accounts.form.fields.noBank')
                                    "
                                    :placeholder="
                                        t('accounts.form.fields.noBank')
                                    "
                                    search-placeholder="Cerca banca, slug o paese"
                                />
                                <InputError
                                    :message="form.errors.user_bank_uuid"
                                />
                                <p
                                    v-if="isBankSelectionLocked"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    La banca selezionata è in sola lettura.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="currency">{{
                                    t('accounts.form.fields.currency')
                                }}</Label>
                                <Select
                                    :model-value="form.currency"
                                    :disabled="isCurrencyLocked"
                                    @update:model-value="
                                        form.currency = String($event)
                                    "
                                >
                                    <SelectTrigger
                                        id="currency"
                                        class="h-11 rounded-2xl border-slate-200 uppercase dark:border-slate-800"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'accounts.form.fields.currencyPlaceholder',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="currency in props.currencies"
                                            :key="currency.code"
                                            :value="currency.code"
                                        >
                                            {{ currency.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'accounts.form.fields.currencyDerivedHelper',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="currencyLockMessage"
                                    class="text-xs text-amber-700 dark:text-amber-300"
                                >
                                    {{ currencyLockMessage }}
                                </p>
                                <InputError :message="form.errors.currency" />
                            </div>

                            <div
                                v-if="!isCashAccount && !isCreditCard"
                                class="grid gap-2"
                            >
                                <Label for="iban">{{
                                    t('accounts.form.fields.iban')
                                }}</Label>
                                <Input
                                    id="iban"
                                    v-model="form.iban"
                                    class="h-11 rounded-2xl border-slate-200 uppercase dark:border-slate-800"
                                    placeholder="IT60X0542811101000000123456"
                                />
                                <InputError :message="form.errors.iban" />
                            </div>

                            <div
                                v-if="!isCashAccount && !isCreditCard"
                                class="grid gap-2"
                            >
                                <Label for="account_number_masked">{{
                                    t('accounts.form.fields.maskedNumber')
                                }}</Label>
                                <Input
                                    id="account_number_masked"
                                    v-model="form.account_number_masked"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="**** 1234"
                                />
                                <InputError
                                    :message="form.errors.account_number_masked"
                                />
                            </div>

                            <div v-if="!isCreditCard" class="grid gap-2">
                                <MobileAmountInput
                                    id="opening_balance"
                                    v-model="form.opening_balance"
                                    :label="
                                        t('accounts.form.fields.openingBalance')
                                    "
                                    :format-locale="moneyFormatLocale"
                                    :currency-code="moneyCurrencyCode"
                                    class="border-slate-200 dark:border-slate-800"
                                    placeholder="0"
                                    :error="form.errors.opening_balance"
                                />
                            </div>

                            <div v-if="!isCreditCard" class="grid gap-2">
                                <Label>{{
                                    t(
                                        'accounts.form.fields.openingBalanceDirection',
                                    )
                                }}</Label>
                                <Select
                                    :disabled="openingBalanceDirectionLocked"
                                    :model-value="
                                        form.opening_balance_direction
                                    "
                                    @update:model-value="
                                        form.opening_balance_direction = String(
                                            $event,
                                        ) as 'positive' | 'negative'
                                    "
                                >
                                    <SelectTrigger
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="positive">
                                            {{
                                                t(
                                                    'accounts.form.fields.openingBalancePositive',
                                                )
                                            }}
                                        </SelectItem>
                                        <SelectItem value="negative">
                                            {{
                                                t(
                                                    'accounts.form.fields.openingBalanceNegative',
                                                )
                                            }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'accounts.form.fields.openingBalanceDirectionHelper',
                                        )
                                    }}
                                </p>
                                <InputError
                                    :message="
                                        form.errors.opening_balance_direction
                                    "
                                />
                            </div>

                            <div v-if="!isCreditCard" class="grid gap-2">
                                <Label for="opening_balance_date">
                                    {{
                                        t(
                                            'accounts.form.fields.openingBalanceDate',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="opening_balance_date"
                                    v-model="form.opening_balance_date"
                                    type="date"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    :required="isOpeningBalanceDateRequired"
                                    :min="openingBalanceDateMin"
                                    :max="openingBalanceDateMax"
                                />
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'accounts.form.fields.openingBalanceDateHelper',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="openingBalanceDateConstraintMessage"
                                    class="text-xs text-amber-600 dark:text-amber-400"
                                >
                                    {{ openingBalanceDateConstraintMessage }}
                                </p>
                                <InputError
                                    :message="form.errors.opening_balance_date"
                                />
                            </div>

                            <div class="grid gap-2">
                                <MobileAmountInput
                                    id="current_balance"
                                    v-model="form.current_balance"
                                    :label="
                                        t('accounts.form.fields.currentBalance')
                                    "
                                    :format-locale="moneyFormatLocale"
                                    :currency-code="moneyCurrencyCode"
                                    :disabled="isCurrentBalanceReadonly"
                                    :readonly="isCurrentBalanceReadonly"
                                    class="border-slate-200 dark:border-slate-800"
                                    placeholder="0"
                                    :error="form.errors.current_balance"
                                />
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'accounts.form.fields.currentBalanceHelper',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="isCreditCard"
                            class="grid gap-5 rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 dark:border-slate-800 dark:bg-slate-950/80"
                        >
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('accounts.form.creditCard.title') }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('accounts.form.creditCard.helper') }}
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <MobileAmountInput
                                        id="credit_limit"
                                        v-model="form.settings.credit_limit"
                                        :label="
                                            t('accounts.form.creditCard.limit')
                                        "
                                        :format-locale="moneyFormatLocale"
                                        :currency-code="moneyCurrencyCode"
                                        class="border-slate-200 dark:border-slate-800"
                                        placeholder="0"
                                        :error="
                                            form.errors['settings.credit_limit']
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label>{{
                                        t(
                                            'accounts.form.creditCard.linkedPaymentAccount',
                                        )
                                    }}</Label>
                                    <Select
                                        :disabled="
                                            creditCardLinkedPaymentAccountDisabled
                                        "
                                        :model-value="
                                            String(
                                                form.settings
                                                    .linked_payment_account_uuid,
                                            )
                                        "
                                        @update:model-value="
                                            form.settings.linked_payment_account_uuid =
                                                String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue
                                                :placeholder="
                                                    t(
                                                        'accounts.form.creditCard.noLinkedPaymentAccount',
                                                    )
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="NONE_OPTION">
                                                {{
                                                    t(
                                                        'accounts.form.creditCard.noLinkedPaymentAccount',
                                                    )
                                                }}
                                            </SelectItem>
                                            <SelectItem
                                                v-for="option in availableLinkedPaymentAccounts"
                                                :key="option.uuid"
                                                :value="option.uuid"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            form.errors[
                                                'settings.linked_payment_account_uuid'
                                            ]
                                        "
                                    />
                                    <p
                                        v-if="creditCardLinkedPaymentAccountHelper"
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            creditCardLinkedPaymentAccountHelper
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="statement_closing_day">{{
                                        t(
                                            'accounts.form.creditCard.statementClosingDay',
                                        )
                                    }}</Label>
                                    <Select
                                        :model-value="
                                            String(
                                                form.settings
                                                    .statement_closing_day ||
                                                    '',
                                            )
                                        "
                                        @update:model-value="
                                            form.settings.statement_closing_day =
                                                String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue placeholder="15" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="day in 31"
                                                :key="`closing-${day}`"
                                                :value="String(day)"
                                            >
                                                {{ day }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        {{ creditCardClosingRangePreview }}
                                    </p>
                                    <InputError
                                        :message="
                                            form.errors[
                                                'settings.statement_closing_day'
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="payment_day">{{
                                        t('accounts.form.creditCard.paymentDay')
                                    }}</Label>
                                    <Select
                                        :model-value="
                                            String(
                                                form.settings.payment_day || '',
                                            )
                                        "
                                        @update:model-value="
                                            form.settings.payment_day =
                                                String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue placeholder="16" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="day in 31"
                                                :key="`payment-${day}`"
                                                :value="String(day)"
                                            >
                                                {{ day }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        {{ creditCardNextBillingPreview }}
                                    </p>
                                    <InputError
                                        :message="
                                            form.errors['settings.payment_day']
                                        "
                                    />
                                </div>
                            </div>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-slate-50/90 p-4 dark:bg-slate-900/80"
                            >
                                <Checkbox
                                    :model-value="form.settings.auto_pay"
                                    @update:model-value="setAutoPayState"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'accounts.form.creditCard.autoPay',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'accounts.form.creditCard.autoPayHelp',
                                            )
                                        }}
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div
                            class="grid gap-4 rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('accounts.form.management.title') }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'accounts.form.management.description',
                                        )
                                    }}
                                </p>
                            </div>

                            <label
                                v-if="canConfigureNegativeBalance"
                                class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :disabled="isNegativeBalanceLocked"
                                    :model-value="
                                        form.settings.allow_negative_balance
                                    "
                                    @update:model-value="
                                        setAllowNegativeBalanceState
                                    "
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'accounts.form.management.allowNegativeBalance',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            isNegativeBalanceLocked
                                                ? t(
                                                      'accounts.form.management.allowNegativeBalanceCashLocked',
                                                  )
                                                : t(
                                                      'accounts.form.management.allowNegativeBalanceHelp',
                                                  )
                                        }}
                                    </p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :model-value="form.is_reported"
                                    @update:model-value="setReportedState"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'accounts.form.management.reported',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'accounts.form.management.reportedHelp',
                                            )
                                        }}
                                    </p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :model-value="form.is_default"
                                    @update:model-value="setDefaultState"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'accounts.form.management.defaultAccount',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'accounts.form.management.defaultAccountHelp',
                                            )
                                        }}
                                    </p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :disabled="isProtectedCashAccount"
                                    :model-value="form.is_active"
                                    @update:model-value="setActiveState"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t('accounts.form.management.active')
                                        }}
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            isProtectedCashAccount
                                                ? t(
                                                      'accounts.form.management.activeCashLocked',
                                                  )
                                                : t(
                                                      'accounts.form.management.activeHelp',
                                                  )
                                        }}
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes">{{
                                t('accounts.form.fields.notes')
                            }}</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="min-h-28 rounded-[1.5rem] border border-slate-200 bg-white px-4 py-3 text-sm transition outline-none focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                                :placeholder="
                                    t('accounts.form.fields.notesPlaceholder')
                                "
                            />
                            <InputError :message="form.errors.notes" />
                        </div>

                        <div
                            class="flex flex-col gap-3 border-t border-slate-200/80 pt-5 sm:flex-row sm:justify-end dark:border-slate-800"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                class="h-11 rounded-2xl px-5"
                                @click="closeSheet"
                            >
                                {{ t('accounts.form.actions.cancel') }}
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="h-11 rounded-2xl px-5"
                            >
                                {{
                                    isEditing
                                        ? t('accounts.form.actions.saveChanges')
                                        : t('accounts.form.actions.create')
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
