<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CreditCard, Landmark, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AccountFilters from '@/components/accounts/AccountFilters.vue';
import AccountFormSheet from '@/components/accounts/AccountFormSheet.vue';
import AccountSharingPanel from '@/components/accounts/AccountSharingPanel.vue';
import AccountsList from '@/components/accounts/AccountsList.vue';
import AppToastStack from '@/components/ui/AppToastStack.vue';
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
import { useToastFeedback } from '@/composables/useToastFeedback';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { formatCurrency, formatCurrencyLabel } from '@/lib/currency';
import { destroy, edit, toggleActive } from '@/routes/accounts';
import { leave as leaveMembership } from '@/routes/sharing/account-memberships';
import type {
    AccountItem,
    AccountsPageProps,
    BreadcrumbItem,
    SharedAccountItem,
} from '@/types';

const props = defineProps<Partial<AccountsPageProps>>();
const { t } = useI18n();

const accountsData = computed<AccountItem[]>(() => props.accounts?.data ?? []);
const accountsSummary = computed(() => ({
    total_count:
        props.accounts?.summary?.total_count ?? accountsData.value.length,
    active_count:
        props.accounts?.summary?.active_count ??
        accountsData.value.filter((item) => item.is_active).length,
    inactive_count:
        props.accounts?.summary?.inactive_count ??
        accountsData.value.filter((item) => !item.is_active).length,
    manual_count:
        props.accounts?.summary?.manual_count ??
        accountsData.value.filter((item) => item.is_manual).length,
    credit_cards_count:
        props.accounts?.summary?.credit_cards_count ??
        accountsData.value.filter(
            (item) => item.account_type?.code === 'credit_card',
        ).length,
    used_count:
        props.accounts?.summary?.used_count ??
        accountsData.value.filter((item) => item.used).length,
}));
const accountOptions = computed(() => ({
    opening_balance_date: props.options?.opening_balance_date ?? {
        available_years: [],
        min: null,
        max: null,
        today: new Date().toISOString().slice(0, 10),
    },
    banks: props.options?.banks ?? [],
    account_types: props.options?.account_types ?? [],
    balance_natures: props.options?.balance_natures ?? [],
    currencies: props.options?.currencies ?? [],
    linked_payment_accounts: props.options?.linked_payment_accounts ?? [],
    default_account_uuid: props.options?.default_account_uuid ?? null,
}));

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('accounts.title'),
        href: edit(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);

const search = ref('');
const activeStatus = ref('all');
const accountTypeUuid = ref('all');
const balanceNature = ref('all');
const bankUuid = ref('all');
const formOpen = ref(false);
const editingAccount = ref<AccountItem | null>(null);
const deletingAccount = ref<AccountItem | null>(null);
const leavingSharedAccount = ref<SharedAccountItem | null>(null);
const selectedAccountUuid = ref<string | null>(
    accountsData.value[0]?.uuid ?? null,
);
const selectedSharingAccountUuid = ref<string | null>(null);
const { feedback, showFeedback } = useToastFeedback();

const flashSuccess = computed(() => flash.value.success ?? undefined);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

watch(
    flashSuccess,
    (message) => {
        if (message) {
            showFeedback({
                variant: 'default',
                title: t('accounts.feedback.successTitle'),
                message,
            });
        }
    },
    { immediate: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.delete ?? errors.toggle;

        if (message) {
            showFeedback({
                variant: 'destructive',
                title: t('accounts.feedback.unavailableTitle'),
                message,
            });
        }
    },
    { immediate: true, deep: true },
);

