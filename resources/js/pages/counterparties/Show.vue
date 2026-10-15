<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import * as actions from '@/actions/App/Http/Controllers/CounterpartyPaymentController';
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
type Obligation = Claim & {
    title: string;
    original_minor: string;
    paid_minor: string;
    compensated_minor: string;
    due_date: string | null;
};
type Payment = {
    uuid: string;
    direction: string;
    amount: string;
    date: string;
    account_uuid: string;
    account_name: string;
    note: string | null;
    effective_minor: string;
    returned_minor: string;
    unallocated_minor: string;
    is_return: boolean;
    is_legacy: boolean;
    scope: string;
    allocations: { obligation_uuid: string; amount: string }[];
};
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
    obligations: Obligation[];
    payments: Payment[];
    accounts: { uuid: string; name: string }[];
    compensations: {
        uuid: string;
        date: string;
        amount_minor: string;
        active: boolean;
    }[];
    operations: { uuid: string; kind: string; created_at: string }[];
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
function edit(payment: Payment, nextMode: typeof mode.value) {
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

    document
        .getElementById('common-payment-form')
        ?.scrollIntoView({ behavior: 'smooth' });
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
    const options = { preserveScroll: true, onSuccess: resetPayment };
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
const operationLabel = computed<Record<string, string>>(() => ({
    activate: ui('Attivazione'),
    payment: ui('Pagamento'),
    create: ui('Pagamento'),
    cancel: ui('Annullamento amministrativo'),
    revise: ui('Revisione'),
    reallocate: ui('Modifica ripartizione'),
    compensate: ui('Compensazione'),
    reverse_compensation: ui('Storno compensazione'),
    return: ui('Restituzione reale'),
    allocation: ui('Modifica ripartizione'),
    compensation: ui('Compensazione'),
}));
</script>
<template>
    <AppLayout>
        <Head :title="relation.name" />
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ ui('Situazione completa') }}
                    </p>
                    <h1 class="text-2xl font-semibold">{{ relation.name }}</h1>
                </div>
                <div class="flex flex-wrap gap-3 text-sm">
                    <Link :href="creditsIndex.url()">
                        {{ ui('Crediti e debiti') }} </Link
                    ><Link :href="sharedIndex.url()">
                        {{ ui('Spese condivise') }}
                    </Link>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="card in [
                        {
                            title: ui('Saldo complessivo'),
                            value: relation.balance_minor,
                        },
                        {
                            title: ui('Altri crediti/debiti'),
                            value: relation.manual_minor,
                        },
                        {
                            title: ui('Spese condivise'),
                            value: relation.shared_minor,
                        },
                        {
                            title: ui('Pagamenti da collegare'),
                            value: relation.unallocated_minor,
                        },
                    ]"
                    :key="card.title"
                    class="rounded-xl border bg-card p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ card.title }}
                    </p>
                    <p class="mt-2 text-xl font-semibold">
                        {{ money(card.value) }}
                    </p>
                </div>
            </div>
            <p
                v-if="
                    relation.amounts_available !== false &&
                    BigInt(relation.balance_minor) === 0n
                "
                class="text-lg"
            >
                {{ ui('Saldo complessivo: {amount}', { amount: money('0') }) }}
            </p>
            <p v-else-if="relation.amounts_available !== false" class="text-lg">
                {{
                    BigInt(relation.balance_minor) >= 0n
                        ? ui('{name} ti deve', { name: relation.name })
                        : ui('Devi a {name}', { name: relation.name })
                }}
                {{
                    money(
                        BigInt(relation.balance_minor) < 0n
                            ? -BigInt(relation.balance_minor)
                            : relation.balance_minor,
                    )
                }}
            </p>
            <p
                v-if="relation.amounts_available === false"
                class="text-sm text-destructive"
            >
                {{
                    ui(
                        'Saldo da verificare: alcuni movimenti richiedono un controllo.',
                    )
                }}
            </p>
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
            <form
                v-if="relation.can_manage"
                id="common-payment-form"
                class="space-y-4 rounded-xl border bg-card p-4 md:p-6"
                @submit.prevent="submit"
            >
                <h2 class="text-xl font-semibold">
                    {{
                        mode === 'return'
                            ? ui('Restituzione reale di denaro')
                            : mode === 'revise'
                              ? ui('Revisione pagamento')
                              : mode === 'allocations'
                                ? ui('Ripartisci pagamento')
                                : ui('Registra pagamento')
                    }}
                </h2>
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
                        ><Input
                            id="common-amount"
                            v-model="form.amount"
                            :readonly="mode === 'allocations'"
                            inputmode="decimal"
                            required
                        />
                    </div>
                    <div v-if="mode !== 'allocations'">
                        <Label for="common-date"> {{ ui('Data') }} </Label
                        ><Input
                            id="common-date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                    </div>
                    <div v-if="mode !== 'allocations'">
                        <Label for="common-account"> {{ ui('Conto') }} </Label
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
                            {{ ui('Tutte le obbligazioni compatibili') }}
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
                    ><Input id="common-note" v-model="form.note" class="mt-2" />
                </details>
                <div class="rounded-lg bg-muted p-4">
                    <div class="flex flex-wrap justify-between gap-3 text-sm">
                        <span>
                            {{ ui('Saldo prima:') }} {{ money(before) }}</span
                        ><span>{{
                            mode === 'allocations'
                                ? ui('Nessun movimento bancario')
                                : ui('Pagamento:') + ' ' + money(amount)
                        }}</span
                        ><strong>
                            {{ ui('Saldo dopo:') }} {{ money(after) }}</strong
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
                        <p v-if="amount > allocated" class="mt-2 text-sm">
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
                <div v-if="advanced && mode !== 'return'" class="space-y-3">
                    <div
                        v-for="allocation in form.allocations"
                        :key="allocation.obligation_uuid"
                        class="grid items-center gap-2 sm:grid-cols-2"
                    >
                        <Label
                            :for="'allocation-' + allocation.obligation_uuid"
                            >{{
                                obligations.find(
                                    (o) =>
                                        o.uuid === allocation.obligation_uuid,
                                )?.title
                            }}</Label
                        ><Input
                            :id="'allocation-' + allocation.obligation_uuid"
                            v-model="allocation.amount"
                            inputmode="decimal"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="form.allow_unallocated"
                            type="checkbox"
                        />
                        {{
                            ui('Consento esplicitamente una quota non allocata')
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
                    <p v-for="(error, index) in form.errors" :key="index">
                        {{ error }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Button
                        type="submit"
                        :disabled="form.processing || !accounts.length"
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
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">
                    {{ ui('Obbligazioni e residui') }}
                </h2>
                <article
                    v-for="obligation in obligations"
                    :key="obligation.uuid"
                    class="rounded-xl border p-4"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <strong>{{ obligation.title }}</strong
                        ><span
                            >{{
                                obligation.direction === 'received'
                                    ? ui('A tuo favore')
                                    : ui('Da pagare')
                            }}
                            ·
                            {{
                                obligation.domain === 'manual'
                                    ? ui('Manuale')
                                    : ui('Condivisa')
                            }}</span
                        >
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <span>
                            {{ ui('Originale:') }}
                            {{ money(obligation.original_minor) }}</span
                        ><span>
                            {{ ui('Pagato:') }}
                            {{ money(obligation.paid_minor) }}</span
                        ><span>
                            {{ ui('Compensato:') }}
                            {{ money(obligation.compensated_minor) }}</span
                        ><strong>
                            {{ ui('Residuo:') }}
                            {{ money(obligation.remaining_minor) }}</strong
                        ><span>
                            {{ ui('Scadenza:') }}
                            {{
                                obligation.due_date ?? ui('Non concordata')
                            }}</span
                        >
                    </div>
                </article>
            </section>
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
                    ><Input
                        id="comp-amount"
                        v-model="compensation.amount"
                        inputmode="decimal"
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
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">
                    {{ ui('Pagamenti e storico') }}
                </h2>
                <article
                    v-for="payment in payments"
                    :key="payment.uuid"
                    class="space-y-3 rounded-xl border p-4"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <strong
                            >{{
                                payment.is_return
                                    ? ui('Restituzione reale')
                                    : payment.direction === 'received'
                                      ? ui('Ricevuto')
                                      : ui('Pagato')
                            }}
                            {{ payment.amount }} {{ relation.currency }}</strong
                        ><span
                            >{{ payment.date }} ·
                            {{ payment.account_name }}</span
                        >
                    </div>
                    <p class="text-sm">
                        {{
                            BigInt(payment.effective_minor) === 0n
                                ? ui(
                                      'Annullato · documento originale preservato',
                                  )
                                : ui('Allocato:') +
                                  ' ' +
                                  money(
                                      BigInt(payment.effective_minor) -
                                          BigInt(payment.returned_minor) -
                                          BigInt(payment.unallocated_minor),
                                  ) +
                                  ' · ' +
                                  ui('Non allocato:') +
                                  ' ' +
                                  money(payment.unallocated_minor)
                        }}<template v-if="BigInt(payment.returned_minor) > 0n">
                            {{ ui('· Restituito:') }}
                            {{ money(payment.returned_minor) }}</template
                        >
                    </p>
                    <p v-if="payment.note" class="text-sm">
                        {{ payment.note }}
                    </p>
                    <div
                        v-if="
                            relation.can_manage &&
                            BigInt(payment.effective_minor) > 0n
                        "
                        class="flex flex-wrap gap-2"
                    >
                        <template v-if="!payment.is_return"
                            ><Button
                                v-if="BigInt(payment.returned_minor) === 0n"
                                variant="outline"
                                size="sm"
                                @click="edit(payment, 'revise')"
                            >
                                {{ ui('Revisiona') }} </Button
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="edit(payment, 'allocations')"
                            >
                                {{ ui('Ripartisci pagamento') }} </Button
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="edit(payment, 'return')"
                            >
                                {{ ui('Restituzione reale') }}
                            </Button></template
                        ><Button
                            v-if="BigInt(payment.returned_minor) === 0n"
                            variant="outline"
                            size="sm"
                            @click="
                                confirmation = {
                                    type: 'cancel',
                                    uuid: payment.uuid,
                                }
                            "
                        >
                            {{ ui('Annullamento amministrativo') }}
                        </Button>
                    </div>
                </article>
                <article
                    v-for="entry in compensations"
                    :key="entry.uuid"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                >
                    <span>
                        {{ ui('Compensazione') }}
                        {{ money(entry.amount_minor) }} · {{ entry.date }} ·
                        {{ entry.active ? ui('Attiva') : ui('Stornata') }}</span
                    ><Button
                        v-if="entry.active && relation.can_manage"
                        variant="outline"
                        @click="
                            confirmation = { type: 'reverse', uuid: entry.uuid }
                        "
                    >
                        {{ ui('Storna compensazione') }}
                    </Button>
                </article>
                <details>
                    <summary class="cursor-pointer text-sm">
                        {{ ui('Cronologia operazioni') }}
                    </summary>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li
                            v-for="operation in operations"
                            :key="operation.uuid"
                        >
                            {{ operation.created_at }} ·
                            {{
                                operationLabel[operation.kind] ??
                                ui('Operazione registrata')
                            }}
                        </li>
                    </ul>
                </details>
            </section>
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
