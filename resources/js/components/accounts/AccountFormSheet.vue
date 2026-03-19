<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { store, update } from '@/routes/accounts';
import type {
    AccountBankOption,
    AccountItem,
    AccountScopeOption,
    AccountTypeOption,
    LinkedPaymentAccountOption,
} from '@/types';

const NONE_OPTION = '__none__';

const props = defineProps<{
    open: boolean;
    account?: AccountItem | null;
    banks: AccountBankOption[];
    scopes: AccountScopeOption[];
    accountTypes: AccountTypeOption[];
    linkedPaymentAccountOptions: LinkedPaymentAccountOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
}>();

const form = useForm({
    name: '',
    user_bank_id: NONE_OPTION,
    account_type_id: '',
    scope_id: NONE_OPTION,
    currency: 'EUR',
    iban: '',
    account_number_masked: '',
    opening_balance: '',
    current_balance: '',
    is_manual: false,
    is_active: true,
    notes: '',
    settings: {
        credit_limit: '',
        linked_payment_account_id: NONE_OPTION,
        statement_closing_day: '',
        payment_day: '',
        auto_pay: false,
    },
});

const isEditing = computed(
    () => props.account !== null && props.account !== undefined,
);

const selectedAccountType = computed(
    () =>
        props.accountTypes.find(
            (option) => String(option.id) === String(form.account_type_id),
        ) ?? null,
);

const isCreditCard = computed(
    () => selectedAccountType.value?.code === 'credit_card',
);

const isCashAccount = computed(
    () => selectedAccountType.value?.code === 'cash_account',
);

const availableLinkedPaymentAccounts = computed(() =>
    props.linkedPaymentAccountOptions.filter((option) => {
        if (props.account && option.id === props.account.id) {
            return false;
        }

        const selectedLinkedPaymentAccountId =
            form.settings.linked_payment_account_id !== NONE_OPTION
                ? Number(form.settings.linked_payment_account_id)
                : null;

        return option.is_active || option.id === selectedLinkedPaymentAccountId;
    }),
);

const sheetTitle = computed(() =>
    isEditing.value ? 'Modifica account' : 'Nuovo account',
);

const sheetDescription = computed(() =>
    isEditing.value
        ? 'Aggiorna i dati del conto, della carta o della posizione selezionata.'
        : 'Crea un nuovo account pronto per movimenti, import e riconciliazioni.',
);

watch(
    () => [props.open, props.account] as const,
    ([open, account]) => {
        if (!open) {
            return;
        }

        form.clearErrors();

        if (account) {
            form.defaults({
                name: account.name,
                user_bank_id: account.user_bank_id
                    ? String(account.user_bank_id)
                    : NONE_OPTION,
                account_type_id: String(account.account_type_id),
                scope_id: account.scope_id
                    ? String(account.scope_id)
                    : NONE_OPTION,
                currency: account.currency,
                iban: account.iban ?? '',
                account_number_masked: account.account_number_masked ?? '',
                opening_balance:
                    account.opening_balance !== null
                        ? String(account.opening_balance)
                        : '',
                current_balance:
                    account.current_balance !== null
                        ? String(account.current_balance)
                        : '',
                is_manual: account.is_manual,
                is_active: account.is_active,
                notes: account.notes ?? '',
                settings: {
                    credit_limit:
                        account.credit_card_settings?.credit_limit !== null
                            ? String(account.credit_card_settings?.credit_limit)
                            : '',
                    linked_payment_account_id: account.credit_card_settings
                        ?.linked_payment_account_id
                        ? String(
                              account.credit_card_settings
                                  .linked_payment_account_id,
                          )
                        : NONE_OPTION,
                    statement_closing_day:
                        account.credit_card_settings?.statement_closing_day !==
                        null
                            ? String(
                                  account.credit_card_settings
                                      ?.statement_closing_day,
                              )
                            : '',
                    payment_day:
                        account.credit_card_settings?.payment_day !== null
                            ? String(account.credit_card_settings?.payment_day)
                            : '',
                    auto_pay: account.credit_card_settings?.auto_pay ?? false,
                },
            });
            form.reset();

            return;
        }

        form.defaults({
            name: '',
            user_bank_id: NONE_OPTION,
            account_type_id: '',
            scope_id: NONE_OPTION,
            currency: 'EUR',
            iban: '',
            account_number_masked: '',
            opening_balance: '',
            current_balance: '',
            is_manual: false,
            is_active: true,
            notes: '',
            settings: {
                credit_limit: '',
                linked_payment_account_id: NONE_OPTION,
                statement_closing_day: '',
                payment_day: '',
                auto_pay: false,
            },
        });
        form.reset();
    },
    { immediate: true },
);