const filteredAccounts = computed(() =>
    accountsData.value.filter((item) => matchesFilters(item)),
);
const shareableAccounts = computed(() =>
    filteredAccounts.value.filter(
        (item) =>
            item.account_type?.code !== 'cash_account' &&
            item.account_type?.code !== 'credit_card',
    ),
);
const sharedAccounts = computed<SharedAccountItem[]>(() => {
    const source =
        (page.props.shared_accounts as unknown) ?? props.shared_accounts ?? [];

    if (Array.isArray(source)) {
        return source as SharedAccountItem[];
    }

    if (source && typeof source === 'object') {
        return Object.values(source as Record<string, SharedAccountItem>);
    }

    return [];
});
const hasSharedAccounts = computed(() => sharedAccounts.value.length > 0);
const sharedAccountsCount = computed(() => sharedAccounts.value.length);

watch(
    filteredAccounts,
    (accounts) => {
        if (accounts.length === 0) {
            selectedAccountUuid.value = null;

            return;
        }

        if (
            selectedAccountUuid.value === null ||
            !accounts.some((item) => item.uuid === selectedAccountUuid.value)
        ) {
            selectedAccountUuid.value = accounts[0].uuid;
        }
    },
    { immediate: true },
);

watch(
    shareableAccounts,
    (accounts) => {
        if (accounts.length === 0) {
            selectedSharingAccountUuid.value = null;

            return;
        }

        if (
            selectedSharingAccountUuid.value === null ||
            !accounts.some(
                (item) => item.uuid === selectedSharingAccountUuid.value,
            )
        ) {
            selectedSharingAccountUuid.value = accounts[0].uuid;
        }
    },
    { immediate: true },
);

const selectedAccount = computed(
    () =>
        filteredAccounts.value.find(
            (item) => item.uuid === selectedAccountUuid.value,
        ) ?? null,
);
const selectedSharingAccount = computed(
    () =>
        shareableAccounts.value.find(
            (item) => item.uuid === selectedSharingAccountUuid.value,
        ) ?? null,
);

