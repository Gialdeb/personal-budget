<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    ArrowDownLeft,
    ArrowUpRight,
    ArrowLeftRight,
    Receipt,
    HandCoins,
    CalendarDays,
    Search,
} from 'lucide-vue-next';
import { computed, ref, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import * as actions from '@/actions/App/Http/Controllers/CounterpartyPaymentController';
import AppDatePicker from '@/components/AppDatePicker.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import CounterpartyActivity from '@/components/shared-expenses/CounterpartyActivity.vue';
import type {
    Activity,
    Payment,
} from '@/components/shared-expenses/CounterpartyActivity.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
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
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { useForm } from '@/composables/useNotifiedForm';
import { useUiCopy } from '@/i18n/ui-copy';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    decimalMinor,
    fifoAllocation,
    parseMinor,
} from '@/lib/counterparty-payments';
import type { Claim } from '@/lib/counterparty-payments';
import { index as creditsIndex } from '@/routes/credits-debts';
import { index as sharedIndex } from '@/routes/shared-expenses';
const ui = useUiCopy();
const kindOptions = [
    { value: 'all', label: 'Tutti i tipi' },
    {
        value: 'shared',
        label: 'Spese condivise',
    },
    {
        value: 'manual',
        label: 'Crediti/debiti manuali',
    },
    {
        value: 'payment',
        label: 'Pagamenti e rimborsi',
    },
    {
        value: 'compensation',
        label: 'Compensazioni',
    },
] as const;
const stateOptions = [
    {
        value: 'all',
        label: 'Tutti gli stati',
    },
    { value: 'open', label: 'Da regolare' },
    {
        value: 'partial',
        label: 'Parzialmente regolata',
    },
    { value: 'settled', label: 'Regolata' },
    {
        value: 'recorded',
        label: 'Registrato',
    },
    {
        value: 'cancelled',
        label: 'Annullato',
    },
] as const;

