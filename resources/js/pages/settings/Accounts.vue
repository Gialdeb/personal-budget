<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleCheckBig,
    CreditCard,
    Landmark,
    Plus,
    ShieldAlert,
    Trash2,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import AccountFilters from '@/components/accounts/AccountFilters.vue';
import AccountFormSheet from '@/components/accounts/AccountFormSheet.vue';
import AccountsList from '@/components/accounts/AccountsList.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { formatCurrency } from '@/lib/currency';
import { destroy, edit, toggleActive } from '@/routes/accounts';
import type { AccountItem, AccountsPageProps, BreadcrumbItem } from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<AccountsPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Conti',
        href: edit(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);

const search = ref('');
const activeStatus = ref('all');
const accountTypeId = ref('all');
const balanceNature = ref('all');
const bankId = ref('all');
const formOpen = ref(false);
const editingAccount = ref<AccountItem | null>(null);
const deletingAccount = ref<AccountItem | null>(null);
const selectedAccountId = ref<number | null>(
    props.accounts.data[0]?.id ?? null,
);
const feedback = ref<FeedbackState | null>(null);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

const flashSuccess = computed(() => flash.value.success ?? undefined);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

watch(
    flashSuccess,
    (message) => {
        if (message) {
            feedback.value = {
                variant: 'default',
                title: 'Operazione completata',
                message,
            };
        }
    },
    { immediate: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.delete ?? errors.toggle;

        if (message) {
            feedback.value = {
                variant: 'destructive',
                title: 'Operazione non disponibile',
                message,
            };
        }
    },
    { immediate: true, deep: true },
);

watch(feedback, (value) => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
        feedbackTimeout = null;
    }

    if (!value) {
        return;
    }

    feedbackTimeout = setTimeout(() => {
        feedback.value = null;
        feedbackTimeout = null;
    }, 4000);
});

onUnmounted(() => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }
});

const filteredAccounts = computed(() =>
    props.accounts.data.filter((item) => matchesFilters(item)),
);

watch(
    filteredAccounts,
    (accounts) => {
        if (accounts.length === 0) {
            selectedAccountId.value = null;

            return;
        }

        if (
            selectedAccountId.value === null ||
            !accounts.some((item) => item.id === selectedAccountId.value)
        ) {
            selectedAccountId.value = accounts[0].id;
        }
    },
    { immediate: true },
);

const selectedAccount = computed(
    () =>
        filteredAccounts.value.find(
            (item) => item.id === selectedAccountId.value,
        ) ?? null,
);

const summaryCards = computed(() => [
    {
        label: 'Totali',
        value: props.accounts.summary.total_count,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: 'Attivi',
        value: props.accounts.summary.active_count,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: 'Carte di credito',
        value: props.accounts.summary.credit_cards_count,
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        label: 'Con utilizzi',
        value: props.accounts.summary.used_count,
        tone: 'text-amber-700 dark:text-amber-300',
    },
]);

const filteredSummary = computed(() => ({
    visible: filteredAccounts.value.length,
    active: filteredAccounts.value.filter((item) => item.is_active).length,
    used: filteredAccounts.value.filter((item) => item.used).length,
}));

const deleteReasons = computed(() => {
    if (!deletingAccount.value) {
        return [];
    }

    const reasons: string[] = [];

    if (deletingAccount.value.counts.transactions > 0) {
        reasons.push(
            deletingAccount.value.counts.transactions === 1
                ? 'È usato in 1 transazione.'
                : `È usato in ${deletingAccount.value.counts.transactions} transazioni.`,
        );
    }

    if (deletingAccount.value.counts.imports > 0) {
        reasons.push(
            deletingAccount.value.counts.imports === 1
                ? 'È collegato a 1 import.'
                : `È collegato a ${deletingAccount.value.counts.imports} import.`,
        );
    }

    if (deletingAccount.value.counts.recurring_entries > 0) {
        reasons.push(
            deletingAccount.value.counts.recurring_entries === 1
                ? 'È usato in 1 ricorrenza.'
                : `È usato in ${deletingAccount.value.counts.recurring_entries} ricorrenze.`,
        );
    }

    if (deletingAccount.value.counts.scheduled_entries > 0) {
        reasons.push(
            deletingAccount.value.counts.scheduled_entries === 1
                ? 'È usato in 1 scadenza pianificata.'
                : `È usato in ${deletingAccount.value.counts.scheduled_entries} scadenze pianificate.`,
        );
    }

    if (deletingAccount.value.counts.opening_balances > 0) {
        reasons.push('Ha saldi iniziali registrati.');
    }

    if (deletingAccount.value.counts.balance_snapshots > 0) {
        reasons.push('Ha snapshot di saldo registrati.');
    }

    if (deletingAccount.value.counts.reconciliations > 0) {
        reasons.push('Ha riconciliazioni registrate.');
    }

    if (deletingAccount.value.counts.linked_credit_cards > 0) {
        reasons.push(
            deletingAccount.value.counts.linked_credit_cards === 1
                ? 'È conto di addebito per 1 carta di credito.'
                : `È conto di addebito per ${deletingAccount.value.counts.linked_credit_cards} carte di credito.`,
        );
    }

    return reasons;
});

