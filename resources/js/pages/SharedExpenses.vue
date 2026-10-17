<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ArrowUpRight, UsersRound } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { show as commonCounterpartyShow } from '@/actions/App/Http/Controllers/CounterpartyPaymentController';
import {
    preparePayments,
    resolveReference,
} from '@/actions/App/Http/Controllers/SharedExpenseController';
import { destroyLedger } from '@/actions/App/Http/Controllers/SharedExpenseController';
import AppDatePicker from '@/components/AppDatePicker.vue';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import type { CommonRelation } from '@/components/shared-expenses/CommonCounterpartyLinks.vue';
import SharedReviewActions from '@/components/shared-expenses/SharedReviewActions.vue';
import MobileQuickChoices from '@/components/transactions/MobileQuickChoices.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
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
import { useForm } from '@/composables/useNotifiedForm';
import { useUiCopy } from '@/i18n/ui-copy';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as creditsDebtsIndex } from '@/routes/credits-debts';
import { index as sharedExpensesIndex } from '@/routes/shared-expenses';
import { store as storeSettlement } from '@/routes/shared-expenses/settlements';
import {
    destroy as destroySettlement,
    update as updateSettlement,
} from '@/routes/shared-expenses/settlements';
import type { TransactionsNavigation } from '@/types';

const cancelLedgerEntry = ref<Entry | null>(null);
function cancelLedger(entry: Entry): void {
    cancelLedgerEntry.value = entry;
}
function confirmCancelLedger(): void {
    if (!cancelLedgerEntry.value) {
        return;
    }

    router.delete(destroyLedger.url(cancelLedgerEntry.value.uuid), {
        preserveScroll: true,
    });
}
const ui = useUiCopy();

