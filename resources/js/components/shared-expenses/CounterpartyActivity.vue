<script setup lang="ts">
import {
    ArrowDownLeft,
    ArrowUpRight,
    ArrowLeftRight,
    Receipt,
    HandCoins,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useUiCopy } from '@/i18n/ui-copy';
import { decimalMinor, parseMinor } from '@/lib/counterparty-payments';
import type { Claim } from '@/lib/counterparty-payments';
export type Obligation = Claim & {
    title: string;
    original_minor: string;
    paid_minor: string;
    compensated_minor: string;
    due_date: string | null;
    expense_total_minor: string | null;
    my_share_minor: string | null;
    counterparty_share_minor: string | null;
};
export type Payment = {
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
export type Activity = { date: string; state: string } & (
    | (Obligation & { kind: 'shared' | 'manual' })
    | (Payment & { kind: 'payment' })
    | {
          kind: 'compensation';
          uuid: string;
          amount_minor: string;
          active: boolean;
      }
);
const props = defineProps<{
    rows: Activity[];
    relation: {
        name: string;
        precision: number;
        currency: string;
        can_manage: boolean;
        amounts_available?: boolean;
    };
}>();
const emit = defineEmits<{
    edit: [payment: Payment, mode: 'revise' | 'allocations' | 'return'];
    cancel: [uuid: string];
    reverse: [uuid: string];
}>();
const ui = useUiCopy();
const { locale } = useI18n();
const money = (minor: string | bigint) =>
    props.relation.amounts_available === false
        ? '—'
        : `${decimalMinor(minor, props.relation.precision).replace('.', ',')} ${props.relation.currency}`;
const groups = computed(() => {
    const months = new Map<string, Activity[]>();

    for (const row of props.rows) {
        const month = row.date.slice(0, 7);
        months.set(month, [...(months.get(month) ?? []), row]);
    }

    return [...months].map(([month, rows]) => ({
        month,
        rows,
        label: new Intl.DateTimeFormat(locale.value, {
            month: 'long',
            year: 'numeric',
        }).format(new Date(`${month}-01T12:00:00`)),
    }));
});
const dateLabel = (date: string) =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${date}T12:00:00`));
const stateLabel = (state: string) =>
    ui(
        (
            {
                open: 'Da regolare',
                partial: 'Parzialmente regolata',
                settled: 'Regolata',
                recorded: 'Registrato',
                cancelled: 'Annullato',
            } as const
        )[state as 'open' | 'partial' | 'settled' | 'recorded' | 'cancelled'] ??
            'Registrato',
    );
function sourceTone(row: Activity): string {
    if (row.state === 'cancelled') {
        return tone(row);
    }

    if (row.kind === 'shared') {
        return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }

    if (row.kind === 'manual') {
        return 'bg-violet-500/10 text-violet-700 dark:text-violet-300';
    }

    return tone(row);
}
function tone(row: Activity): string {
    if (row.state === 'cancelled' || row.state === 'settled') {
        return 'bg-muted text-muted-foreground';
    }

    if (row.kind === 'compensation') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return row.direction === 'received'
        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
        : 'bg-rose-500/10 text-rose-700 dark:text-rose-300';
}
</script>
<template>
    <div class="space-y-6">
        <div
            v-if="!rows.length"
            class="rounded-2xl border border-dashed p-8 text-center"
        >
            <Receipt
                class="mx-auto mb-3 size-7 text-muted-foreground"
                aria-hidden="true"
            />
            <p class="font-medium">
                {{ ui('Nessun movimento con questi filtri') }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ ui('Prova un altro periodo o modifica i filtri.') }}
            </p>
        </div>
        <section v-for="group in groups" :key="group.month" class="space-y-3">
            <h3
                class="flex items-center gap-3 text-sm font-semibold capitalize"
            >
                <span>{{ group.label }}</span
                ><span class="h-px flex-1 bg-border" />
            </h3>
            <div class="space-y-3 border-l-2 border-muted pl-3 sm:pl-5">
                <article
                    v-for="row in group.rows"
                    :key="`${row.kind}-${row.uuid}`"
                    class="min-w-0 space-y-4 rounded-2xl border bg-card p-4 shadow-sm sm:p-5"
                >
                    <div class="flex items-start gap-3">
                        <span
                            :class="sourceTone(row)"
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                        >
                            <component
                                :is="
                                    row.kind === 'compensation'
                                        ? ArrowLeftRight
                                        : row.kind === 'payment'
                                          ? row.direction === 'received'
                                              ? ArrowDownLeft
                                              : ArrowUpRight
                                          : row.kind === 'shared'
                                            ? Receipt
                                            : HandCoins
                                "
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <h4 class="font-semibold break-words">
                                    {{
                                        row.kind === 'payment'
                                            ? row.is_return
                                                ? ui('Restituzione reale')
                                                : row.direction === 'received'
                                                  ? ui('Pagamento ricevuto')
                                                  : ui('Pagamento effettuato')
                                            : row.kind === 'compensation'
                                              ? ui('Compensazione')
                                              : row.title
                                    }}
                                </h4>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="tone(row)"
                                    >{{ stateLabel(row.state) }}</span
                                >
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <time :datetime="row.date">{{
                                    dateLabel(row.date)
                                }}</time>
                                ·
                                {{
                                    row.kind === 'shared'
                                        ? ui('Spesa condivisa')
                                        : row.kind === 'manual'
                                          ? ui('Credito/debito manuale')
                                          : row.kind === 'compensation'
                                            ? ui('Senza movimento bancario')
                                            : row.kind === 'payment'
                                              ? row.account_name
                                              : ''
                                }}
                                <template
                                    v-if="
                                        row.kind === 'shared' ||
                                        row.kind === 'manual'
                                    "
                                >
                                    ·
                                    {{
                                        row.direction === 'received'
                                            ? ui('A tuo favore')
                                            : ui('Da pagare')
                                    }}</template
                                >
                            </p>
                        </div>
                    </div>
                    <template
                        v-if="row.kind === 'shared' || row.kind === 'manual'"
                    >
                        <dl
                            class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3"
                        >
                            <div
                                v-if="
                                    row.kind === 'shared' &&
                                    row.expense_total_minor !== null
                                "
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('Importo spesa') }}
                                </dt>
                                <dd
                                    class="mt-1 font-semibold whitespace-nowrap tabular-nums"
                                >
                                    {{ money(row.expense_total_minor) }}
                                </dd>
                            </div>
                            <div
                                v-if="
                                    row.kind === 'shared' &&
                                    row.my_share_minor !== null
                                "
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('La tua quota') }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{ money(row.my_share_minor) }}
                                </dd>
                            </div>
                            <div
                                v-if="
                                    row.kind === 'shared' &&
                                    row.counterparty_share_minor !== null
                                "
                            >
                                <dt
                                    class="text-xs break-words text-muted-foreground"
                                >
                                    {{
                                        ui('Quota {name}', {
                                            name: relation.name,
                                        })
                                    }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{ money(row.counterparty_share_minor) }}
                                </dd>
                            </div>
                            <div
                                v-if="
                                    row.kind === 'manual' ||
                                    row.expense_total_minor === null
                                "
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{
                                        row.kind === 'manual'
                                            ? ui('Importo credito/debito')
                                            : ui(
                                                  'Quota riconosciuta · sorgente parziale',
                                              )
                                    }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{ money(row.original_minor) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('Rimborsato') }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{ money(row.paid_minor) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('Compensato') }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{ money(row.compensated_minor) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('Residuo') }}
                                </dt>
                                <dd
                                    class="mt-1 font-semibold whitespace-nowrap tabular-nums"
                                >
                                    {{ money(row.remaining_minor) }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="row.due_date"
                            class="text-xs text-muted-foreground"
                        >
                            {{ ui('Scadenza:') }} {{ row.due_date }}
                        </p>
                    </template>
                    <template v-else-if="row.kind === 'payment'">
                        <p
                            class="text-xl font-semibold whitespace-nowrap tabular-nums"
                            :class="
                                row.state === 'cancelled'
                                    ? 'text-muted-foreground line-through'
                                    : row.direction === 'received'
                                      ? 'text-emerald-700 dark:text-emerald-300'
                                      : 'text-rose-700 dark:text-rose-300'
                            "
                        >
                            {{ row.direction === 'received' ? '↙' : '↗' }}
                            {{
                                money(
                                    parseMinor(
                                        row.amount,
                                        relation.precision,
                                    ) ?? 0n,
                                )
                            }}
                        </p>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('Collegato:') }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{
                                        money(
                                            BigInt(row.effective_minor) -
                                                BigInt(row.returned_minor) -
                                                BigInt(row.unallocated_minor),
                                        )
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('Da collegare:') }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{ money(row.unallocated_minor) }}
                                </dd>
                            </div>
                            <div v-if="BigInt(row.returned_minor) > 0n">
                                <dt class="text-xs text-muted-foreground">
                                    {{ ui('Restituito:') }}
                                </dt>
                                <dd class="mt-1 whitespace-nowrap tabular-nums">
                                    {{ money(row.returned_minor) }}
                                </dd>
                            </div>
                        </dl>
                        <p v-if="row.note" class="text-sm break-words">
                            {{ row.note }}
                        </p>
                        <div
                            v-if="
                                relation.can_manage &&
                                BigInt(row.effective_minor) > 0n
                            "
                            class="flex flex-wrap gap-2 border-t pt-3"
                        >
                            <template v-if="!row.is_return">
                                <Button
                                    v-if="BigInt(row.returned_minor) === 0n"
                                    variant="outline"
                                    size="sm"
                                    @click="emit('edit', row, 'revise')"
                                    >{{ ui('Revisiona') }}</Button
                                >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="emit('edit', row, 'allocations')"
                                    >{{ ui('Ripartisci pagamento') }}</Button
                                >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="emit('edit', row, 'return')"
                                    >{{ ui('Restituzione reale') }}</Button
                                >
                            </template>
                            <Button
                                v-if="BigInt(row.returned_minor) === 0n"
                                variant="outline"
                                size="sm"
                                @click="emit('cancel', row.uuid)"
                                >{{ ui('Annullamento amministrativo') }}</Button
                            >
                        </div>
                    </template>
                    <template v-else-if="row.kind === 'compensation'">
                        <p
                            class="text-xl font-semibold whitespace-nowrap tabular-nums"
                        >
                            {{ money(row.amount_minor) }}
                        </p>
                        <Button
                            v-if="row.active && relation.can_manage"
                            variant="outline"
                            size="sm"
                            @click="emit('reverse', row.uuid)"
                            >{{ ui('Storna compensazione') }}</Button
                        >
                    </template>
                </article>
            </div>
        </section>
    </div>
</template>