const emptyMessage = computed(() => {
    if (props.accounts.data.length === 0) {
        return 'Non hai ancora creato conti. Parti dal primo conto o dalla prima carta.';
    }

    return 'Nessun conto corrisponde ai filtri attivi.';
});

function matchesFilters(item: AccountItem): boolean {
    const query = search.value.trim().toLowerCase();

    if (
        query !== '' &&
        ![
            item.name,
            item.bank_name ?? '',
            item.iban ?? '',
            item.account_number_masked ?? '',
            item.account_type.name,
            item.account_type.code,
        ].some((value) => value.toLowerCase().includes(query))
    ) {
        return false;
    }

    if (activeStatus.value === 'active' && !item.is_active) {
        return false;
    }

    if (activeStatus.value === 'inactive' && item.is_active) {
        return false;
    }

    if (
        accountTypeId.value !== 'all' &&
        String(item.account_type_id) !== accountTypeId.value
    ) {
        return false;
    }

    if (
        balanceNature.value !== 'all' &&
        item.balance_nature !== balanceNature.value
    ) {
        return false;
    }

    return bankId.value === 'all' || String(item.user_bank_id) === bankId.value;
}

function openCreateAccount(): void {
    editingAccount.value = null;
    formOpen.value = true;
}

function openEditAccount(item: AccountItem): void {
    editingAccount.value = item;
    formOpen.value = true;
}

function handleSaved(message: string): void {
    feedback.value = {
        variant: 'default',
        title: 'Salvataggio completato',
        message,
    };
}

function selectAccount(item: AccountItem): void {
    selectedAccountId.value = item.id;
}

function toggleAccount(item: AccountItem): void {
    router.patch(
        toggleActive.url(item.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                feedback.value = {
                    variant: 'default',
                    title: 'Stato aggiornato',
                    message: item.is_active
                        ? 'Il conto è stato disattivato.'
                        : 'Il conto è stato attivato.',
                };
            },
            onError: (errors) => {
                feedback.value = {
                    variant: 'destructive',
                    title: 'Aggiornamento non riuscito',
                    message:
                        String(errors.toggle ?? '') ||
                        'Non è stato possibile aggiornare lo stato del conto.',
                };
            },
        },
    );
}

function requestDelete(item: AccountItem): void {
    deletingAccount.value = item;
}

function closeDeleteDialog(): void {
    deletingAccount.value = null;
}

function confirmDelete(): void {
    if (!deletingAccount.value) {
        return;
    }

    router.delete(destroy.url(deletingAccount.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = {
                variant: 'default',
                title: 'Conto eliminato',
                message: 'Il conto è stato rimosso correttamente.',
            };
            closeDeleteDialog();
        },
        onError: (errors) => {
            feedback.value = {
                variant: 'destructive',
                title: 'Eliminazione non riuscita',
                message:
                    String(errors.delete ?? '') ||
                    'Questo conto non può essere eliminato.',
            };
            closeDeleteDialog();
        },
    });
}

function formatBalance(value: number | null, currency: string): string {
    if (value === null) {
        return 'Non impostato';
    }

    return formatCurrency(value, currency);
}