type Entry = {
    source_kind?: 'transaction' | 'ledger';
    review_reason?: string | null;
    display_state:
        | 'receivable'
        | 'payable'
        | 'partial'
        | 'settled'
        | 'review'
        | 'repayment'
        | 'cancelled';
    remaining_cents: number | null;
    original_share_cents: number;
    repaid_cents: number;
    compensated_cents: number;
    original_effect_cents?: number;
    refund_date?: string | null;
    is_cancelled?: boolean;
    can_cancel_payment?: boolean;
    uuid: string;
    date: string | null;
    title: string | null;
    counterparty: string | null;
    counterparty_uuid?: string | null;
    category: string | null;
    reference: string | null;
    origin: string;
    amount: number;
    currency: string;
    status: string;
    origin_url?: string | null;
    origin_label?: string | null;
    paid_by?: string;
    my_share_cents?: number;
    counterparty_share_cents?: number;
    effect_cents?: number;
    common_settlement?: {
        relation_uuid: string;
        paid: string;
        compensated: string;
        remaining: string;
        economic_group: boolean;
    } | null;
    is_settlement?: boolean;
    settlement?: {
        account_uuid: string | null;
        category_uuid: string | null;
        reference: string | null;
        notes: string | null;
    };
};
type AccountOption = { uuid: string; name: string; currency: string };
type CategoryOption = {
    uuid: string;
    name: string;
    icon?: string | null;
    color?: string | null;
    id?: number;
    parent_id?: number | null;
};
type ReferenceOption = {
    value: string;
    name?: string;
    label?: string;
    logo_url?: string | null;
};
const props = defineProps<{
    commonRelations: CommonRelation[];
    selectedEntryUuid?: string | null;
    filters: {
        month: number;
        year: number;
        counterparty: string | null;
        search: string;
        effect: string;
    };
    counterparties: { uuid: string; name: string }[];
    visibleCounterparties: { uuid: string; name: string }[];
    historicalCounterparties: { uuid: string; name: string }[];
    entries: Entry[];
    reviewEntries: Entry[];
    blockedEntries: Entry[];
    canPreparePayments: boolean;
    referenceOptions: { uuid: string; name: string }[];
    referenceReviewPeople: string[];
    relationshipOrigins: Record<
        string,
        ('manual_credit' | 'manual_debit' | 'shared' | 'payments')[]
    >;
    balances: Record<string, number>;
    counterpartyBalances: Record<
        string,
        {
            currency: string;
            manual_cents: number;
            shared_cents: number;
            total_cents: number;
            unallocated_cents?: number;
        }[]
    >;
    counterpartyEntryCounts: Record<string, number>;
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    settlementOptions: {
        accounts: AccountOption[];
        categories: Record<string, CategoryOption[]>;
        references?: Record<string, ReferenceOption[]>;
    };
}>();
watch(
    () => props.selectedEntryUuid,
    async (uuid) => {
        if (!uuid) {
            return;
        }

        await nextTick();
        document
            .getElementById(`shared-entry-${uuid}`)
            ?.scrollIntoView({ block: 'center' });
    },
    { immediate: true, flush: 'post' },
);
const page = usePage();
const isMobile = useMediaQuery('(max-width: 767px)');
const newReferenceOpen = ref(false);
const newReferenceName = ref('');
const newReferenceWebsite = ref('');
const creatingReference = ref(false);
const localReferences = ref<Record<string, ReferenceOption[]>>({});
const { locale } = useI18n();
const search = ref(props.filters.search);
const counterparty = ref(props.filters.counterparty ?? '');
const statusTabs = [
    { value: 'pending', label: 'Da sistemare' },
    { value: 'settled', label: 'Regolate' },
    { value: 'all', label: 'Tutte' },
] as const;
const effect = ref(
    ['pending', 'settled', 'all'].includes(props.filters.effect)
        ? props.filters.effect
        : props.filters.effect === ''
          ? 'all'
          : props.filters.effect,
);
const navigation = computed(
    () => page.props.transactionsNavigation as TransactionsNavigation | null,
);
const availableYears = computed(
    () => navigation.value?.context.available_years ?? [props.filters.year],
);
const selectedYear = computed(() => String(props.filters.year));
const selectedMonth = computed(() => String(props.filters.month));
let timeout: ReturnType<typeof setTimeout> | undefined;
function visitFilters(): void {
    clearTimeout(timeout);
    router.get(
        sharedExpensesIndex.url(),
        {
            search: search.value || undefined,
            month: props.filters.month,
            year: props.filters.year,
            counterparty: counterparty.value || undefined,
            effect: effect.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
        },
    );
}
watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(visitFilters, 250);
});
watch([counterparty, effect], visitFilters);
onBeforeUnmount(() => clearTimeout(timeout));
function monthLabel(month: number): string {
    return new Intl.DateTimeFormat(locale.value, { month: 'long' }).format(
        new Date(Date.UTC(props.filters.year, month - 1, 1)),
    );
}
function updatePeriod(year: number, month: number): void {
    if (year === props.filters.year && month === props.filters.month) {
        return;
    }

    clearTimeout(timeout);
    router.get(
        sharedExpensesIndex.url(),
        {
            search: search.value || undefined,
            year,
            month,
            counterparty: counterparty.value || undefined,
            effect: effect.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
function updateYear(value: string): void {
    const year = Number(value);

    if (Number.isInteger(year)) {
        updatePeriod(year, props.filters.month);
    }
}
function updateMonth(value: string): void {
    const month = Number(value);

    if (Number.isInteger(month) && month >= 1 && month <= 12) {
        updatePeriod(props.filters.year, month);
    }
}
const money = (entry: Entry) =>
    new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: entry.currency,
    }).format(entry.amount);
const cents = (value: number, currency = 'EUR') =>
    new Intl.NumberFormat(locale.value, { style: 'currency', currency }).format(
        value / 100,
    );
const settlementCounterparty = ref<{
    uuid: string;
    name: string;
    balance: number;
} | null>(null);
const editingSettlement = ref<Entry | null>(null);
const settlementForm = useForm({
    amount: '',
    settled_at: new Date().toISOString().slice(0, 10),
    account_uuid: '',
    category_uuid: '',
    reference: '',
    notes: '',
});
const receivableCents = computed(() =>
    Object.values(props.balances)
        .filter((balance) => balance > 0)
        .reduce((total, balance) => total + balance, 0),
);
const payableCents = computed(() =>
    Object.values(props.balances)
        .filter((balance) => balance < 0)
        .reduce((total, balance) => total + Math.abs(balance), 0),
);
const netCents = computed(() => receivableCents.value - payableCents.value);
const receivablePeople = computed(
    () => Object.values(props.balances).filter((balance) => balance > 0).length,
);
const payablePeople = computed(
    () => Object.values(props.balances).filter((balance) => balance < 0).length,
);
const entryDate = (entry: Entry) =>
    entry.date
        ? new Intl.DateTimeFormat(locale.value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(new Date(`${entry.date}T12:00:00`))
        : ui('Data non disponibile');
const entriesByDay = computed(() => {
    const groups = new Map<string, { label: string; entries: Entry[] }>();

    for (const entry of props.entries) {
        const key = entry.date ?? 'unknown-date';
        const group = groups.get(key) ?? {
            label: entryDate(entry).toLocaleUpperCase(locale.value),
            entries: [],
        };

        group.entries.push(entry);
        groups.set(key, group);
    }

    return [...groups.entries()].map(([key, group]) => ({ key, ...group }));
});
const stateLabel = (entry: Entry): string =>
    ui(
        (
            {
                receivable: 'Da ricevere',
                payable: 'Da pagare',
                partial: 'Parzialmente regolata',
                settled: 'Regolata',
                review: 'Verifica necessaria',
                repayment: 'Rimborso registrato',
                cancelled: 'Annullata',
            } as const
        )[entry.display_state],
    );
const reviewingReference = ref<Entry | null>(null);
const referenceConfirmation = useForm({
    reference_uuid: '',
    confirm_reference: true,
});
function openReferenceReview(entry: Entry): void {
    referenceConfirmation.reset();
    referenceConfirmation.clearErrors();
    referenceConfirmation.reference_uuid = entry.counterparty_uuid ?? '';
    reviewingReference.value = entry;
}
function saveReferenceReview(): void {
    const entry = reviewingReference.value;

    if (!entry?.source_kind) {
        return;
    }

    referenceConfirmation.patch(
        resolveReference.url({ kind: entry.source_kind, uuid: entry.uuid }),
        {
            preserveScroll: true,
            onSuccess: () => {
                reviewingReference.value = null;
            },
        },
    );
}
const preparingPayments = ref(false);
function linkExistingPayments(entry: Entry): void {
    if (!entry.counterparty_uuid || preparingPayments.value) {
        return;
    }

    preparingPayments.value = true;
    router.post(
        preparePayments.url(entry.counterparty_uuid),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                preparingPayments.value = false;
            },
        },
    );
}
function relationshipOriginLabels(uuid: string): string[] {
    return (props.relationshipOrigins[uuid] ?? []).map((origin) =>
        ui(
            (
                {
                    manual_credit: 'Credito manuale',
                    manual_debit: 'Debito manuale',
                    shared: 'Spese condivise',
                    payments: 'Pagamenti e rimborsi',
                } as const
            )[origin],
        ),
    );
}
function subjectBalances(uuid: string) {
    return (props.counterpartyBalances[uuid] ?? []).filter(
        (balance) =>
            balance.currency === 'EUR' ||
            balance.shared_cents !== 0 ||
            balance.manual_cents !== 0 ||
            balance.total_cents !== 0,
    );
}
function completeSituationUrl(uuid: string, currency = 'EUR'): string {
    const relation = props.commonRelations.find(
        (item) => item.counterparty_uuid === uuid && item.currency === currency,
    );

    return relation
        ? commonCounterpartyShow.url(relation.uuid)
        : creditsDebtsIndex.url({
              query: {
                  reference_uuid: uuid,
                  month: 'all',
                  type: 'all',
                  status: 'all',
              },
          });
}
function balanceLabel(name: string, value: number, currency: string): string {
    return value === 0
        ? ui('Saldo complessivo: {amount}', { amount: cents(0, currency) })
        : value > 0
          ? ui('Ti deve {amount}', { amount: cents(value, currency) })
          : ui('Devi a {name} {amount}', {
                name,
                amount: cents(-value, currency),
            });
}
function residualLabel(entry: Entry): string {
    if (entry.display_state === 'review') {
        return ui('Verifica necessaria');
    }

    if (entry.display_state === 'settled') {
        return ui('Regolata');
    }

    if (entry.display_state === 'cancelled') {
        return ui('Annullata');
    }

    if (entry.is_settlement) {
        return entry.title ?? ui('Rimborso registrato');
    }

    return (entry.original_effect_cents ?? entry.effect_cents ?? 0) > 0
        ? ui('{name} ti deve {amount}', {
              name: entry.counterparty ?? '',
              amount: cents(entry.remaining_cents ?? 0, entry.currency),
          })
        : ui('Devi a {name} {amount}', {
              name: entry.counterparty ?? '',
              amount: cents(entry.remaining_cents ?? 0, entry.currency),
          });
}
function refundDate(entry: Entry): string {
    return entry.refund_date
        ? new Intl.DateTimeFormat(locale.value).format(
              new Date(`${entry.refund_date}T12:00:00`),
          )
        : '';
}
const accountCategories = computed(
    () => props.settlementOptions.categories[settlementForm.account_uuid] ?? [],
);
const categoryChoices = computed(() =>
    accountCategories.value.map((item) => ({
        ...item,
        value: item.uuid,
        label: item.name,
    })),
);
const categoryChildren = computed(() => {
    const selected = accountCategories.value.find(
        (item) => item.uuid === settlementForm.category_uuid,
    );

    if (!selected) {
        return [];
    }

    const children = categoryChoices.value.filter(
        (item) => item.parent_id === selected.id,
    );

    return children.length
        ? children
        : categoryChoices.value.filter(
              (item) =>
                  selected.parent_id != null &&
                  item.parent_id === selected.parent_id,
          );
});
const referenceCatalog = computed(() => [
    ...(props.settlementOptions.references?.[settlementForm.account_uuid] ??
        []),
    ...(localReferences.value[settlementForm.account_uuid] ?? []),
]);
const referenceChoices = computed(() =>
    referenceCatalog.value.map((item) => ({
        ...item,
        value: item.name ?? item.label ?? '',
        label: item.name ?? item.label ?? '',
    })),
);
const quickReferences = computed(() => {
    const counts = new Map<string, number>();
    props.entries.forEach((entry) => {
        if (entry.reference) {
            counts.set(entry.reference, (counts.get(entry.reference) ?? 0) + 1);
        }
    });
    const ranked = [...referenceChoices.value].sort(
        (a, b) => (counts.get(b.value) ?? 0) - (counts.get(a.value) ?? 0),
    );
    const choices = ranked.slice(0, 8);
    const selected = ranked.find(
        (item) => item.value === settlementForm.reference,
    );

    if (selected && !choices.includes(selected)) {
        choices.unshift(selected);
    }

    return choices;
});
const settlementAccounts = computed(() =>
    props.settlementOptions.accounts.filter(
        (account) => account.currency === 'EUR',
    ),
);
const accountChoices = computed(() =>
    settlementAccounts.value.map((item) => ({
        value: item.uuid,
        label: `${item.name} (${item.currency})`,
    })),
);
function createSettlementReference(): void {
    if (!newReferenceName.value.trim() || !settlementForm.category_uuid) {
        settlementForm.setError(
            'reference',
            ui('Seleziona una categoria e inserisci il nome.'),
        );

        return;
    }

    settlementForm.reference = newReferenceName.value.trim();
    newReferenceOpen.value = false;
    newReferenceName.value = '';
    newReferenceWebsite.value = '';
    settlementForm.clearErrors('reference');
}