watch(isCreditCard, (value) => {
    if (value) {
        return;
    }

    form.settings.credit_limit = '';
    form.settings.linked_payment_account_id = NONE_OPTION;
    form.settings.statement_closing_day = '';
    form.settings.payment_day = '';
    form.settings.auto_pay = false;
});

watch(isCashAccount, (value) => {
    if (!value) {
        return;
    }

    form.iban = '';
    form.account_number_masked = '';
});

function closeSheet(): void {
    emit('update:open', false);
}

function setManualState(checked: boolean | 'indeterminate'): void {
    form.is_manual = checked === true;
}

function setActiveState(checked: boolean | 'indeterminate'): void {
    form.is_active = checked === true;
}

function setAutoPayState(checked: boolean | 'indeterminate'): void {
    form.settings.auto_pay = checked === true;
}

function submit(): void {
    const payload = {
        ...form.data(),
        user_bank_id:
            form.user_bank_id === NONE_OPTION
                ? null
                : Number(form.user_bank_id),
        scope_id: form.scope_id === NONE_OPTION ? null : Number(form.scope_id),
        account_type_id: Number(form.account_type_id),
        opening_balance:
            form.opening_balance !== '' ? Number(form.opening_balance) : null,
        current_balance:
            form.current_balance !== '' ? Number(form.current_balance) : null,
        settings: {
            credit_limit:
                form.settings.credit_limit !== ''
                    ? Number(form.settings.credit_limit)
                    : null,
            linked_payment_account_id:
                form.settings.linked_payment_account_id !== NONE_OPTION
                    ? Number(form.settings.linked_payment_account_id)
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
        form.transform(() => payload).patch(update.url(props.account.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved', 'Account aggiornato con successo.');
                closeSheet();
            },
        });

        return;
    }

    form.transform(() => payload).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', 'Account creato con successo.');
            closeSheet();
        },
    });
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
                                <Label for="name">Nome account</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="Es. Conto principale, Visa personale"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label>Tipo account</Label>
                                <Select
                                    :model-value="String(form.account_type_id)"
                                    @update:model-value="
                                        form.account_type_id = String($event)
                                    "
                                >
                                    <SelectTrigger
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    >
                                        <SelectValue
                                            placeholder="Seleziona un tipo account"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in accountTypes"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ option.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.account_type_id"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>Natura saldo</Label>
                                <div
                                    class="flex h-11 items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    {{
                                        selectedAccountType?.balance_nature_label ??
                                        'Seleziona prima il tipo account'
                                    }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label>Banca</Label>
                                <Select
                                    :model-value="String(form.user_bank_id)"
                                    @update:model-value="
                                        form.user_bank_id = String($event)
                                    "
                                >
                                    <SelectTrigger
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    >
                                        <SelectValue
                                            placeholder="Nessuna banca"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NONE_OPTION">
                                            Nessuna banca
                                        </SelectItem>
                                        <SelectItem
                                            v-for="option in banks"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ option.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.user_bank_id"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>Scope</Label>
                                <Select
                                    :model-value="String(form.scope_id)"
                                    @update:model-value="
                                        form.scope_id = String($event)
                                    "
                                >
                                    <SelectTrigger
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    >
                                        <SelectValue
                                            placeholder="Nessuno scope"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NONE_OPTION">
                                            Nessuno scope
                                        </SelectItem>
                                        <SelectItem
                                            v-for="option in scopes"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ option.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.scope_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="currency">Valuta</Label>
                                <Input
                                    id="currency"
                                    v-model="form.currency"
                                    maxlength="3"
                                    class="h-11 rounded-2xl border-slate-200 uppercase dark:border-slate-800"
                                    placeholder="EUR"
                                />
                                <InputError :message="form.errors.currency" />
                            </div>

                            <div v-if="!isCashAccount" class="grid gap-2">
                                <Label for="iban">IBAN</Label>
                                <Input
                                    id="iban"
                                    v-model="form.iban"
                                    class="h-11 rounded-2xl border-slate-200 uppercase dark:border-slate-800"
                                    placeholder="IT60X0542811101000000123456"
                                />
                                <InputError :message="form.errors.iban" />
                            </div>

                            <div v-if="!isCashAccount" class="grid gap-2">
                                <Label for="account_number_masked"
                                    >Numero mascherato</Label
                                >
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

                            <div class="grid gap-2">
                                <Label for="opening_balance"
                                    >Saldo iniziale</Label
                                >
                                <Input
                                    id="opening_balance"
                                    v-model="form.opening_balance"
                                    type="number"
                                    step="0.01"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="0.00"
                                />
                                <InputError
                                    :message="form.errors.opening_balance"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="current_balance"
                                    >Saldo corrente</Label
                                >
                                <Input
                                    id="current_balance"
                                    v-model="form.current_balance"
                                    type="number"
                                    step="0.01"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="0.00"
                                />
                                <InputError
                                    :message="form.errors.current_balance"
                                />
                            </div>
                        </div>

                        <div
                            class="grid gap-4 rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    Stato e gestione
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Definisci se l’account è manuale e se deve
                                    restare attivo nella gestione operativa.
                                </p>
                            </div>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :model-value="form.is_manual"
                                    @update:model-value="setManualState"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        Gestione manuale
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        Attiva se il saldo non viene alimentato
                                        da import automatici.
                                    </p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/80 p-4 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :model-value="form.is_active"
                                    @update:model-value="setActiveState"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        Account attivo
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        Un account disattivo resta storico ma
                                        non dovrebbe essere usato come conto
                                        operativo principale.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div
                            v-if="isCreditCard"
                            class="grid gap-5 rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 dark:border-slate-800 dark:bg-slate-950/80"
                        >
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    Impostazioni carta di credito
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Questi dati vengono salvati nel JSON
                                    `settings` dell’account.
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="credit_limit"
                                        >Limite carta</Label
                                    >
                                    <Input
                                        id="credit_limit"
                                        v-model="form.settings.credit_limit"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        placeholder="5000.00"
                                    />
                                    <InputError
                                        :message="
                                            form.errors['settings.credit_limit']
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label>Conto di addebito</Label>
                                    <Select
                                        :model-value="
                                            String(
                                                form.settings
                                                    .linked_payment_account_id,
                                            )
                                        "
                                        @update:model-value="
                                            form.settings.linked_payment_account_id =
                                                String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue
                                                placeholder="Nessun conto collegato"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="NONE_OPTION">
                                                Nessun conto collegato
                                            </SelectItem>
                                            <SelectItem
                                                v-for="option in availableLinkedPaymentAccounts"
                                                :key="option.id"
                                                :value="String(option.id)"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            form.errors[
                                                'settings.linked_payment_account_id'
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="statement_closing_day"
                                        >Giorno chiusura estratto</Label
                                    >
                                    <Input
                                        id="statement_closing_day"
                                        v-model="
                                            form.settings.statement_closing_day
                                        "
                                        type="number"
                                        min="1"
                                        max="31"
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        placeholder="15"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                'settings.statement_closing_day'
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="payment_day"
                                        >Giorno pagamento</Label
                                    >
                                    <Input
                                        id="payment_day"
                                        v-model="form.settings.payment_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        placeholder="3"
                                    />
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
                                        Auto pay
                                    </p>
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        Segnala che il pagamento della carta
                                        viene addebitato automaticamente.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes">Note</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="min-h-28 rounded-[1.5rem] border border-slate-200 bg-white px-4 py-3 text-sm transition outline-none focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950"
                                placeholder="Annotazioni operative, dettagli utili o memo interni"
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
                                Annulla
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="h-11 rounded-2xl px-5"
                            >
                                {{
                                    isEditing
                                        ? 'Salva modifiche'
                                        : 'Crea account'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