function balanceToneClass(value: number | null): string {
    if (value === null || value === 0) {
        return 'text-slate-700 dark:text-slate-200';
    }

    if (value > 0) {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    return 'text-rose-700 dark:text-rose-300';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Conti" />

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.16),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.16),_transparent_28%),linear-gradient(135deg,rgba(15,23,42,0.03),rgba(255,255,255,0))] px-6 py-6 sm:px-8 sm:py-8 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="max-w-3xl space-y-4">
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-sky-700 uppercase dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300"
                            >
                                <Landmark class="h-3.5 w-3.5" />
                                Conti e carte
                            </div>

                            <div class="space-y-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50"
                                >
                                    Conti
                                </h1>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-[15px] dark:text-slate-300"
                                >
                                    Gestisci conti correnti, carte di credito e
                                    altre posizioni finanziarie mantenendo
                                    coerenti saldo, collegamenti e stato
                                    operativo.
                                </p>
                            </div>
                        </div>

                        <Button
                            class="h-11 rounded-2xl px-5"
                            @click="openCreateAccount"
                        >
                            <Plus class="h-4 w-4" />
                            Nuovo conto
                        </Button>
                    </div>
                </div>

                <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold tracking-tight"
                                :class="card.tone"
                            >
                                {{ card.value }}
                            </p>
                        </article>
                    </div>

                    <Alert
                        v-if="feedback"
                        :variant="feedback.variant"
                        class="rounded-[1.5rem] border"
                    >
                        <CheckCircle2
                            v-if="feedback.variant === 'default'"
                            class="h-4 w-4"
                        />
                        <ShieldAlert v-else class="h-4 w-4" />
                        <AlertTitle>{{ feedback.title }}</AlertTitle>
                        <AlertDescription>
                            <p>{{ feedback.message }}</p>
                        </AlertDescription>
                    </Alert>

                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-3 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-3 opacity-0"
                    >
                        <div
                            v-if="feedback"
                            class="pointer-events-none fixed right-4 bottom-4 z-50 max-w-sm sm:right-6 sm:bottom-6"
                        >
                            <div
                                class="pointer-events-auto overflow-hidden rounded-[1.5rem] border shadow-2xl"
                                :class="
                                    feedback.variant === 'default'
                                        ? 'border-emerald-200 bg-emerald-500 text-white'
                                        : 'border-rose-200 bg-rose-600 text-white'
                                "
                            >
                                <div class="flex items-start gap-3 px-4 py-4">
                                    <div
                                        class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15"
                                    >
                                        <CircleCheckBig
                                            v-if="
                                                feedback.variant === 'default'
                                            "
                                            class="h-5 w-5"
                                        />
                                        <ShieldAlert v-else class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold">
                                            {{ feedback.title }}
                                        </p>
                                        <p class="mt-1 text-sm text-white/90">
                                            {{ feedback.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <AccountFilters
                        v-model:search="search"
                        v-model:active-status="activeStatus"
                        v-model:account-type-id="accountTypeId"
                        v-model:balance-nature="balanceNature"
                        v-model:bank-id="bankId"
                        :banks="options.banks"
                        :account-types="options.account_types"
                        :balance-nature-options="options.balance_natures"
                    />

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                        <section class="space-y-4">
                            <div
                                class="flex flex-col gap-3 rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/75"
                            >
                                <div class="space-y-1">
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        Elenco conti
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ filteredSummary.visible }} visibili,
                                        {{ filteredSummary.active }} attivi,
                                        {{ filteredSummary.used }} con utilizzi.
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full"
                                    >
                                        Mobile cards
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full"
                                    >
                                        Desktop table
                                    </Badge>
                                </div>
                            </div>

                            <AccountsList
                                :accounts="filteredAccounts"
                                :selected-account-id="selectedAccountId"
                                :empty-message="emptyMessage"
                                @select="selectAccount"
                                @edit="openEditAccount"
                                @toggle-active="toggleAccount"
                                @delete="requestDelete"
                            />
                        </section>

                        <aside class="space-y-4">
                            <section
                                class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/80"
                            >
                                <div v-if="selectedAccount" class="space-y-5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        >
                                            <component
                                                :is="
                                                    selectedAccount.account_type
                                                        .code === 'credit_card'
                                                        ? CreditCard
                                                        : Landmark
                                                "
                                                class="h-5 w-5"
                                            />
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ selectedAccount.name }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    selectedAccount.account_type
                                                        .name
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full"
                                        >
                                            {{
                                                selectedAccount.balance_nature_label
                                            }}
                                        </Badge>
                                        <Badge
                                            class="rounded-full"
                                            :class="
                                                selectedAccount.is_active
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                            "
                                        >
                                            {{
                                                selectedAccount.is_active
                                                    ? 'Attivo'
                                                    : 'Disattivo'
                                            }}
                                        </Badge>
                                        <Badge
                                            class="rounded-full"
                                            :class="
                                                selectedAccount.is_manual
                                                    ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                            "
                                        >
                                            {{
                                                selectedAccount.is_manual
                                                    ? 'Manuale'
                                                    : 'Importato'
                                            }}
                                        </Badge>
                                    </div>

                                    <div class="space-y-3 text-sm">
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >Banca</span
                                            >
                                            <span
                                                class="text-right font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.bank_name ??
                                                    'Non impostata'
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >Scope</span
                                            >
                                            <span
                                                class="text-right font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.scope
                                                        ?.name ?? 'Nessuno'
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >Valuta</span
                                            >
                                            <span
                                                class="font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{ selectedAccount.currency }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >Saldo iniziale</span
                                            >
                                            <span
                                                class="text-right font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    formatBalance(
                                                        selectedAccount.opening_balance,
                                                        selectedAccount.currency,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >Saldo corrente</span
                                            >
                                            <span
                                                class="rounded-2xl px-3 py-1.5 text-right text-lg font-bold tracking-tight"
                                                :class="
                                                    balanceToneClass(
                                                        selectedAccount.current_balance,
                                                    )
                                                "
                                            >
                                                {{
                                                    formatBalance(
                                                        selectedAccount.current_balance,
                                                        selectedAccount.currency,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >Saldo negativo</span
                                            >
                                            <span
                                                class="text-right font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.account_type
                                                        .code === 'credit_card'
                                                        ? 'Gestito dal limite carta'
                                                        : selectedAccount.allow_negative_balance
                                                          ? 'Consentito'
                                                          : 'Non consentito'
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="
                                                selectedAccount.account_number_masked
                                            "
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >Numero</span
                                            >
                                            <span
                                                class="font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.account_number_masked
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="selectedAccount.iban"
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >IBAN</span
                                            >
                                            <span
                                                class="text-right font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{ selectedAccount.iban }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            selectedAccount.account_type
                                                .code === 'credit_card' &&
                                            selectedAccount.credit_card_settings
                                        "
                                        class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/90 p-4 dark:border-slate-800 dark:bg-slate-900/80"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            Impostazioni carta di credito
                                        </p>
                                        <div class="mt-4 space-y-3 text-sm">
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >Limite</span
                                                >
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .credit_card_settings
                                                            .credit_limit !==
                                                        null
                                                            ? formatCurrency(
                                                                  selectedAccount
                                                                      .credit_card_settings
                                                                      .credit_limit,
                                                                  selectedAccount.currency,
                                                              )
                                                            : 'Non impostato'
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >Conto addebito</span
                                                >
                                                <span
                                                    class="text-right font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .linked_payment_account
                                                            ?.name ??
                                                        'Non collegato'
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >Chiusura estratto</span
                                                >
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .credit_card_settings
                                                            .statement_closing_day ??
                                                        'Non impostato'
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >Pagamento</span
                                                >
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .credit_card_settings
                                                            .payment_day ??
                                                        'Non impostato'
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >Auto pay</span
                                                >
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .credit_card_settings
                                                            .auto_pay
                                                            ? 'Sì'
                                                            : 'No'
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/85 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            Utilizzi e collegamenti
                                        </p>
                                        <div
                                            class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span>Transazioni</span>
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount.counts
                                                            .transactions
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span>Import</span>
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount.counts
                                                            .imports
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span>Ricorrenze</span>
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount.counts
                                                            .recurring_entries
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span
                                                    >Scadenze pianificate</span
                                                >
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount.counts
                                                            .scheduled_entries
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span>Snapshot saldo</span>
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount.counts
                                                            .balance_snapshots
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3"
                                            >
                                                <span>Carte collegate</span>
                                                <span
                                                    class="font-medium text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount.counts
                                                            .linked_credit_cards
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="selectedAccount.notes"
                                        class="rounded-[1.5rem] border border-slate-200/80 bg-white/80 p-4 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300"
                                    >
                                        <p
                                            class="mb-2 font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            Note
                                        </p>
                                        <p>{{ selectedAccount.notes }}</p>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                                >
                                    Seleziona un conto per vedere il riepilogo.
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </section>

            <AccountFormSheet
                v-model:open="formOpen"
                :account="editingAccount"
                :banks="options.banks"
                :scopes="options.scopes"
                :account-types="options.account_types"
                :linked-payment-account-options="
                    options.linked_payment_accounts
                "
                @saved="handleSaved"
            />

            <Dialog
                :open="deletingAccount !== null"
                @update:open="!$event ? closeDeleteDialog() : null"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle class="flex items-center gap-2">
                            <Trash2 class="h-4 w-4" />
                            Elimina conto
                        </DialogTitle>
                        <DialogDescription class="leading-6">
                            <template v-if="deletingAccount?.is_deletable">
                                Stai per eliminare
                                <strong>{{ deletingAccount?.name }}</strong
                                >. L’operazione è definitiva.
                            </template>
                            <template v-else>
                                <strong>{{ deletingAccount?.name }}</strong>
                                non può essere eliminato in questo momento.
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deleteReasons.length > 0"
                        class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        <p class="font-medium">Motivi del blocco</p>
                        <ul class="mt-2 space-y-1">
                            <li v-for="reason in deleteReasons" :key="reason">
                                {{ reason }}
                            </li>
                        </ul>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            @click="closeDeleteDialog"
                        >
                            Chiudi
                        </Button>
                        <Button
                            v-if="deletingAccount?.is_deletable"
                            type="button"
                            variant="destructive"
                            class="rounded-xl"
                            @click="confirmDelete"
                        >
                            Elimina conto
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