function openSettlement(counterpartyItem: {
    uuid: string;
    name: string;
}): void {
    const common = props.commonRelations.find(
        (r) =>
            r.counterparty_uuid === counterpartyItem.uuid &&
            r.active &&
            r.currency === 'EUR',
    );

    if (common) {
        router.visit(
            commonCounterpartyShow.url(common.uuid, {
                query: { scope: 'shared' },
            }),
        );

        return;
    }

    const balance = props.balances[counterpartyItem.uuid];
    settlementCounterparty.value = { ...counterpartyItem, balance };
    settlementForm.reset();
    settlementForm.amount = (Math.abs(balance) / 100).toFixed(2);
    settlementForm.settled_at = new Date().toISOString().slice(0, 10);
    settlementForm.account_uuid = settlementAccounts.value[0]?.uuid ?? '';
    settlementForm.clearErrors();
}
function submitSettlement(): void {
    if (!settlementCounterparty.value) {
        return;
    }

    const route = editingSettlement.value
        ? updateSettlement(editingSettlement.value.uuid).url
        : storeSettlement(settlementCounterparty.value.uuid).url;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            settlementCounterparty.value = null;
            editingSettlement.value = null;
        },
    };

    if (editingSettlement.value) {
        settlementForm.patch(route, options);
    } else {
        settlementForm.post(route, options);
    }
}
function editSettlement(entry: Entry): void {
    const common = props.commonRelations.find(
        (r) =>
            r.counterparty_uuid === entry.counterparty_uuid &&
            r.active &&
            r.currency === entry.currency,
    );

    if (common) {
        router.visit(
            commonCounterpartyShow.url(common.uuid, {
                query: { scope: 'shared' },
            }),
        );

        return;
    }

    if (!entry.settlement || !entry.counterparty_uuid || !entry.counterparty) {
        return;
    }

    editingSettlement.value = entry;
    settlementCounterparty.value = {
        uuid: entry.counterparty_uuid,
        name: entry.counterparty,
        balance: props.balances[entry.counterparty_uuid] ?? 0,
    };
    settlementForm.amount = entry.amount.toFixed(2);
    settlementForm.settled_at = entry.date ?? '';
    settlementForm.account_uuid = entry.settlement.account_uuid ?? '';
    settlementForm.category_uuid = entry.settlement.category_uuid ?? '';
    settlementForm.reference = entry.settlement.reference ?? '';
    settlementForm.notes = entry.settlement.notes ?? '';
    settlementForm.clearErrors();
}
function deleteSettlement(entry: Entry): void {
    router.delete(destroySettlement(entry.uuid).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="ui('Spese condivise')" />
    <AppLayout
        :breadcrumbs="[
            { title: ui('Spese condivise'), href: sharedExpensesIndex.url() },
        ]"
    >
        <div
            class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-28 md:p-6"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ ui('Spese condivise') }}
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    {{
                        ui(
                            'Quanto devi ricevere o pagare per le spese condivise e la situazione completa con ogni persona.',
                        )
                    }}
                </p>
            </div>

            <section class="grid gap-3 sm:grid-cols-3">
                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/25 dark:bg-emerald-500/10"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-emerald-800 uppercase dark:text-emerald-200"
                    >
                        {{ ui('Da ricevere') }} · {{ ui('Spese condivise') }} ·
                        EUR
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ cents(receivableCents) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ ui('da') }} {{ receivablePeople }}
                        {{
                            receivablePeople === 1
                                ? ui('persona')
                                : ui('persone')
                        }}
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 dark:border-rose-500/25 dark:bg-rose-500/10"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-rose-800 uppercase dark:text-rose-200"
                    >
                        {{ ui('Da pagare') }} · {{ ui('Spese condivise') }} ·
                        EUR
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ cents(payableCents) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ ui('a') }} {{ payablePeople }}
                        {{
                            payablePeople === 1 ? ui('persona') : ui('persone')
                        }}
                    </p>
                </div>
                <div class="rounded-2xl border bg-card p-4">
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ ui('Saldo spese condivise') }} · EUR
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ netCents > 0 ? '+' : netCents < 0 ? '−' : ''
                        }}{{ cents(Math.abs(netCents)) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            netCents === 0
                                ? ui('Saldo condiviso nullo')
                                : netCents > 0
                                  ? ui('Credito da spese condivise')
                                  : ui('Debito da spese condivise')
                        }}
                    </p>
                </div>
            </section>

            <p class="text-sm text-muted-foreground">
                {{
                    ui(
                        'Saldi attuali, indipendenti dal periodo del registro. Il riepilogo riguarda solo le spese condivise.',
                    )
                }}
            </p>

            <h2
                v-if="visibleCounterparties.length"
                class="text-xl font-semibold"
            >
                {{ ui('Rapporti con le persone') }}
            </h2>
            <section
                v-if="visibleCounterparties.length"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="item in visibleCounterparties"
                    :key="item.uuid"
                    class="flex min-w-0 flex-col rounded-2xl border bg-card p-5 text-sm shadow-sm transition-shadow hover:shadow-md"
                    :class="
                        subjectBalances(item.uuid).length === 1
                            ? subjectBalances(item.uuid)[0].total_cents > 0
                                ? 'border-t-4 border-t-emerald-500/50'
                                : subjectBalances(item.uuid)[0].total_cents < 0
                                  ? 'border-t-4 border-t-rose-500/50'
                                  : 'border-t-4 border-t-border'
                            : ''
                    "
                >
                    <h2
                        class="flex items-center gap-2 text-lg font-semibold break-words"
                    >
                        <UsersRound
                            class="size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {{ item.name }}
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ ui('Origine del rapporto') }}:
                        {{ relationshipOriginLabels(item.uuid).join(' · ') }}
                    </p>
                    <p
                        v-if="
                            !relationshipOrigins[item.uuid]?.includes('shared')
                        "
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ ui('Nessuna spesa condivisa registrata') }}
                    </p>
                    <div
                        v-for="balance in subjectBalances(item.uuid)"
                        :key="balance.currency"
                        class="mt-3 space-y-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ ui('Saldo complessivo con la persona') }} ·
                            {{ balance.currency }}
                        </p>
                        <p
                            class="text-2xl font-bold tracking-tight tabular-nums"
                            :class="
                                balance.total_cents < 0
                                    ? 'text-rose-700 dark:text-rose-300'
                                    : balance.total_cents > 0
                                      ? 'text-emerald-700 dark:text-emerald-300'
                                      : 'text-foreground'
                            "
                        >
                            {{
                                balanceLabel(
                                    item.name,
                                    balance.total_cents,
                                    balance.currency,
                                )
                            }}
                        </p>
                        <dl class="space-y-2 rounded-xl bg-muted/40 p-3">
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt>{{ ui('Spese condivise') }}</dt>
                                <dd class="font-semibold tabular-nums">
                                    {{ balance.shared_cents > 0 ? '+' : ''
                                    }}{{
                                        cents(
                                            balance.shared_cents,
                                            balance.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt>{{ ui('Crediti/debiti manuali') }}</dt>
                                <dd class="font-semibold tabular-nums">
                                    {{ balance.manual_cents > 0 ? '+' : ''
                                    }}{{
                                        cents(
                                            balance.manual_cents,
                                            balance.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="balance.unallocated_cents"
                                class="flex flex-wrap justify-between gap-2"
                            >
                                <dt>{{ ui('Pagamenti da collegare') }}</dt>
                                <dd class="font-semibold tabular-nums">
                                    {{
                                        cents(
                                            balance.unallocated_cents,
                                            balance.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="
                                relationshipOrigins[item.uuid]?.includes(
                                    'payments',
                                )
                            "
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                ui(
                                    'I pagamenti collegati sono già inclusi nei residui. Le somme da collegare sono indicate separatamente.',
                                )
                            }}
                        </p>
                        <Link
                            :href="
                                completeSituationUrl(
                                    item.uuid,
                                    balance.currency,
                                )
                            "
                            class="inline-flex min-h-11 w-full items-center justify-between gap-2 rounded-xl border bg-background px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                            :aria-label="
                                ui('Situazione con {name}', { name: item.name })
                            "
                            >{{ ui('Apri situazione completa')
                            }}<ArrowUpRight
                                class="size-4 shrink-0"
                                aria-hidden="true"
                        /></Link>
                    </div>
                    <p
                        v-if="referenceReviewPeople.includes(item.uuid)"
                        class="mt-3 text-sm text-amber-800 dark:text-amber-200"
                    >
                        {{
                            ui(
                                'I crediti/debiti precedenti non confermati non sono inclusi in questo saldo.',
                            )
                        }}
                    </p>
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ counterpartyEntryCounts[item.uuid] ?? 0 }}
                        {{ ui('movimenti condivisi nel periodo') }}
                    </p>
                    <Button
                        v-if="
                            !commonRelations.some(
                                (r) =>
                                    r.active &&
                                    !r.can_manage &&
                                    r.counterparty_uuid === item.uuid &&
                                    r.currency === 'EUR',
                            ) &&
                            balances[item.uuid] !== 0 &&
                            counterparties.some(
                                (counterparty) =>
                                    counterparty.uuid === item.uuid,
                            )
                        "
                        variant="outline"
                        size="sm"
                        class="mt-4 w-full sm:w-auto"
                        @click="openSettlement(item)"
                        >{{ ui('Registra rimborso spese condivise') }}</Button
                    >
                </div>
            </section>
            <div
                class="grid gap-3 rounded-2xl border bg-card p-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <div class="grid gap-1.5">
                    <Label for="shared-search">{{ ui('Cerca') }}</Label
                    ><Input
                        id="shared-search"
                        v-model="search"
                        :placeholder="ui('Titolo, categoria, riferimento…')"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="shared-counterparty">{{
                        ui('Condivisa con')
                    }}</Label
                    ><SearchableSelect
                        id="shared-counterparty"
                        v-model="counterparty"
                        :options="[
                            { value: '', label: ui('Tutte le persone') },
                            ...historicalCounterparties.map((item) => ({
                                value: item.uuid,
                                label: item.name,
                            })),
                        ]"
                        :placeholder="ui('Tutte le persone')"
                        :search-placeholder="ui('Cerca persona')"
                        clearable
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>{{ ui('Anno') }}</Label>
                    <Select
                        :model-value="selectedYear"
                        @update:model-value="updateYear(String($event ?? ''))"
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-border/80 bg-background/80 hover:bg-background"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="year in availableYears"
                                :key="year"
                                :value="String(year)"
                            >
                                {{ year }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>{{ ui('Mese') }}</Label>
                    <Select
                        :model-value="selectedMonth"
                        @update:model-value="updateMonth(String($event ?? ''))"
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-border/80 bg-background/80 hover:bg-background"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="month in 12"
                                :key="month"
                                :value="String(month)"
                            >
                                {{ monthLabel(month) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div
                class="grid grid-cols-3 gap-2 rounded-2xl bg-muted p-1"
                role="group"
                :aria-label="ui('Situazione dei movimenti')"
            >
                <Button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    :variant="effect === tab.value ? 'default' : 'ghost'"
                    :aria-pressed="effect === tab.value"
                    class="min-h-11"
                    @click="effect = tab.value"
                    >{{ ui(tab.label) }}</Button
                >
            </div>
            <InputError :message="page.props.errors.payments" />
            <section
                v-if="reviewEntries.length"
                class="rounded-2xl border border-amber-200 bg-card p-4 dark:border-amber-500/30"
            >
                <h2 class="font-semibold">{{ ui('Verifica necessaria') }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        ui(
                            'Per questi movimenti il residuo non è attribuibile con certezza. Restano visibili senza essere indicati come regolati o da pagare.',
                        )
                    }}
                </p>
                <div
                    v-for="entry in reviewEntries"
                    :key="entry.uuid"
                    class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 text-sm"
                >
                    <div>
                        <p class="font-medium">
                            {{ entry.title }} ·
                            {{
                                entry.counterparty ??
                                ui('Persona da verificare')
                            }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ entryDate(entry) }} · {{ money(entry) }}
                        </p>
                    </div>
                    <SharedReviewActions
                        :reason="entry.review_reason"
                        :origin-url="entry.origin_url"
                        :can-prepare-payments="canPreparePayments"
                        @reference="openReferenceReview(entry)"
                        @payments="linkExistingPayments(entry)"
                    />
                </div>
            </section>
            <section
                v-if="blockedEntries.length"
                class="space-y-3 rounded-2xl border bg-card p-4"
            >
                <h2 class="font-semibold">{{ ui('Controlli protetti') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        ui(
                            'Questi movimenti restano visibili, ma non sono azioni da completare con una semplice conferma del riferimento.',
                        )
                    }}
                </p>
                <div
                    v-for="entry in blockedEntries"
                    :key="entry.uuid"
                    class="space-y-2 rounded-xl border p-3"
                >
                    <p class="font-medium">
                        {{ entry.title }} · {{ entry.counterparty }} ·
                        {{ money(entry) }}
                    </p>
                    <SharedReviewActions
                        :reason="entry.review_reason"
                        :origin-url="entry.origin_url"
                        :can-prepare-payments="false"
                    />
                </div>
            </section>
            <section class="overflow-hidden rounded-2xl border bg-card">
                <div class="border-b px-4 py-3">
                    <h2 class="font-semibold">
                        {{ ui('Movimenti condivisi') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            ui(
                                'Solo spese condivise e relativi rimborsi. Gli altri crediti/debiti sono nella situazione completa della persona.',
                            )
                        }}
                    </p>
                </div>
                <div
                    v-if="entries.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    {{
                        ui(
                            'Nessun movimento condiviso per i filtri selezionati.',
                        )
                    }}
                </div>
                <div v-else class="divide-y">
                    <div v-for="group in entriesByDay" :key="group.key">
                        <div
                            class="bg-muted/45 px-4 py-2 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            {{ group.label }}
                        </div>
                        <article
                            v-for="entry in group.entries"
                            :id="`shared-entry-${entry.uuid}`"
                            :key="entry.uuid"
                            class="m-3 grid min-w-0 gap-3 rounded-2xl border bg-card p-3 sm:m-4 sm:p-4"
                        >
                            <div class="min-w-0 space-y-3">
                                <div
                                    class="flex min-w-0 flex-wrap items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <h3
                                            class="text-base font-semibold break-words"
                                        >
                                            {{
                                                entry.title ||
                                                ui('Movimento condiviso')
                                            }}
                                        </h3>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                entry.counterparty ??
                                                ui('Persona da verificare')
                                            }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p
                                            v-if="!entry.is_settlement"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ ui('Importo spesa') }}
                                        </p>
                                        <p
                                            class="text-2xl font-bold whitespace-nowrap tabular-nums"
                                        >
                                            {{ money(entry) }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    v-if="!entry.is_settlement"
                                    class="text-sm font-medium"
                                >
                                    {{
                                        entry.paid_by === 'counterparty'
                                            ? ui('{name} ha pagato', {
                                                  name:
                                                      entry.counterparty ?? '',
                                              })
                                            : ui('Hai pagato tu')
                                    }}
                                </p>
                                <dl
                                    v-if="entry.my_share_cents !== undefined"
                                    class="grid gap-2 rounded-xl bg-muted/40 p-3 text-sm sm:grid-cols-2"
                                >
                                    <div>
                                        <dt class="text-muted-foreground">
                                            {{ ui('La tua quota') }}
                                        </dt>
                                        <dd class="mt-1 font-semibold">
                                            {{
                                                cents(
                                                    entry.my_share_cents,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="break-words text-muted-foreground"
                                        >
                                            {{
                                                ui('Quota {name}', {
                                                    name:
                                                        entry.counterparty ??
                                                        '',
                                                })
                                            }}
                                        </dt>
                                        <dd class="mt-1 font-semibold">
                                            {{
                                                cents(
                                                    entry.counterparty_share_cents ??
                                                        0,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <p
                                    v-if="
                                        entry.common_settlement?.economic_group
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        ui(
                                            'La situazione seguente riguarda l’intera ricorrenza, composta da più movimenti.',
                                        )
                                    }}
                                </p>
                                <dl
                                    v-if="
                                        !entry.is_settlement &&
                                        entry.display_state !== 'review' &&
                                        (entry.repaid_cents > 0 ||
                                            entry.compensated_cents > 0)
                                    "
                                    class="grid gap-2 text-sm sm:grid-cols-3"
                                >
                                    <div>
                                        <dt class="text-muted-foreground">
                                            {{ ui('Quota da regolare') }}
                                        </dt>
                                        <dd class="font-semibold">
                                            {{
                                                cents(
                                                    entry.original_share_cents,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">
                                            {{ ui('Rimborsato') }}
                                        </dt>
                                        <dd class="font-semibold">
                                            {{
                                                cents(
                                                    entry.repaid_cents,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">
                                            {{ ui('Residuo') }}
                                        </dt>
                                        <dd class="font-semibold">
                                            {{
                                                cents(
                                                    entry.remaining_cents ?? 0,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div v-if="entry.compensated_cents > 0">
                                        <dt class="text-muted-foreground">
                                            {{ ui('Compensato') }}
                                        </dt>
                                        <dd class="font-semibold">
                                            {{
                                                cents(
                                                    entry.compensated_cents,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <p
                                    class="border-t pt-3 text-lg font-semibold"
                                    :class="
                                        entry.display_state === 'review'
                                            ? 'text-amber-700 dark:text-amber-300'
                                            : (entry.original_effect_cents ??
                                                    0) < 0
                                              ? 'text-rose-700 dark:text-rose-300'
                                              : 'text-emerald-700 dark:text-emerald-300'
                                    "
                                >
                                    {{ residualLabel(entry) }}
                                </p>
                                <p
                                    v-if="
                                        !entry.is_settlement &&
                                        entry.repaid_cents > 0 &&
                                        entry.display_state !== 'review'
                                    "
                                    class="text-sm text-muted-foreground"
                                >
                                    {{
                                        (entry.original_effect_cents ?? 0) >= 0
                                            ? ui('Rimborso ricevuto')
                                            : ui('Rimborso effettuato')
                                    }}:
                                    {{
                                        cents(
                                            entry.repaid_cents,
                                            entry.currency,
                                        )
                                    }}<template v-if="entry.refund_date">
                                        · {{ refundDate(entry) }}</template
                                    >
                                </p>
                                <SharedReviewActions
                                    v-if="entry.display_state === 'review'"
                                    :reason="entry.review_reason"
                                    :origin-url="entry.origin_url"
                                    :can-prepare-payments="canPreparePayments"
                                    @reference="openReferenceReview(entry)"
                                    @payments="linkExistingPayments(entry)"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ entry.origin
                                    }}<template v-if="entry.reference">
                                        · {{ entry.reference }}</template
                                    ><template v-if="entry.category">
                                        · {{ entry.category }}</template
                                    >
                                </p>
                            </div>
                            <div
                                class="flex min-w-0 flex-wrap items-center justify-between gap-2 border-t pt-1"
                            >
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                                    >{{ stateLabel(entry) }}</span
                                >
                                <Link
                                    v-if="entry.counterparty_uuid"
                                    :href="
                                        completeSituationUrl(
                                            entry.counterparty_uuid,
                                            entry.currency,
                                        )
                                    "
                                    class="inline-flex min-h-11 items-center text-xs font-medium text-primary underline"
                                    >{{ ui('Vedi situazione completa') }}</Link
                                >
                                <Button
                                    v-if="
                                        entry.source_kind === 'ledger' &&
                                        !entry.is_cancelled
                                    "
                                    size="sm"
                                    variant="outline"
                                    @click="openReferenceReview(entry)"
                                    >{{ ui('Modifica riferimento') }}</Button
                                >
                                <Link
                                    v-if="entry.origin_url"
                                    :href="entry.origin_url"
                                    class="inline-flex min-h-11 items-center text-xs font-medium text-primary hover:underline"
                                    >{{ entry.origin_label }} →</Link
                                ><Button
                                    v-if="
                                        entry.can_cancel_payment &&
                                        !entry.is_cancelled
                                    "
                                    size="sm"
                                    variant="outline"
                                    @click="cancelLedger(entry)"
                                >
                                    {{ ui('Annulla pagamento') }} </Button
                                ><Button
                                    v-if="
                                        entry.is_settlement &&
                                        !commonRelations.some(
                                            (r) =>
                                                r.active &&
                                                r.counterparty_uuid ===
                                                    entry.counterparty_uuid &&
                                                r.currency === entry.currency,
                                        )
                                    "
                                    size="sm"
                                    variant="outline"
                                    @click="editSettlement(entry)"
                                    >{{ ui('Modifica') }}</Button
                                ><Button
                                    v-if="
                                        entry.is_settlement &&
                                        !commonRelations.some(
                                            (r) =>
                                                r.active &&
                                                r.counterparty_uuid ===
                                                    entry.counterparty_uuid &&
                                                r.currency === entry.currency,
                                        )
                                    "
                                    size="sm"
                                    variant="destructive"
                                    @click="deleteSettlement(entry)"
                                    >{{ ui('Elimina') }}</Button
                                >
                            </div>
                        </article>
                    </div>
                </div>
                <nav
                    v-if="pagination.last_page > 1"
                    :aria-label="ui('Movimenti condivisi')"
                    class="flex items-center justify-between gap-2 border-t p-3 text-sm"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="min-h-11"
                        :disabled="!pagination.prev_page_url"
                        @click="
                            pagination.prev_page_url &&
                            router.visit(pagination.prev_page_url, {
                                preserveState: true,
                                preserveScroll: true,
                            })
                        "
                    >
                        {{ ui('Precedente') }}
                    </Button>
                    <span
                        class="text-center text-muted-foreground"
                        aria-live="polite"
                    >
                        {{ pagination.current_page }} /
                        {{ pagination.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        class="min-h-11"
                        :disabled="!pagination.next_page_url"
                        @click="
                            pagination.next_page_url &&
                            router.visit(pagination.next_page_url, {
                                preserveState: true,
                                preserveScroll: true,
                            })
                        "
                    >
                        {{ ui('Successiva') }}
                    </Button>
                </nav>
            </section>
        </div>
        <Dialog
            :open="cancelLedgerEntry !== null"
            @update:open="
                (value) => {
                    if (!value) cancelLedgerEntry = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            ui('Annulla pagamento di {name}', {
                                name: cancelLedgerEntry?.counterparty ?? '',
                            })
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            ui(
                                'Il movimento condiviso verrà eliminato e la scadenza tornerà da pagare. Non verrà creata una transazione.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelLedgerEntry = null">
                        {{ ui('Indietro') }}
                    </Button>
                    <Button @click="confirmCancelLedger">
                        {{ ui('Conferma annullamento') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="settlementCounterparty !== null"
            @update:open="
                (open) => {
                    if (!open) settlementCounterparty = null;
                }
            "
        >
            <DialogContent
                class="max-h-[90dvh] min-w-0 overflow-x-hidden overflow-y-auto sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle>{{ ui('Registra rimborso') }}</DialogTitle
                    ><DialogDescription v-if="settlementCounterparty"
                        >{{
                            settlementCounterparty.balance > 0
                                ? ui('{name} ti deve {amount}', {
                                      name: settlementCounterparty.name,
                                      amount: cents(
                                          settlementCounterparty.balance,
                                      ),
                                  })
                                : ui('Devi {amount} a {name}', {
                                      name: settlementCounterparty.name,
                                      amount: cents(
                                          -settlementCounterparty.balance,
                                      ),
                                  })
                        }}{{
                            ui(
                                '. Verrà creato un movimento reale sul conto scelto.',
                            )
                        }}</DialogDescription
                    ></DialogHeader
                >
                <form
                    class="grid min-w-0 gap-4 [&_input]:max-md:h-11 [&_input]:max-md:min-w-0 [&_input]:max-md:rounded-2xl [&_input]:max-md:text-base"
                    @submit.prevent="submitSettlement"
                >
                    <div class="grid gap-1.5">
                        <Label v-if="!isMobile" for="settlement-amount">{{
                            ui('Importo')
                        }}</Label
                        ><MobileAmountInput
                            v-if="isMobile"
                            id="settlement-amount"
                            v-model="settlementForm.amount"
                            :format-locale="locale"
                            :currency-code="
                                settlementOptions.accounts.find(
                                    (item) =>
                                        item.uuid ===
                                        settlementForm.account_uuid,
                                )?.currency
                            "
                            :label="ui('Importo')"
                        />
                        <Input
                            v-else
                            id="settlement-amount"
                            v-model="settlementForm.amount"
                            inputmode="decimal"
                        /><InputError :message="settlementForm.errors.amount" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-date">{{ ui('Data') }}</Label
                        ><AppDatePicker
                            id="settlement-date"
                            v-model="settlementForm.settled_at"
                        /><InputError
                            :message="settlementForm.errors.settled_at"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-account">{{
                            ui('Risorsa / conto')
                        }}</Label
                        ><MobileSearchableSelect
                            v-if="isMobile"
                            v-model="settlementForm.account_uuid"
                            :options="accountChoices"
                            :placeholder="ui('Seleziona un conto')"
                            :mobile-title="ui('Risorsa / conto')"
                            trigger-class="min-h-11 rounded-2xl"
                            :teleport="false"
                        />
                        <select
                            v-else
                            id="settlement-account"
                            v-model="settlementForm.account_uuid"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">
                                {{ ui('Seleziona un conto') }}
                            </option>
                            <option
                                v-for="account in settlementAccounts"
                                :key="account.uuid"
                                :value="account.uuid"
                            >
                                {{ account.name }} ({{ account.currency }})
                            </option></select
                        ><InputError
                            :message="settlementForm.errors.account_uuid"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-category">{{
                            ui('Categoria (facoltativa)')
                        }}</Label>
                        <div v-if="isMobile" class="grid min-w-0 gap-2">
                            <MobileQuickChoices
                                v-model="settlementForm.category_uuid"
                                :options="categoryChoices.slice(0, 8)"
                                appearance="category"
                            />
                            <MobileQuickChoices
                                v-if="categoryChildren.length"
                                v-model="settlementForm.category_uuid"
                                :options="categoryChildren"
                                appearance="category"
                            />
                            <MobileSearchableSelect
                                v-model="settlementForm.category_uuid"
                                :options="categoryChoices"
                                :placeholder="ui('Tutte · Cerca')"
                                :mobile-title="ui('Categoria')"
                                clearable
                                trigger-class="min-h-11 rounded-2xl"
                                :teleport="false"
                            />
                        </div>
                        <select
                            v-else
                            id="settlement-category"
                            v-model="settlementForm.category_uuid"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">
                                {{ ui('Nessuna categoria') }}
                            </option>
                            <option
                                v-for="category in accountCategories"
                                :key="category.uuid"
                                :value="category.uuid"
                            >
                                {{ category.name }}
                            </option></select
                        ><InputError
                            :message="settlementForm.errors.category_uuid"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-reference">{{
                            ui('Riferimento (facoltativo)')
                        }}</Label>
                        <div v-if="isMobile" class="grid min-w-0 gap-2">
                            <MobileQuickChoices
                                v-if="referenceChoices.length"
                                v-model="settlementForm.reference"
                                :options="quickReferences"
                                appearance="reference"
                            />
                            <div class="flex min-w-0 gap-2">
                                <MobileSearchableSelect
                                    v-model="settlementForm.reference"
                                    :options="referenceChoices"
                                    :placeholder="ui('Tutti · Cerca')"
                                    :mobile-title="ui('Riferimento')"
                                    clearable
                                    trigger-class="min-h-11 rounded-2xl"
                                    class="min-w-0 flex-1"
                                    :teleport="false"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="min-h-11 rounded-2xl"
                                    @click="
                                        newReferenceOpen = !newReferenceOpen
                                    "
                                    >{{ ui('+ Nuovo') }}</Button
                                >
                            </div>
                            <div
                                v-if="newReferenceOpen"
                                class="grid gap-2 rounded-2xl border bg-muted/30 p-3"
                            >
                                <Label for="settlement-reference-name">{{
                                    ui('Nome riferimento')
                                }}</Label>
                                <Input
                                    id="settlement-reference-name"
                                    v-model="newReferenceName"
                                />
                                <Label for="settlement-reference-site">{{
                                    ui('Sito web per il logo (opzionale)')
                                }}</Label>
                                <Input
                                    id="settlement-reference-site"
                                    v-model="newReferenceWebsite"
                                    type="url"
                                    placeholder="https://…"
                                />
                                <Button
                                    type="button"
                                    :disabled="creatingReference"
                                    @click="createSettlementReference"
                                    >{{ ui('Crea e seleziona') }}</Button
                                >
                            </div>
                        </div>
                        <Input
                            v-else
                            id="settlement-reference"
                            v-model="settlementForm.reference"
                        />
                    </div>
                    <InputError :message="settlementForm.errors.reference" />
                    <div class="grid gap-1.5">
                        <Label for="settlement-notes">{{
                            ui('Nota (facoltativa)')
                        }}</Label
                        ><textarea
                            id="settlement-notes"
                            v-model="settlementForm.notes"
                            class="min-h-20 w-full min-w-0 rounded-md border bg-background px-3 py-2 text-sm max-md:rounded-2xl max-md:p-3 max-md:text-base"
                        ></textarea>
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="settlementCounterparty = null"
                            >{{ ui('Annulla') }}</Button
                        ><Button
                            type="submit"
                            :disabled="settlementForm.processing"
                            >{{ ui('Registra rimborso') }}</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>
        <Dialog
            :open="reviewingReference !== null"
            @update:open="
                (open) => {
                    if (!open) reviewingReference = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader
                    ><DialogTitle>{{ ui('Conferma riferimento') }}</DialogTitle
                    ><DialogDescription>{{
                        ui(
                            'Scegli la persona corretta per questa quota. Importo, pagamenti e movimento originale restano invariati.',
                        )
                    }}</DialogDescription></DialogHeader
                >
                <form class="space-y-4" @submit.prevent="saveReferenceReview">
                    <Label>{{ ui('Riferimento') }}</Label>
                    <SearchableSelect
                        v-model="referenceConfirmation.reference_uuid"
                        :teleport="false"
                        :options="
                            referenceOptions.map((item) => ({
                                value: item.uuid,
                                label: item.name,
                            }))
                        "
                        :placeholder="ui('Scegli il riferimento')"
                    />
                    <InputError
                        :message="referenceConfirmation.errors.reference_uuid"
                    />
                    <InputError
                        :message="
                            referenceConfirmation.errors.confirm_reference
                        "
                    />
                    <Button
                        type="submit"
                        :disabled="
                            referenceConfirmation.processing ||
                            !referenceConfirmation.reference_uuid
                        "
                        >{{ ui('Conferma riferimento') }}</Button
                    >
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