const summaryCards = computed(() => [
    {
        label: t('accounts.summary.total'),
        value: accountsSummary.value.total_count,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: t('accounts.summary.active'),
        value: accountsSummary.value.active_count,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: t('accounts.summary.creditCards'),
        value: accountsSummary.value.credit_cards_count,
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        label: t('accounts.summary.used'),
        value: accountsSummary.value.used_count,
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

    const counts = deletingAccount.value.counts ?? {
        transactions: 0,
        imports: 0,
        recurring_entries: 0,
        scheduled_entries: 0,
        opening_balances: 0,
        balance_snapshots: 0,
        reconciliations: 0,
        linked_credit_cards: 0,
    };
    const reasons: string[] = [];

    if (counts.transactions > 0) {
        reasons.push(
            counts.transactions === 1
                ? t('accounts.deleteReasons.transactionOne')
                : t('accounts.deleteReasons.transactionMany', {
                      count: counts.transactions,
                  }),
        );
    }

    if (counts.imports > 0) {
        reasons.push(
            counts.imports === 1
                ? t('accounts.deleteReasons.importOne')
                : t('accounts.deleteReasons.importMany', {
                      count: counts.imports,
                  }),
        );
    }

    if (counts.recurring_entries > 0) {
        reasons.push(
            counts.recurring_entries === 1
                ? t('accounts.deleteReasons.recurringOne')
                : t('accounts.deleteReasons.recurringMany', {
                      count: counts.recurring_entries,
                  }),
        );
    }

    if (counts.scheduled_entries > 0) {
        reasons.push(
            counts.scheduled_entries === 1
                ? t('accounts.deleteReasons.scheduledOne')
                : t('accounts.deleteReasons.scheduledMany', {
                      count: counts.scheduled_entries,
                  }),
        );
    }

    if (counts.opening_balances > 0) {
        reasons.push(t('accounts.deleteReasons.openingBalances'));
    }

    if (counts.balance_snapshots > 0) {
        reasons.push(t('accounts.deleteReasons.balanceSnapshots'));
    }

    if (counts.reconciliations > 0) {
        reasons.push(t('accounts.deleteReasons.reconciliations'));
    }

    if (counts.linked_credit_cards > 0) {
        reasons.push(
            counts.linked_credit_cards === 1
                ? t('accounts.deleteReasons.linkedCreditCardOne')
                : t('accounts.deleteReasons.linkedCreditCardMany', {
                      count: counts.linked_credit_cards,
                  }),
        );
    }

    return reasons;
});

const emptyMessage = computed(() => {
    if (accountsData.value.length === 0) {
        return t('accounts.empty.initial');
    }

    return t('accounts.empty.filtered');
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
            item.account_type?.name ?? '',
            item.account_type?.code ?? '',
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
        accountTypeUuid.value !== 'all' &&
        item.account_type_uuid !== accountTypeUuid.value
    ) {
        return false;
    }

    if (
        balanceNature.value !== 'all' &&
        item.balance_nature !== balanceNature.value
    ) {
        return false;
    }

    return bankUuid.value === 'all' || item.user_bank_uuid === bankUuid.value;
}

function openCreateAccount(): void {
    editingAccount.value = null;
    formOpen.value = true;
}

function consumeCreateAccountQuery(): boolean {
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

function openEditAccount(item: AccountItem): void {
    editingAccount.value = item;
    formOpen.value = true;
}

function handleSaved(message: string): void {
    showFeedback({
        variant: 'default',
        title: t('accounts.feedback.saveTitle'),
        message,
    });
}

function selectAccount(item: AccountItem): void {
    selectedAccountUuid.value = item.uuid;
}

function selectSharingAccount(accountUuid: string): void {
    selectedSharingAccountUuid.value = accountUuid;
}

function toggleAccount(item: AccountItem): void {
    if (!item.can_toggle_active) {
        return;
    }

    router.patch(
        toggleActive.url(item.uuid),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showFeedback({
                    variant: 'default',
                    title: t('accounts.feedback.statusTitle'),
                    message: item.is_active
                        ? t('accounts.feedback.statusDeactivated')
                        : t('accounts.feedback.statusActivated'),
                });
            },
            onError: (errors) => {
                showFeedback({
                    variant: 'destructive',
                    title: t('accounts.feedback.statusTitle'),
                    message:
                        String(errors.toggle ?? '') ||
                        t('accounts.feedback.statusError'),
                });
            },
        },
    );
}

function requestDelete(item: AccountItem): void {
    if (!item.is_deletable) {
        return;
    }

    deletingAccount.value = item;
}

function closeDeleteDialog(): void {
    deletingAccount.value = null;
}

function requestLeaveSharedAccount(item: SharedAccountItem): void {
    leavingSharedAccount.value = item;
}

function closeLeaveSharedAccountDialog(): void {
    leavingSharedAccount.value = null;
}

function confirmLeaveSharedAccount(): void {
    if (!leavingSharedAccount.value?.membership_uuid) {
        return;
    }

    router.post(
        leaveMembership.url(leavingSharedAccount.value.membership_uuid),
        { reason: null },
        {
            preserveScroll: true,
            onSuccess: () => {
                showFeedback({
                    variant: 'default',
                    title: t('accounts.page.leaveTitle'),
                    message: t('accounts.feedback.deletedMessage'),
                });
                closeLeaveSharedAccountDialog();
            },
            onError: () => {
                showFeedback({
                    variant: 'destructive',
                    title: t('accounts.page.leaveTitle'),
                    message: t('accounts.sharing.feedback.actionError'),
                });
                closeLeaveSharedAccountDialog();
            },
        },
    );
}

function confirmDelete(): void {
    if (!deletingAccount.value) {
        return;
    }

    router.delete(destroy.url(deletingAccount.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            showFeedback({
                variant: 'default',
                title: t('accounts.feedback.deletedTitle'),
                message: t('accounts.feedback.deletedMessage'),
            });
            closeDeleteDialog();
        },
        onError: (errors) => {
            showFeedback({
                variant: 'destructive',
                title: t('accounts.feedback.deleteErrorTitle'),
                message:
                    String(errors.delete ?? '') ||
                    t('accounts.feedback.deleteErrorMessage'),
            });
            closeDeleteDialog();
        },
    });
}

