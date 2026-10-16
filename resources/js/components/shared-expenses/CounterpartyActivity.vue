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
    revision_of_uuid?: string | null;
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
    obligations: (Claim & { title: string })[];
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
const obligationsByUuid = computed(
    () => new Map(props.obligations.map((claim) => [claim.uuid, claim])),
);
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
function title(row: Activity): string {
    if (row.kind === 'compensation') {
        return ui('Compensazione');
    }

    if (row.kind !== 'payment') {
        return row.title;
    }

    if (row.is_return) {
        return ui(
            row.direction === 'received'
                ? 'Rimborso ricevuto'
                : 'Rimborso effettuato',
        );
    }

    return ui(
        row.direction === 'received'
            ? 'Pagamento ricevuto'
            : 'Pagamento effettuato',
    );
}
function payer(row: Activity): string {
    if (row.kind === 'shared') {
        if (
            row.expense_total_minor === null ||
            BigInt(row.original_minor) === 0n
        ) {
            return ui('Quota riconosciuta · sorgente parziale');
        }

        return row.direction === 'received'
            ? ui('Hai pagato {amount}', {
                  amount: money(row.expense_total_minor),
              })
            : ui('{name} ha pagato {amount}', {
                  name: props.relation.name,
                  amount: money(row.expense_total_minor),
              });
    }

    if (row.kind === 'manual') {
        return ui(
            row.direction === 'received'
                ? 'Credito verso {name}'
                : 'Debito verso {name}',
            { name: props.relation.name },
        );
    }

    if (row.kind === 'compensation') {
        return ui('Senza movimento bancario');
    }

    if (row.kind !== 'payment') {
        return ui('Spesa condivisa');
    }

    return ui(
        row.is_return
            ? row.direction === 'received'
                ? '{name} ti ha restituito denaro'
                : 'Hai restituito denaro a {name}'
            : row.direction === 'received'
              ? '{name} ti ha pagato'
              : 'Hai pagato a {name}',
        { name: props.relation.name },
    );
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
            <ol class="divide-y rounded-2xl border bg-card">
                <li v-for="row in group.rows" :key="`${row.kind}-${row.uuid}`">
                    <article
                        class="grid min-w-0 grid-cols-[1.75rem_2rem_minmax(0,1fr)] gap-x-2 gap-y-1 px-3 py-3 sm:grid-cols-[2rem_2.5rem_minmax(0,1fr)_auto] sm:gap-x-3 sm:px-5"
                        :data-activity-kind="row.kind"
                        :data-activity-uuid="row.uuid"
                    >
                        <time
                            :datetime="row.date"
                            :aria-label="dateLabel(row.date)"
                            class="pt-1 text-center text-xs leading-5 text-muted-foreground"
                        >
                            <span class="block">{{
                                new Intl.DateTimeFormat(locale, {
                                    month: 'short',
                                }).format(new Date(`${row.date}T12:00:00`))
                            }}</span>
                            <span
                                class="block text-base font-medium tabular-nums"
                                >{{
                                    new Date(`${row.date}T12:00:00`).getDate()
                                }}</span
                            >
                        </time>
                        <span
                            :class="sourceTone(row)"
                            class="flex size-8 items-center justify-center rounded-xl sm:size-10"
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
                                class="size-4 sm:size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <div class="min-w-0 space-y-1">
                            <h4
                                class="text-sm font-semibold wrap-anywhere sm:text-base"
                            >
                                {{ title(row) }}
                            </h4>
                            <p
                                class="text-sm wrap-anywhere text-muted-foreground"
                            >
                                {{ payer(row) }}
                            </p>
                            <p
                                v-if="
                                    row.kind === 'shared' &&
                                    row.my_share_minor !== null
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{ ui('La tua quota') }}:
                                {{ money(row.my_share_minor) }}
                            </p>
                            <p
                                v-if="row.kind === 'manual'"
                                class="text-xs text-muted-foreground"
                            >
                                {{ ui('Importo credito/debito') }}:
                                {{ money(row.original_minor) }}
                            </p>
                            <span
                                v-if="
                                    row.kind === 'payment' &&
                                    row.revision_of_uuid
                                "
                                class="inline-flex rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                                >{{ ui('Revisione pagamento') }}</span
                            >
                        </div>
                        <div
                            class="col-start-3 flex min-w-0 flex-wrap items-baseline justify-between gap-x-3 gap-y-1 sm:col-start-4 sm:row-start-1 sm:block sm:max-w-56 sm:text-right"
                        >
                            <template
                                v-if="
                                    row.kind === 'shared' ||
                                    row.kind === 'manual'
                                "
                            >
                                <p
                                    class="text-xs font-medium"
                                    :class="
                                        row.state === 'settled'
                                            ? 'text-muted-foreground'
                                            : row.direction === 'received'
                                              ? 'text-emerald-700 dark:text-emerald-300'
                                              : 'text-orange-700 dark:text-orange-300'
                                    "
                                >
                                    {{
                                        ui(
                                            row.direction === 'received'
                                                ? 'Devi ricevere'
                                                : 'Devi restituire',
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-lg font-semibold whitespace-nowrap tabular-nums"
                                    :class="
                                        row.state === 'settled'
                                            ? 'text-muted-foreground'
                                            : row.direction === 'received'
                                              ? 'text-emerald-700 dark:text-emerald-300'
                                              : 'text-orange-700 dark:text-orange-300'
                                    "
                                >
                                    {{ money(row.remaining_minor) }}
                                </p>
                            </template>
                            <template v-else-if="row.kind === 'payment'">
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        ui(
                                            row.is_return
                                                ? 'Rimborso'
                                                : 'Pagamento',
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-lg font-semibold whitespace-nowrap tabular-nums"
                                    :class="
                                        row.state === 'cancelled'
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    "
                                >
                                    {{
                                        money(
                                            parseMinor(
                                                row.amount,
                                                relation.precision,
                                            ) ?? 0n,
                                        )
                                    }}
                                </p>
                                <p
                                    class="basis-full text-xs text-muted-foreground"
                                >
                                    <template
                                        v-if="row.state === 'cancelled'"
                                        >{{
                                            ui('Non incide sul saldo')
                                        }}</template
                                    >
                                    <template v-else
                                        >{{ ui('Effetto sul saldo') }}:
                                        {{
                                            row.direction === 'received'
                                                ? '−'
                                                : '+'
                                        }}{{
                                            money(row.effective_minor)
                                        }}</template
                                    >
                                </p>
                            </template>
                            <template v-else-if="row.kind === 'compensation'">
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        ui(
                                            row.active
                                                ? 'Saldo invariato'
                                                : 'Non incide sul saldo',
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-lg font-semibold whitespace-nowrap tabular-nums"
                                    :class="
                                        !row.active
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    "
                                >
                                    {{ money(row.amount_minor) }}
                                </p>
                            </template>
                        </div>
                        <details
                            class="col-start-3 min-w-0 text-sm sm:col-span-2 sm:col-start-3"
                        >
                            <summary
                                class="flex min-h-11 cursor-pointer flex-wrap items-center gap-2 text-xs font-medium text-muted-foreground hover:text-foreground"
                            >
                                {{ ui('Dettagli')
                                }}<span aria-hidden="true" class="ml-2">⌄</span>

                                <span
                                    v-if="row.state !== 'recorded'"
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="tone(row)"
                                    >{{ stateLabel(row.state) }}</span
                                >
                            </summary>
                            <div class="space-y-3 rounded-xl bg-muted/40 p-3">
                                <p
                                    v-if="row.kind === 'compensation'"
                                    class="col-start-3 text-xs text-muted-foreground sm:col-span-2 sm:col-start-3"
                                >
                                    {{
                                        ui(
                                            row.active
                                                ? 'Riduce credito e debito dello stesso importo, senza cambiare il saldo complessivo.'
                                                : 'Compensazione stornata: i residui sono stati riaperti.',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="
                                        row.kind === 'payment' &&
                                        BigInt(row.returned_minor) > 0n
                                    "
                                    class="col-start-3 text-xs text-muted-foreground sm:col-span-2 sm:col-start-3"
                                >
                                    {{
                                        ui(
                                            'La restituzione è mostrata separatamente nello storico.',
                                        )
                                    }}
                                </p>

                                <template
                                    v-if="
                                        row.kind === 'shared' ||
                                        row.kind === 'manual'
                                    "
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
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ ui('Importo totale') }}
                                            </dt>
                                            <dd
                                                class="mt-1 font-semibold whitespace-nowrap tabular-nums"
                                            >
                                                {{
                                                    money(
                                                        row.expense_total_minor,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                row.kind === 'shared' &&
                                                row.my_share_minor !== null
                                            "
                                        >
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ ui('La tua quota') }}
                                            </dt>
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{ money(row.my_share_minor) }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                row.kind === 'shared' &&
                                                row.counterparty_share_minor !==
                                                    null
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
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{
                                                    money(
                                                        row.counterparty_share_minor,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                row.kind === 'manual' ||
                                                row.expense_total_minor === null
                                            "
                                        >
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    row.kind === 'manual'
                                                        ? ui(
                                                              'Importo credito/debito',
                                                          )
                                                        : ui(
                                                              'Quota riconosciuta · sorgente parziale',
                                                          )
                                                }}
                                            </dt>
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{ money(row.original_minor) }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ ui('Rimborsato') }}
                                            </dt>
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{ money(row.paid_minor) }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    ui(
                                                        'Compensazioni registrate',
                                                    )
                                                }}
                                            </dt>
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{
                                                    money(row.compensated_minor)
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
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
                                    <div
                                        v-if="row.allocations.length"
                                        class="space-y-2"
                                    >
                                        <h5
                                            class="text-xs font-semibold text-muted-foreground"
                                        >
                                            {{
                                                ui(
                                                    'Spese e posizioni collegate',
                                                )
                                            }}
                                        </h5>
                                        <ul class="space-y-2">
                                            <li
                                                v-for="(
                                                    allocation, index
                                                ) in row.allocations"
                                                :key="`${allocation.obligation_uuid}-${index}`"
                                                class="rounded-lg border bg-card px-3 py-2 text-sm"
                                            >
                                                <p
                                                    class="font-medium wrap-anywhere"
                                                >
                                                    {{
                                                        obligationsByUuid.get(
                                                            allocation.obligation_uuid,
                                                        )?.title ??
                                                        ui(
                                                            'Posizione collegata',
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                                >
                                                    <span
                                                        v-if="
                                                            obligationsByUuid.has(
                                                                allocation.obligation_uuid,
                                                            )
                                                        "
                                                        >{{
                                                            dateLabel(
                                                                obligationsByUuid.get(
                                                                    allocation.obligation_uuid,
                                                                )!
                                                                    .economic_date,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        >{{
                                                            ui(
                                                                'Importo allocato',
                                                            )
                                                        }}:
                                                        <strong
                                                            class="font-semibold text-foreground tabular-nums"
                                                            >{{
                                                                money(
                                                                    parseMinor(
                                                                        allocation.amount,
                                                                        relation.precision,
                                                                    ) ?? 0n,
                                                                )
                                                            }}</strong
                                                        ></span
                                                    >
                                                    <span
                                                        v-if="
                                                            obligationsByUuid.has(
                                                                allocation.obligation_uuid,
                                                            )
                                                        "
                                                        >{{
                                                            ui(
                                                                'Residuo attuale',
                                                            )
                                                        }}:
                                                        {{
                                                            money(
                                                                obligationsByUuid.get(
                                                                    allocation.obligation_uuid,
                                                                )!
                                                                    .remaining_minor,
                                                            )
                                                        }}</span
                                                    >
                                                </p>
                                            </li>
                                        </ul>
                                    </div>
                                    <dl class="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ ui('Collegato:') }}
                                            </dt>
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{
                                                    money(
                                                        BigInt(
                                                            row.effective_minor,
                                                        ) -
                                                            BigInt(
                                                                row.returned_minor,
                                                            ) -
                                                            BigInt(
                                                                row.unallocated_minor,
                                                            ),
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ ui('Da collegare:') }}
                                            </dt>
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{
                                                    money(row.unallocated_minor)
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                BigInt(row.returned_minor) > 0n
                                            "
                                        >
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ ui('Restituito:') }}
                                            </dt>
                                            <dd
                                                class="mt-1 whitespace-nowrap tabular-nums"
                                            >
                                                {{ money(row.returned_minor) }}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p
                                        v-if="row.note"
                                        class="text-sm break-words"
                                    >
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
                                                v-if="
                                                    BigInt(
                                                        row.returned_minor,
                                                    ) === 0n
                                                "
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    emit('edit', row, 'revise')
                                                "
                                                >{{ ui('Revisiona') }}</Button
                                            >
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    emit(
                                                        'edit',
                                                        row,
                                                        'allocations',
                                                    )
                                                "
                                                >{{
                                                    ui('Ripartisci pagamento')
                                                }}</Button
                                            >
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    emit('edit', row, 'return')
                                                "
                                                >{{
                                                    ui('Restituzione reale')
                                                }}</Button
                                            >
                                        </template>
                                        <Button
                                            v-if="
                                                BigInt(row.returned_minor) ===
                                                0n
                                            "
                                            variant="outline"
                                            size="sm"
                                            @click="emit('cancel', row.uuid)"
                                            >{{
                                                ui(
                                                    'Annullamento amministrativo',
                                                )
                                            }}</Button
                                        >
                                    </div>
                                </template>
                                <template
                                    v-else-if="row.kind === 'compensation'"
                                >
                                    <Button
                                        v-if="row.active && relation.can_manage"
                                        variant="outline"
                                        size="sm"
                                        @click="emit('reverse', row.uuid)"
                                        >{{
                                            ui('Storna compensazione')
                                        }}</Button
                                    >
                                </template>
                            </div>
                        </details>
                    </article>
                </li>
            </ol>
        </section>
    </div>
</template>