const props = defineProps<{
    relation: {
        uuid: string;
        name: string;
        currency: string;
        precision: number;
        active: boolean;
        can_activate: boolean;
        can_manage: boolean;
        amounts_available?: boolean;
        balance_minor: string;
        manual_minor: string;
        shared_minor: string;
        unallocated_minor: string;
    };
    activity: {
        data: Activity[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    historyFilters: {
        period: string;
        kind: string;
        state: string;
        search: string;
    };
    historyPeriods: string[];
    obligations: (Claim & {
        title: string;
    })[];
    payments: Payment[];
    accounts: { uuid: string; name: string }[];
    compensations: {
        uuid: string;
        date: string;
        amount_minor: string;
        active: boolean;
    }[];
    operations: { uuid: string; kind: string; created_at: string }[];
    paymentCount: number;
    today: string;
    initialScope?: string;
}>();
const money = (minor: string | bigint) =>
    props.relation.amounts_available === false
        ? '—'
        : `${decimalMinor(minor, props.relation.precision).replace('.', ',')} ${props.relation.currency}`;
const key = () => crypto.randomUUID();
const mode = ref<'create' | 'revise' | 'allocations' | 'return'>('create');
const selected = ref<Payment | null>(null);
const advanced = ref(false);
const form = useForm({
    operation_key: key(),
    amount: '',
    direction:
        BigInt(
            props.initialScope === 'shared'
                ? props.relation.shared_minor
                : props.relation.balance_minor,
        ) >= 0n
            ? 'received'
            : 'sent',
    date: props.today,
    account_uuid: props.accounts[0]?.uuid ?? '',
    note: '',
    scope: props.initialScope === 'shared' ? 'shared' : 'all',
    allow_unallocated: false,
    allocations: [] as { obligation_uuid: string; amount: string }[],
});
const available = computed(() =>
    props.obligations
        .filter(
            (o) =>
                o.direction === form.direction &&
                (form.scope !== 'shared' || o.domain === 'shared'),
        )
        .map((o) => ({
            ...o,
            remaining_minor: (
                BigInt(o.remaining_minor) +
                (mode.value === 'revise' || mode.value === 'allocations'
                    ? (parseMinor(
                          selected.value?.allocations.find(
                              (a) => a.obligation_uuid === o.uuid,
                          )?.amount ?? '0',
                          props.relation.precision,
                      ) ?? 0n)
                    : 0n)
            ).toString(),
        }))
        .filter((o) => BigInt(o.remaining_minor) > 0n),
);
const amount = computed(
    () => parseMinor(form.amount, props.relation.precision) ?? 0n,
);
const proposed = computed(() =>
    advanced.value
        ? form.allocations
              .map((a) => ({
                  obligation_uuid: a.obligation_uuid,
                  minor: parseMinor(a.amount, props.relation.precision) ?? 0n,
              }))
              .filter((a) => a.minor > 0n)
        : fifoAllocation(available.value, amount.value),
);
const allocated = computed(() =>
    proposed.value.reduce((sum, a) => sum + a.minor, 0n),
);
const paymentSign = computed(() => (form.direction === 'received' ? -1n : 1n));
const before = computed(() => BigInt(props.relation.balance_minor));
const after = computed(() =>
    mode.value === 'allocations'
        ? before.value
        : before.value +
          amount.value * paymentSign.value -
          (mode.value === 'revise' && selected.value
              ? BigInt(selected.value.effective_minor) *
                (selected.value.direction === 'received' ? -1n : 1n)
              : 0n),
);
function manageAllocation() {
    form.allocations = available.value.map((o) => ({
        obligation_uuid: o.uuid,
        amount: decimalMinor(
            proposed.value.find((a) => a.obligation_uuid === o.uuid)?.minor ??
                0n,
            props.relation.precision,
        ),
    }));
    advanced.value = true;
}
function resetPayment() {
    mode.value = 'create';
    selected.value = null;
    advanced.value = false;
    form.reset();
    form.operation_key = key();
}
async function edit(payment: Payment, nextMode: typeof mode.value) {
    rememberPaymentTrigger();
    paymentOpen.value = true;
    selected.value = payment;
    mode.value = nextMode;
    advanced.value = false;
    form.clearErrors();
    Object.assign(form, {
        operation_key: key(),
        amount:
            nextMode === 'allocations'
                ? decimalMinor(
                      BigInt(payment.effective_minor) -
                          BigInt(payment.returned_minor),
                      props.relation.precision,
                  )
                : payment.amount,
        date: props.today,
        account_uuid: payment.account_uuid,
        note: payment.note ?? '',
        scope: payment.scope,
        direction:
            nextMode === 'return'
                ? payment.direction === 'received'
                    ? 'sent'
                    : 'received'
                : payment.direction,
        allow_unallocated: false,
        allocations: [],
    });

    if (nextMode === 'revise') {
        form.date = payment.date;
    }

    if (nextMode === 'allocations') {
        manageAllocation();
        form.allocations = available.value.map((o) => ({
            obligation_uuid: o.uuid,
            amount:
                payment.allocations.find((a) => a.obligation_uuid === o.uuid)
                    ?.amount ?? '0',
        }));
        form.allow_unallocated = BigInt(payment.unallocated_minor) > 0n;
    }

    await nextTick();
    document.getElementById('common-amount')?.focus({ preventScroll: true });
}
function submit() {
    form.transform((data) => {
        const payload = {
            ...data,
            amount: data.amount.replace(',', '.'),
            allocations: data.allocations.map((a) => ({
                ...a,
                amount: a.amount.replace(',', '.'),
            })),
        };

        if (mode.value === 'return') {
            delete (payload as Partial<typeof payload>).allocations;
        } else if (advanced.value) {
            payload.allocations = payload.allocations.filter(
                (a) =>
                    (parseMinor(a.amount, props.relation.precision) ?? 0n) > 0n,
            );
        } else {
            delete (payload as Partial<typeof payload>).allocations;
        }

        return payload;
    });
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetPayment();
            paymentOpen.value = false;
        },
    };
    const args = {
        relation: props.relation.uuid,
        payment: selected.value?.uuid ?? '',
    };

    if (mode.value === 'create') {
        form.post(actions.store.url(props.relation.uuid), options);
    } else if (mode.value === 'revise') {
        form.patch(actions.update.url(args), options);
    } else if (mode.value === 'allocations') {
        form.patch(actions.allocations.url(args), options);
    } else {
        form.post(actions.returnMoney.url(args), options);
    }
}
const command = useForm({ operation_key: key() });
const confirmation = ref<{
    type: 'activate' | 'cancel' | 'reverse';
    uuid?: string;
} | null>(null);
function confirm() {
    if (!confirmation.value) {
        return;
    }

    command.operation_key = key();
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            confirmation.value = null;
        },
    };

    if (confirmation.value.type === 'activate') {
        command.post(actions.activate.url(props.relation.uuid), options);
    } else if (confirmation.value.type === 'cancel') {
        command.delete(
            actions.destroy.url({
                relation: props.relation.uuid,
                payment: confirmation.value.uuid!,
            }),
            options,
        );
    } else {
        command.post(
            actions.reverseCompensation.url({
                relation: props.relation.uuid,
                entry: confirmation.value.uuid!,
            }),
            options,
        );
    }
}
const compensation = useForm({
    operation_key: key(),
    amount: '',
    positive_uuid: '',
    negative_uuid: '',
    date: props.today,
});
function compensate() {
    compensation.transform((data) => ({
        ...data,
        amount: data.amount.replace(',', '.'),
    }));
    compensation.post(actions.compensate.url(props.relation.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            compensation.reset();
            compensation.operation_key = key();
        },
    });
}
const { locale } = useI18n();
const filters = ref({ ...props.historyFilters });
const paymentOpen = ref(false);
const paymentTrigger = ref<HTMLElement | null>(null);
const isMobile = useMediaQuery('(max-width: 767px)');
function rememberPaymentTrigger(): void {
    paymentTrigger.value =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
}
async function openPayment(): Promise<void> {
    rememberPaymentTrigger();
    resetPayment();
    paymentOpen.value = true;
    await nextTick();
    document.getElementById('common-amount')?.focus({ preventScroll: true });
}
const balance = computed(() => BigInt(props.relation.balance_minor));
const openPositions = computed(() => ({
    receivable: props.obligations
        .filter((claim) => claim.direction === 'received')
        .reduce((sum, claim) => sum + BigInt(claim.remaining_minor), 0n),
    payable: props.obligations
        .filter((claim) => claim.direction === 'sent')
        .reduce((sum, claim) => sum + BigInt(claim.remaining_minor), 0n),
}));
const balanceTone = computed(() =>
    props.relation.amounts_available === false || balance.value === 0n
        ? 'bg-muted/50'
        : balance.value > 0n
          ? 'bg-emerald-500/10 text-emerald-800 dark:text-emerald-200'
          : 'bg-rose-500/10 text-rose-800 dark:text-rose-200',
);
const absoluteBalance = computed(() =>
    money(balance.value < 0n ? -balance.value : balance.value),
);
function periodLabel(period: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${period}-01T12:00:00`));
}
function applyFilters(): void {
    router.get(
        actions.show.url(props.relation.uuid),
        {
            ...filters.value,
            scope: props.initialScope,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['activity', 'historyFilters'],
        },
    );
}
function visitHistory(url: string | null): void {
    if (url) {
        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
            only: ['activity', 'historyFilters'],
        });
    }
}
</script>
<template>
    <AppLayout>
        <Head :title="relation.name" />
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-8">
            <header
                class="overflow-hidden rounded-2xl border bg-card shadow-sm"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3 text-sm"
                >
                    <p class="text-muted-foreground">
                        {{ ui('Rapporti con le persone') }}
                    </p>
                    <div class="flex gap-2">
                        <Button as-child variant="ghost" size="sm"
                            ><Link :href="creditsIndex.url()">{{
                                ui('Crediti e debiti')
                            }}</Link></Button
                        ><Button as-child variant="ghost" size="sm"
                            ><Link :href="sharedIndex.url()">{{
                                ui('Spese condivise')
                            }}</Link></Button
                        >
                    </div>
                </div>
                <div
                    class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between md:p-7"
                >
                    <div class="min-w-0">
                        <p class="text-sm text-muted-foreground">
                            {{ ui('Situazione con') }}
                        </p>
                        <h1
                            class="mt-1 text-3xl font-semibold tracking-tight break-words"
                        >
                            {{ relation.name }}
                        </h1>
                        <p class="mt-3 text-sm text-muted-foreground">
                            {{ ui('Saldo netto · tutti i periodi') }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl px-5 py-4 sm:text-right"
                        :class="balanceTone"
                    >
                        <p
                            class="flex items-center gap-2 text-sm font-medium sm:justify-end"
                        >
                            <component
                                :is="
                                    balance > 0n
                                        ? ArrowDownLeft
                                        : balance < 0n
                                          ? ArrowUpRight
                                          : ArrowLeftRight
                                "
                                class="size-4"
                                aria-hidden="true"
                            />{{
                                relation.amounts_available === false
                                    ? ui('Saldo da verificare')
                                    : balance === 0n
                                      ? ui('Siete in pari')
                                      : balance > 0n
                                        ? ui('Ti deve')
                                        : ui('Devi')
                            }}
                        </p>
                        <p
                            class="mt-2 text-3xl font-semibold whitespace-nowrap tabular-nums"
                        >
                            {{ absoluteBalance }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="relation.amounts_available === false"
                    class="px-5 pb-5 text-sm text-destructive"
                >
                    {{
                        ui(
                            'Saldo da verificare: alcuni movimenti richiedono un controllo.',
                        )
                    }}
                </p>
            </header>
            <section
                v-if="relation.active"
                class="space-y-4 rounded-2xl border bg-card p-4 sm:p-5"
                aria-labelledby="open-positions-title"
            >
                <h2 id="open-positions-title" class="text-base font-semibold">
                    {{ ui('Posizioni aperte') }}
                </h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            {{ ui('Devi ricevere') }}
                        </dt>
                        <dd
                            class="mt-1 text-xl font-semibold text-emerald-700 tabular-nums dark:text-emerald-300"
                        >
                            {{ money(openPositions.receivable) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            {{ ui('Devi restituire') }}
                        </dt>
                        <dd
                            class="mt-1 text-xl font-semibold text-orange-700 tabular-nums dark:text-orange-300"
                        >
                            {{ money(openPositions.payable) }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="
                        openPositions.receivable - openPositions.payable ===
                        balance
                    "
                    class="rounded-xl bg-muted/50 px-3 py-2 text-sm tabular-nums"
                    data-balance-explanation
                >
                    {{ ui('Saldo netto') }}:
                    {{ money(openPositions.receivable) }} −
                    {{ money(openPositions.payable) }} =
                    {{ money(relation.balance_minor) }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    {{
                        ui(
                            'Le posizioni aperte non rappresentano da sole il saldo netto: consulta anche i movimenti non collegati e lo storico.',
                        )
                    }}
                </p>
                <p class="text-xs leading-5 text-muted-foreground">
                    {{
                        ui(
                            'Il saldo netto è una differenza matematica: non chiude le singole posizioni e non registra compensazioni.',
                        )
                    }}
                </p>
            </section>
            <section
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    v-for="card in [
                        {
                            title: ui('Spese condivise'),
                            value: relation.shared_minor,
                            icon: Receipt,
                        },
                        {
                            title: ui('Crediti/debiti manuali'),
                            value: relation.manual_minor,
                            icon: HandCoins,
                        },
                        {
                            title: ui('Pagamenti da collegare'),
                            value: relation.unallocated_minor,
                            icon: ArrowLeftRight,
                        },
                    ]"
                    :key="card.title"
                    class="rounded-2xl border bg-card p-4"
                >
                    <p
                        class="flex items-center gap-2 text-xs font-medium text-muted-foreground"
                    >
                        <component
                            :is="card.icon"
                            class="size-4"
                            aria-hidden="true"
                        />{{ card.title }}
                    </p>
                    <p
                        class="mt-3 text-xl font-semibold whitespace-nowrap tabular-nums"
                    >
                        {{ money(card.value) }}
                    </p>
                </div>
                <div class="rounded-2xl border bg-card p-4">
                    <p
                        class="flex items-center gap-2 text-xs font-medium text-muted-foreground"
                    >
                        <ArrowDownLeft class="size-4" aria-hidden="true" />{{
                            ui('Pagamenti e rimborsi')
                        }}
                    </p>
                    <p class="mt-3 text-xl font-semibold tabular-nums">
                        {{ paymentCount }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ ui('Registrazioni nello storico') }}
                    </p>
                </div>
            </section>
            <p class="text-xs leading-relaxed text-muted-foreground">
                {{
                    ui(
                        'Pagamenti e rimborsi collegati sono già inclusi nei residui. Le somme da collegare sono indicate separatamente. I filtri modificano solo lo storico.',
                    )
                }}
            </p>
            <div v-if="relation.can_manage" class="flex justify-end">
                <Button class="min-h-11 w-full sm:w-auto" @click="openPayment"
                    ><ArrowLeftRight class="size-4" aria-hidden="true" />{{
                        ui('Registra pagamento')
                    }}</Button
                >
            </div>
            <section class="space-y-5" aria-labelledby="history-title">
                <div>
                    <h2 id="history-title" class="text-xl font-semibold">
                        {{ ui('Movimenti e pagamenti') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            ui(
                                'Spese, crediti, rimborsi e compensazioni in un unico storico.',
                            )
                        }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{
                            ui(
                                'I residui sono attuali e includono già pagamenti e compensazioni; non sono il saldo alla data del movimento.',
                            )
                        }}
                    </p>
                </div>
                <form
                    class="grid grid-cols-2 items-end gap-3 rounded-2xl border bg-card p-3 lg:grid-cols-4"
                    @submit.prevent="applyFilters"
                >
                    <div class="min-w-0 space-y-1.5">
                        <Label for="history-period">{{ ui('Periodo') }}</Label
                        ><Select
                            v-model="filters.period"
                            @update:model-value="applyFilters"
                            ><SelectTrigger
                                id="history-period"
                                class="h-11 w-full rounded-2xl"
                                ><CalendarDays
                                    class="size-4 shrink-0"
                                    aria-hidden="true" /><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem value="all">{{
                                    ui('Tutti i periodi')
                                }}</SelectItem
                                ><SelectItem
                                    v-for="period in historyPeriods"
                                    :key="period"
                                    :value="period"
                                    >{{ periodLabel(period) }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <Label for="history-kind">{{
                            ui('Tipo movimento')
                        }}</Label
                        ><Select
                            v-model="filters.kind"
                            @update:model-value="applyFilters"
                            ><SelectTrigger
                                id="history-kind"
                                class="h-11 w-full rounded-2xl"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="option in kindOptions"
                                    :key="option.value"
                                    :value="option.value"
                                    >{{ ui(option.label) }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <Label for="history-state">{{ ui('Stato') }}</Label
                        ><Select
                            v-model="filters.state"
                            @update:model-value="applyFilters"
                            ><SelectTrigger
                                id="history-state"
                                class="h-11 w-full rounded-2xl"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="option in stateOptions"
                                    :key="option.value"
                                    :value="option.value"
                                    >{{ ui(option.label) }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <Label for="history-search">{{ ui('Ricerca') }}</Label>
                        <div class="flex gap-1">
                            <Input
                                id="history-search"
                                v-model="filters.search"
                                class="h-11 min-w-0 rounded-2xl"
                                :placeholder="ui('Descrizione o nota')"
                                type="search"
                            /><Button
                                type="submit"
                                variant="outline"
                                class="size-11 shrink-0 rounded-xl px-0"
                                :aria-label="ui('Cerca')"
                                ><Search class="size-4" aria-hidden="true"
                            /></Button>
                        </div>
                    </div>
                </form>
                <p
                    class="text-xs text-muted-foreground"
                    role="status"
                    aria-live="polite"
                >
                    {{
                        activity.total
                            ? `${activity.from}–${activity.to} / ${activity.total}`
                            : '0'
                    }}
                    {{ ui('movimenti') }}
                </p>
                <CounterpartyActivity
                    :rows="activity.data"
                    :relation="relation"
                    :obligations="obligations"
                    @edit="edit"
                    @cancel="confirmation = { type: 'cancel', uuid: $event }"
                    @reverse="confirmation = { type: 'reverse', uuid: $event }"
                />
                <nav
                    v-if="activity.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-card p-3"
                    :aria-label="ui('Paginazione movimenti')"
                >
                    <Button
                        variant="outline"
                        class="min-h-11"
                        :disabled="!activity.prev_page_url"
                        @click="visitHistory(activity.prev_page_url)"
                        >{{ ui('Precedente') }}</Button
                    ><span class="text-sm tabular-nums"
                        >{{ activity.current_page }} /
                        {{ activity.last_page }}</span
                    ><Button
                        variant="outline"
                        class="min-h-11"
                        :disabled="!activity.next_page_url"
                        @click="visitHistory(activity.next_page_url)"
                        >{{ ui('Successiva') }}</Button
                    >
                </nav>
            </section>
            <div
                v-if="!relation.can_manage"
                class="space-y-3 rounded-xl border p-4"
            >
                <p>
                    {{
                        relation.active
                            ? ui(
                                  'La gestione comune è temporaneamente in sola lettura.',
                              )
                            : ui(
                                  'I pagamenti continuano a usare i flussi esistenti fino all’attivazione esplicita.',
                              )
                    }}
                </p>
                <Button
                    v-if="relation.can_activate"
                    @click="confirmation = { type: 'activate' }"
                >
                    {{ ui('Attiva gestione comune') }}
                </Button>
            </div>
            <Sheet v-if="relation.can_manage" v-model:open="paymentOpen">
                <SheetContent
                    :side="isMobile ? 'bottom' : 'right'"
                    class="min-h-0 overflow-hidden p-0"
                    :class="
                        isMobile
                            ? 'h-[100dvh] max-h-[100dvh] w-full rounded-t-3xl'
                            : 'w-full sm:max-w-2xl'
                    "
                    @open-auto-focus.prevent
                    @close-auto-focus.prevent="
                        paymentTrigger?.focus({ preventScroll: true })
                    "
                >
                    <SheetHeader class="shrink-0 border-b px-5 py-5 pr-12">
                        <SheetTitle>{{
                            mode === 'return'
                                ? ui('Restituzione reale di denaro')
                                : mode === 'revise'
                                  ? ui('Revisione pagamento')
                                  : mode === 'allocations'
                                    ? ui('Ripartisci pagamento')
                                    : ui('Registra pagamento')
                        }}</SheetTitle>
                        <SheetDescription>{{
                            ui(
                                'Registra il movimento reale e verifica la ripartizione prima di confermare.',
                            )
                        }}</SheetDescription>
                    </SheetHeader>
                    <div
                        class="min-h-0 flex-1 touch-pan-y overflow-x-hidden overflow-y-auto overscroll-y-contain px-5 pb-6"
                    >
                        <form
                            id="common-payment-form"
                            class="space-y-4 [&_button]:min-h-11 [&_input]:text-base [&_input:not([type=checkbox])]:min-h-11 [&_select]:min-h-11"
                            @submit.prevent="submit"
                        >
                            <p
                                v-if="mode === 'revise'"
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    ui(
                                        'Il pagamento precedente sarà stornato e sostituito. Lo storico resta consultabile.',
                                    )
                                }}
                            </p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label for="common-direction">
                                        {{ ui('Direzione') }} </Label
                                    ><select
                                        id="common-direction"
                                        v-model="form.direction"
                                        :disabled="mode !== 'create'"
                                        class="mt-1 h-10 w-full rounded-md border bg-background px-3"
                                        @change="advanced = false"
                                    >
                                        <option value="received">
                                            {{
                                                ui('Ricevuto da {name}', {
                                                    name: relation.name,
                                                })
                                            }}
                                        </option>
                                        <option value="sent">
                                            {{
                                                ui('Pagato a {name}', {
                                                    name: relation.name,
                                                })
                                            }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="common-amount">
                                        {{ ui('Importo (') }}
                                        {{ relation.currency }})</Label
                                    ><MobileAmountInput
                                        id="common-amount"
                                        v-model="form.amount"
                                        :currency-code="relation.currency"
                                        :precision="relation.precision"
                                        :readonly="mode === 'allocations'"
                                        required
                                    />
                                </div>
                                <div v-if="mode !== 'allocations'">
                                    <Label for="common-date">
                                        {{ ui('Data') }} </Label
                                    ><AppDatePicker
                                        id="common-date"
                                        v-model="form.date"
                                        required
                                    />
                                </div>
                                <div v-if="mode !== 'allocations'">
                                    <Label for="common-account">
                                        {{ ui('Conto') }} </Label
                                    ><select
                                        id="common-account"
                                        v-model="form.account_uuid"
                                        required
                                        class="mt-1 h-10 w-full rounded-md border bg-background px-3"
                                    >
                                        <option value="" disabled>
                                            {{ ui('Seleziona conto') }}
                                        </option>
                                        <option
                                            v-for="account in accounts"
                                            :key="account.uuid"
                                            :value="account.uuid"
                                        >
                                            {{ account.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="mode !== 'return'">
                                <Label for="common-scope">
                                    {{ ui('Cosa stai regolando') }} </Label
                                ><select
                                    id="common-scope"
                                    v-model="form.scope"
                                    class="mt-1 h-10 w-full rounded-md border bg-background px-3"
                                    @change="advanced = false"
                                >
                                    <option value="all">
                                        {{
                                            ui(
                                                'Tutte le obbligazioni compatibili',
                                            )
                                        }}
                                    </option>
                                    <option value="shared">
                                        {{ ui('Solo spese condivise') }}
                                    </option>
                                </select>
                            </div>
                            <details v-if="mode !== 'allocations'">
                                <summary class="cursor-pointer text-sm">
                                    {{ ui('Note opzionali') }}
                                </summary>
                                <Label for="common-note" class="sr-only">
                                    {{ ui('Note') }} </Label
                                ><Input
                                    id="common-note"
                                    v-model="form.note"
                                    class="mt-2"
                                />
                            </details>
                            <div class="rounded-lg bg-muted p-4">
                                <div
                                    class="flex flex-wrap justify-between gap-3 text-sm"
                                >
                                    <span>
                                        {{ ui('Saldo prima:') }}
                                        {{ money(before) }}</span
                                    ><span>{{
                                        mode === 'allocations'
                                            ? ui('Nessun movimento bancario')
                                            : ui('Pagamento:') +
                                              ' ' +
                                              money(amount)
                                    }}</span
                                    ><strong>
                                        {{ ui('Saldo dopo:') }}
                                        {{ money(after) }}</strong
                                    >
                                </div>
                                <template v-if="mode !== 'return'"
                                    ><p class="mt-3 font-medium">
                                        {{ money(allocated) }}
                                        {{ ui('saranno applicati a:') }}
                                    </p>
                                    <ul class="mt-1 space-y-1 text-sm">
                                        <li
                                            v-for="allocation in proposed"
                                            :key="allocation.obligation_uuid"
                                        >
                                            {{
                                                obligations.find(
                                                    (o) =>
                                                        o.uuid ===
                                                        allocation.obligation_uuid,
                                                )?.title
                                            }}: {{ money(allocation.minor) }}
                                        </li>
                                    </ul>
                                    <p
                                        v-if="amount > allocated"
                                        class="mt-2 text-sm"
                                    >
                                        {{ ui('Quota non allocata:') }}
                                        {{ money(amount - allocated) }}
                                        {{
                                            ui(
                                                '. È necessaria un’autorizzazione esplicita.',
                                            )
                                        }}
                                    </p></template
                                >
                            </div>
                            <Button
                                v-if="mode !== 'return' && !advanced"
                                type="button"
                                variant="outline"
                                @click="manageAllocation"
                            >
                                {{ ui('Ripartisci pagamento') }}
                            </Button>
                            <div
                                v-if="advanced && mode !== 'return'"
                                class="space-y-3"
                            >
                                <div
                                    v-for="allocation in form.allocations"
                                    :key="allocation.obligation_uuid"
                                    class="grid items-center gap-2 sm:grid-cols-2"
                                >
                                    <Label
                                        :for="
                                            'allocation-' +
                                            allocation.obligation_uuid
                                        "
                                        >{{
                                            obligations.find(
                                                (o) =>
                                                    o.uuid ===
                                                    allocation.obligation_uuid,
                                            )?.title
                                        }}</Label
                                    ><MobileAmountInput
                                        :id="
                                            'allocation-' +
                                            allocation.obligation_uuid
                                        "
                                        v-model="allocation.amount"
                                        :currency-code="relation.currency"
                                        :precision="relation.precision"
                                    />
                                </div>
                                <label class="flex items-center gap-2 text-sm"
                                    ><input
                                        v-model="form.allow_unallocated"
                                        type="checkbox"
                                    />
                                    {{
                                        ui(
                                            'Consento esplicitamente una quota non allocata',
                                        )
                                    }}
                                </label>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        ui(
                                            'Le eccedenze rispetto al saldo ammesso sono bloccate; questa opzione non crea anticipi.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="form.hasErrors"
                                role="alert"
                                class="space-y-1 text-sm text-destructive"
                            >
                                <p
                                    v-for="(error, index) in form.errors"
                                    :key="index"
                                >
                                    {{ error }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <Button
                                    type="submit"
                                    :disabled="
                                        form.processing || !accounts.length
                                    "
                                    >{{
                                        mode === 'return'
                                            ? ui('Conferma restituzione')
                                            : mode === 'allocations'
                                              ? ui('Conferma ripartizione')
                                              : ui('Conferma pagamento')
                                    }}</Button
                                ><Button
                                    v-if="mode !== 'create'"
                                    type="button"
                                    variant="outline"
                                    @click="resetPayment"
                                >
                                    {{ ui('Annulla modifica') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </SheetContent>
            </Sheet>
            <details v-if="relation.can_manage" class="rounded-xl border p-4">
                <summary class="cursor-pointer font-semibold">
                    {{ ui('Compensazione senza movimento bancario') }}
                </summary>
                <form class="mt-4 space-y-3" @submit.prevent="compensate">
                    <p class="text-sm">
                        {{
                            ui(
                                'Seleziona un credito e un debito. La compensazione riduce entrambi i residui e mantiene il saldo complessivo.',
                            )
                        }}
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label for="comp-positive">
                                {{ ui('Credito') }} </Label
                            ><select
                                id="comp-positive"
                                v-model="compensation.positive_uuid"
                                required
                                class="h-10 w-full rounded border bg-background"
                            >
                                <option value="" disabled>
                                    {{ ui('Seleziona credito') }}
                                </option>
                                <option
                                    v-for="o in obligations.filter(
                                        (o) =>
                                            o.direction === 'received' &&
                                            BigInt(o.remaining_minor) > 0n,
                                    )"
                                    :key="o.uuid"
                                    :value="o.uuid"
                                >
                                    {{ o.title }} ·
                                    {{ money(o.remaining_minor) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label for="comp-negative">
                                {{ ui('Debito') }} </Label
                            ><select
                                id="comp-negative"
                                v-model="compensation.negative_uuid"
                                required
                                class="h-10 w-full rounded border bg-background"
                            >
                                <option value="" disabled>
                                    {{ ui('Seleziona debito') }}
                                </option>
                                <option
                                    v-for="o in obligations.filter(
                                        (o) =>
                                            o.direction === 'sent' &&
                                            BigInt(o.remaining_minor) > 0n,
                                    )"
                                    :key="o.uuid"
                                    :value="o.uuid"
                                >
                                    {{ o.title }} ·
                                    {{ money(o.remaining_minor) }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <Label for="comp-amount">
                        {{ ui('Importo da compensare') }} </Label
                    ><MobileAmountInput
                        id="comp-amount"
                        v-model="compensation.amount"
                        :currency-code="relation.currency"
                        :precision="relation.precision"
                        required
                    />
                    <p
                        v-for="(error, index) in compensation.errors"
                        :key="index"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                    <Button :disabled="compensation.processing">
                        {{ ui('Conferma compensazione') }}
                    </Button>
                </form>
            </details>
            <Dialog
                :open="confirmation !== null"
                @update:open="
                    (open) => {
                        if (!open) confirmation = null;
                    }
                "
                ><DialogContent
                    ><DialogHeader
                        ><DialogTitle>{{
                            confirmation?.type === 'activate'
                                ? ui('Attiva gestione comune')
                                : ui('Conferma storno')
                        }}</DialogTitle
                        ><DialogDescription>{{
                            confirmation?.type === 'activate'
                                ? ui(
                                      'Da questa attivazione i nuovi pagamenti useranno esclusivamente la gestione comune. Lo storico esistente viene conservato.',
                                  )
                                : confirmation?.type === 'cancel'
                                  ? ui(
                                        'Questo è un annullamento amministrativo: il movimento bancario viene annullato, i residui riaperti e lo storico conservato. Per denaro realmente restituito usa Restituzione reale.',
                                    )
                                  : ui(
                                        'La compensazione viene stornata e i residui riaperti, senza movimenti bancari.',
                                    )
                        }}</DialogDescription></DialogHeader
                    >
                    <p
                        v-for="(error, index) in command.errors"
                        :key="index"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                    <Button :disabled="command.processing" @click="confirm">
                        {{ ui('Conferma') }}
                    </Button></DialogContent
                ></Dialog
            >
        </div>
    </AppLayout>
</template>