function formatBalance(value: number | null, currency: string): string {
    if (value === null) {
        return t('accounts.empty.notSet');
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

watch(
    () => page.url,
    () => {
        if (consumeCreateAccountQuery()) {
            openCreateAccount();
        }
    },
    { immediate: true },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('accounts.title')" />

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
                                {{ t('accounts.page.badge') }}
                            </div>

                            <div class="space-y-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50"
                                >
                                    {{ t('accounts.title') }}
                                </h1>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-[15px] dark:text-slate-300"
                                >
                                    {{ t('accounts.page.description') }}
                                </p>
                            </div>
                        </div>

                        <Button
                            class="h-11 rounded-2xl px-5"
                            @click="openCreateAccount"
                        >
                            <Plus class="h-4 w-4" />
                            {{ t('accounts.page.newAccount') }}
                        </Button>
                    </div>
                </div>

                <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
                    <AppToastStack :items="[feedback]" />
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

                    <AccountFilters
                        v-model:search="search"
                        v-model:active-status="activeStatus"
                        v-model:account-type-uuid="accountTypeUuid"
                        v-model:balance-nature="balanceNature"
                        v-model:bank-uuid="bankUuid"
                        :banks="accountOptions.banks"
                        :account-types="accountOptions.account_types"
                        :balance-nature-options="accountOptions.balance_natures"
                    />

                    <section
                        class="rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('accounts.page.sharedTitle') }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('accounts.page.sharedDescription') }}
                                </p>
                            </div>
                            <Badge
                                variant="secondary"
                                class="w-fit rounded-full"
                            >
                                {{ sharedAccountsCount }}
                            </Badge>
                        </div>

                        <div
                            v-if="hasSharedAccounts"
                            class="mt-4 grid gap-3 lg:grid-cols-2"
                        >
                            <article
                                v-for="account in sharedAccounts"
                                :key="account.uuid"
                                class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ account.name }}
                                        </p>
                                        <p
                                            class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                account.bank_name ??
                                                t('accounts.list.bankUnset')
                                            }}
                                        </p>
                                    </div>
                                    <p
                                        class="text-right text-sm font-semibold"
                                        :class="
                                            balanceToneClass(
                                                account.current_balance,
                                            )
                                        "
                                    >
                                        {{
                                            formatBalance(
                                                account.current_balance,
                                                account.currency,
                                            )
                                        }}
                                    </p>
                                </div>

                                <dl
                                    class="mt-4 grid gap-3 text-xs sm:grid-cols-3"
                                >
                                    <div
                                        class="rounded-2xl bg-white/80 p-3 dark:bg-slate-950/70"
                                    >
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{ t('accounts.detail.owner') }}
                                        </dt>
                                        <dd
                                            class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{ account.owner_name ?? '—' }}
                                        </dd>
                                    </div>
                                    <div
                                        class="rounded-2xl bg-white/80 p-3 dark:bg-slate-950/70"
                                    >
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{ t('accounts.detail.role') }}
                                        </dt>
                                        <dd
                                            class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                account.membership_role_label ??
                                                '—'
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        class="rounded-2xl bg-white/80 p-3 dark:bg-slate-950/70"
                                    >
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{ t('accounts.detail.status') }}
                                        </dt>
                                        <dd
                                            class="mt-1 font-medium text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                account.membership_status_label ??
                                                '—'
                                            }}
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    v-if="
                                        account.can_leave &&
                                        account.membership_uuid
                                    "
                                    class="mt-4 flex justify-end"
                                >
                                    <Button
                                        variant="outline"
                                        class="rounded-full"
                                        @click="
                                            requestLeaveSharedAccount(account)
                                        "
                                    >
                                        {{ t('accounts.page.leaveAction') }}
                                    </Button>
                                </div>
                            </article>
                        </div>

                        <p
                            v-else
                            class="mt-4 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ t('accounts.page.sharedEmpty') }}
                        </p>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                        <section class="space-y-4">
                            <div
                                class="flex flex-col gap-3 rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/75"
                            >
                                <div class="space-y-1">
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ t('accounts.page.listTitle') }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'accounts.page.listSummary',
                                                filteredSummary,
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full"
                                    >
                                        {{ t('accounts.page.mobileCards') }}
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full"
                                    >
                                        {{ t('accounts.page.desktopTable') }}
                                    </Badge>
                                </div>
                            </div>

                            <AccountsList
                                :accounts="filteredAccounts"
                                :selected-account-uuid="selectedAccountUuid"
                                :empty-message="emptyMessage"
                                @select="selectAccount"
                                @edit="openEditAccount"
                                @toggle-active="toggleAccount"
                                @delete="requestDelete"
                            />
                        </section>

                        <aside class="min-w-0 space-y-4">
                            <section
                                class="rounded-[1.4rem] border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] sm:rounded-[1.75rem] sm:p-5 dark:border-slate-800 dark:bg-slate-950/80"
                            >
                                <div v-if="selectedAccount" class="space-y-5">
                                    <div
                                        class="flex items-center gap-2.5 sm:gap-3"
                                    >
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[1rem] bg-slate-100 text-slate-700 sm:h-11 sm:w-11 sm:rounded-2xl dark:bg-slate-900 dark:text-slate-200"
                                        >
                                            <component
                                                :is="
                                                    selectedAccount.account_type
                                                        ?.code === 'credit_card'
                                                        ? CreditCard
                                                        : Landmark
                                                "
                                                class="h-4 w-4 sm:h-5 sm:w-5"
                                            />
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-950 sm:text-base dark:text-slate-50"
                                            >
                                                {{ selectedAccount.name }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    selectedAccount.account_type
                                                        ?.name ??
                                                    t(
                                                        'accounts.list.notConfigured',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-wrap gap-1.5 sm:gap-2"
                                    >
                                        <Badge
                                            variant="secondary"
                                            class="rounded-full px-2.5 py-0.5 text-[11px] sm:px-3 sm:py-1 sm:text-xs"
                                        >
                                            {{
                                                selectedAccount.balance_nature_label ??
                                                t('accounts.list.notConfigured')
                                            }}
                                        </Badge>
                                        <Badge
                                            class="rounded-full px-2.5 py-0.5 text-[11px] sm:px-3 sm:py-1 sm:text-xs"
                                            :class="
                                                selectedAccount.is_active
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                            "
                                        >
                                            {{
                                                selectedAccount.is_active
                                                    ? t('accounts.list.active')
                                                    : t(
                                                          'accounts.list.inactive',
                                                      )
                                            }}
                                        </Badge>
                                    </div>

                                    <div
                                        class="space-y-2.5 text-sm sm:space-y-3"
                                    >
                                        <div
                                            class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >{{
                                                    t('accounts.detail.bank')
                                                }}</span
                                            >
                                            <span
                                                class="text-left font-medium break-words text-slate-950 sm:text-right dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.bank_name ??
                                                    t(
                                                        'accounts.list.notConfigured',
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >{{
                                                    t(
                                                        'accounts.detail.currency',
                                                    )
                                                }}</span
                                            >
                                            <span
                                                class="font-medium break-all text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.currency_label ??
                                                    formatCurrencyLabel(
                                                        selectedAccount.currency,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >{{
                                                    t(
                                                        'accounts.detail.openingBalance',
                                                    )
                                                }}</span
                                            >
                                            <span
                                                class="text-left font-medium break-words text-slate-950 sm:text-right dark:text-slate-50"
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
                                            class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >{{
                                                    t(
                                                        'accounts.detail.currentBalance',
                                                    )
                                                }}</span
                                            >
                                            <span
                                                class="max-w-full rounded-2xl px-3 py-1.5 text-left text-base font-bold tracking-tight sm:text-right sm:text-lg"
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
                                            class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >{{
                                                    t(
                                                        'accounts.detail.negativeBalance',
                                                    )
                                                }}</span
                                            >
                                            <span
                                                class="text-left font-medium break-words text-slate-950 sm:text-right dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.account_type
                                                        ?.code === 'credit_card'
                                                        ? t(
                                                              'accounts.detail.negativeBalanceManagedByCard',
                                                          )
                                                        : selectedAccount.allow_negative_balance
                                                          ? t(
                                                                'accounts.detail.negativeBalanceAllowed',
                                                            )
                                                          : t(
                                                                'accounts.detail.negativeBalanceNotAllowed',
                                                            )
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="
                                                selectedAccount.account_number_masked
                                            "
                                            class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >{{
                                                    t('accounts.detail.number')
                                                }}</span
                                            >
                                            <span
                                                class="font-medium break-all text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    selectedAccount.account_number_masked
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="selectedAccount.iban"
                                            class="flex flex-col gap-1.5 sm:flex-row sm:items-start sm:justify-between sm:gap-3"
                                        >
                                            <span
                                                class="text-slate-500 dark:text-slate-400"
                                                >{{
                                                    t('accounts.detail.iban')
                                                }}</span
                                            >
                                            <span
                                                class="text-left font-medium break-all text-slate-950 sm:text-right dark:text-slate-50"
                                            >
                                                {{ selectedAccount.iban }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            selectedAccount.account_type
                                                ?.code === 'credit_card' &&
                                            selectedAccount.credit_card_settings
                                        "
                                        class="rounded-[1.25rem] border border-slate-200/80 bg-slate-50/90 p-3.5 sm:rounded-[1.5rem] sm:p-4 dark:border-slate-800 dark:bg-slate-900/80"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                t(
                                                    'accounts.detail.creditCardSettings',
                                                )
                                            }}
                                        </p>
                                        <div
                                            class="mt-3 space-y-2.5 text-sm sm:mt-4 sm:space-y-3"
                                        >
                                            <div
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >{{
                                                        t(
                                                            'accounts.detail.creditLimit',
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="font-medium break-words text-slate-950 dark:text-slate-50"
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
                                                            : t(
                                                                  'accounts.list.notSet',
                                                              )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >{{
                                                        t(
                                                            'accounts.detail.linkedPaymentAccount',
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="text-left font-medium break-words text-slate-950 sm:text-right dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .linked_payment_account
                                                            ?.name ??
                                                        t(
                                                            'accounts.detail.linkedPaymentAccountNone',
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >{{
                                                        t(
                                                            'accounts.detail.statementClosing',
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="font-medium break-words text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .credit_card_settings
                                                            .statement_closing_day ??
                                                        t(
                                                            'accounts.list.notSet',
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >{{
                                                        t(
                                                            'accounts.detail.paymentDay',
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="font-medium break-words text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .credit_card_settings
                                                            .payment_day ??
                                                        t(
                                                            'accounts.list.notSet',
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span
                                                    class="text-slate-500 dark:text-slate-400"
                                                    >{{
                                                        t(
                                                            'accounts.detail.autoPay',
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="font-medium break-words text-slate-950 dark:text-slate-50"
                                                >
                                                    {{
                                                        selectedAccount
                                                            .credit_card_settings
                                                            .auto_pay
                                                            ? t(
                                                                  'accounts.detail.yes',
                                                              )
                                                            : t(
                                                                  'accounts.detail.no',
                                                              )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[1.25rem] border border-slate-200/80 bg-slate-50/85 p-3.5 sm:rounded-[1.5rem] sm:p-4 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                t(
                                                    'accounts.detail.usageAndLinks',
                                                )
                                            }}
                                        </p>
                                        <div
                                            class="mt-3 space-y-2.5 text-sm text-slate-600 sm:mt-4 sm:space-y-3 dark:text-slate-300"
                                        >
                                            <div
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span>{{
                                                    t(
                                                        'accounts.detail.transactions',
                                                    )
                                                }}</span>
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
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span>{{
                                                    t('accounts.detail.imports')
                                                }}</span>
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
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span>{{
                                                    t(
                                                        'accounts.detail.recurring',
                                                    )
                                                }}</span>
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
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span>{{
                                                    t(
                                                        'accounts.detail.scheduled',
                                                    )
                                                }}</span>
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
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span>{{
                                                    t(
                                                        'accounts.detail.balanceSnapshots',
                                                    )
                                                }}</span>
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
                                                class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                                            >
                                                <span>{{
                                                    t(
                                                        'accounts.detail.linkedCards',
                                                    )
                                                }}</span>
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
                                        class="rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-3.5 text-sm leading-6 text-slate-600 sm:rounded-[1.5rem] sm:p-4 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300"
                                    >
                                        <p
                                            class="mb-2 font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ t('accounts.detail.notes') }}
                                        </p>
                                        <p>{{ selectedAccount.notes }}</p>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/80 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                                >
                                    {{ t('accounts.page.detailEmpty') }}
                                </div>
                            </section>
                        </aside>
                    </div>

                    <AccountSharingPanel
                        :accounts="shareableAccounts"
                        :account="selectedSharingAccount"
                        :selected-account-uuid="selectedSharingAccountUuid"
                        @update:selected-account-uuid="selectSharingAccount"
                    />
                </div>
            </section>

            <AccountFormSheet
                v-model:open="formOpen"
                :account="editingAccount"
                :banks="accountOptions.banks"
                :opening-balance-date-options="
                    accountOptions.opening_balance_date
                "
                :account-types="accountOptions.account_types"
                :currencies="accountOptions.currencies"
                :linked-payment-account-options="
                    accountOptions.linked_payment_accounts
                "
                :default-account-uuid="accountOptions.default_account_uuid"
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
                            {{ t('accounts.deleteDialog.title') }}
                        </DialogTitle>
                        <DialogDescription class="leading-6">
                            <template v-if="deletingAccount?.is_deletable">
                                {{
                                    t('accounts.deleteDialog.confirm', {
                                        name: deletingAccount?.name,
                                    })
                                }}
                            </template>
                            <template v-else>
                                {{
                                    t('accounts.deleteDialog.blocked', {
                                        name: deletingAccount?.name,
                                    })
                                }}
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deleteReasons.length > 0"
                        class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        <p class="font-medium">
                            {{ t('accounts.deleteDialog.blockedReasons') }}
                        </p>
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
                            {{ t('accounts.deleteDialog.close') }}
                        </Button>
                        <Button
                            v-if="deletingAccount?.is_deletable"
                            type="button"
                            variant="destructive"
                            class="rounded-xl"
                            @click="confirmDelete"
                        >
                            {{ t('accounts.deleteDialog.delete') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="leavingSharedAccount !== null"
                @update:open="!$event ? closeLeaveSharedAccountDialog() : null"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            t('accounts.page.leaveTitle')
                        }}</DialogTitle>
                        <DialogDescription class="leading-6">
                            {{ t('accounts.page.leaveConfirm') }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2 sm:justify-end">
                        <Button
                            variant="outline"
                            @click="closeLeaveSharedAccountDialog"
                        >
                            {{ t('accounts.page.leaveCancel') }}
                        </Button>
                        <Button
                            variant="destructive"
                            @click="confirmLeaveSharedAccount"
                        >
                            {{ t('accounts.page.leaveSubmit') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
