<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { useUiCopy } from '@/i18n/ui-copy';
import { formatCurrency } from '@/lib/currency';
import {
    paymentReasonLabel,
    paymentStateLabel,
    paymentActionLabel,
} from '@/lib/payment-management';
import type { RecurringEntryShowPayload } from '@/types';
import type {
    PaymentManagement,
    PaymentTimelineSnapshot,
} from '@/types/dashboard';

const props = defineProps<{
    payments: RecurringEntryShowPayload['occurrences'][number]['payments'];
    currency: string | null;
    management?: PaymentManagement | null;
    occurrenceUrl?: string;
    sequenceNumber?: number;
    expectedDate?: string | null;
    history?: RecurringEntryShowPayload['occurrences'][number]['payment_history'];
    externalPayment?: RecurringEntryShowPayload['occurrences'][number]['external_payment'];
}>();
const ui = useUiCopy();
const { locale } = useI18n();
const currencyForEvent = computed(() => props.currency ?? 'EUR');
function eventDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
function calendarDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
    }).format(new Date(`${value.slice(0, 10)}T12:00:00`));
}
function timelineChanges(event: PaymentManagement['history'][number]) {
    const fields: {
        key: keyof PaymentTimelineSnapshot;
        label: Parameters<typeof ui>[0];
        amount?: boolean;
        date?: boolean;
    }[] = [
        { key: 'expected_amount', label: 'Importo previsto', amount: true },
        { key: 'expected_date', label: 'Data prevista', date: true },
        {
            key: 'estimated_date',
            label: 'Nuova data stimata (facoltativa)',
            date: true,
        },
        { key: 'reminder_date', label: 'Promemoria programmato', date: true },
        { key: 'actual_amount', label: 'Importo effettivo', amount: true },
        {
            key: 'actual_date',
            label: 'Data effettiva del pagamento',
            date: true,
        },
        { key: 'account', label: 'Conto' },
    ];

    return fields
        .filter(
            ({ key }) =>
                event.before?.[key] != null || event.after?.[key] != null,
        )
        .map((field) => {
            const format = (value: string | number | null | undefined) =>
                value == null
                    ? ui('Non impostato')
                    : field.date
                      ? calendarDate(String(value))
                      : field.amount
                        ? formatCurrency(Number(value), currencyForEvent.value)
                        : String(value);

            return {
                ...field,
                before: format(event.before?.[field.key]),
                after: format(event.after?.[field.key]),
                changed:
                    event.before?.[field.key] != null &&
                    event.before?.[field.key] !== event.after?.[field.key],
            };
        });
}
</script>

<template>
    <section
        v-if="
            payments?.length || externalPayment || history?.length || management
        "
        class="min-w-0 space-y-2 text-xs"
    >
        <Link
            v-if="externalPayment"
            :href="externalPayment.show_url"
            class="flex min-h-11 flex-wrap items-center gap-2 rounded-xl border border-border px-3 py-2 hover:bg-muted"
        >
            <span class="font-semibold"
                >{{
                    ui(externalPayment.settled ? 'Pagata da' : 'Anticipata da')
                }}
                {{ externalPayment.payer }}</span
            >
            <time :datetime="externalPayment.date">{{
                externalPayment.date
            }}</time>
            <SensitiveValue
                :value="
                    formatCurrency(
                        externalPayment.amount,
                        externalPayment.currency,
                    )
                "
            />
            <span class="text-primary"> {{ ui('Spesa condivisa →') }} </span>
        </Link>
        <div
            v-for="payment in history"
            :key="payment.uuid"
            class="rounded-xl border border-border px-3 py-2 text-muted-foreground"
        >
            <component
                :is="payment.rectified ? 'div' : Link"
                :href="payment.rectified ? undefined : payment.show_url"
                class="inline-flex min-h-11 items-center gap-2"
                ><time :datetime="payment.date">{{ payment.date }}</time>
                <SensitiveValue
                    :value="formatCurrency(payment.amount, currency ?? 'EUR')"
                />
                {{
                    payment.rectified
                        ? ui('Rettificato')
                        : payment.refunded
                          ? ui('Pagamento rimborsato')
                          : payment.status === 'confirmed'
                            ? ui('Pagamento registrato')
                            : payment.status === 'ignored'
                              ? ui('Pagamento ignorato')
                              : ui('Pagamento da confermare')
                }}
                <span>{{
                    [payment.reference, payment.account]
                        .filter(Boolean)
                        .join(' · ')
                }}</span></component
            >
            <Link
                v-if="payment.refund_url"
                :href="payment.refund_url"
                class="inline-flex min-h-11 items-center text-primary"
            >
                {{ ui('Apri rimborso →') }}
            </Link>
        </div>
        <h4 v-if="payments?.length" class="font-semibold">
            {{ ui('Pagamenti') }}
        </h4>
        <Link
            v-for="payment in payments"
            :key="payment.uuid"
            :href="payment.show_url"
            class="flex min-h-11 flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-border px-3 py-2 hover:bg-muted"
        >
            <time :datetime="payment.date">{{ payment.date }}</time>
            <SensitiveValue
                :value="formatCurrency(payment.amount, currency ?? 'EUR')"
            />
            <span class="font-semibold">{{
                ui(payment.classification === 'balance' ? 'Saldo' : 'Acconto')
            }}</span>
            <span class="min-w-0 break-words text-muted-foreground">{{
                [payment.reference, payment.account].filter(Boolean).join(' · ')
            }}</span>
        </Link>
        <div
            v-if="management"
            class="grid gap-3 rounded-xl border border-border p-3"
        >
            <dl class="grid grid-cols-2 gap-3">
                <div>
                    <dt class="text-muted-foreground">
                        {{ ui('Importo previsto') }}
                    </dt>
                    <dd>
                        <SensitiveValue
                            :value="
                                formatCurrency(
                                    management.expected_amount,
                                    currency ?? 'EUR',
                                )
                            "
                        />
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ ui('Data prevista') }}
                    </dt>
                    <dd>{{ management.expected_date }}</dd>
                </div>
                <div v-if="management.estimated_date">
                    <dt class="text-muted-foreground">
                        {{ ui('Nuova data stimata (facoltativa)') }}
                    </dt>
                    <dd>{{ management.estimated_date }}</dd>
                </div>
                <div v-if="management.actual_amount !== null">
                    <dt class="text-muted-foreground">
                        {{ ui('Importo effettivo') }}
                    </dt>
                    <dd>
                        <SensitiveValue
                            :value="
                                formatCurrency(
                                    management.actual_amount,
                                    currency ?? 'EUR',
                                )
                            "
                        />
                    </dd>
                </div>
                <div v-if="management.actual_date">
                    <dt class="text-muted-foreground">
                        {{ ui('Data effettiva') }}
                    </dt>
                    <dd>{{ management.actual_date }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ ui('Stato del pagamento') }}
                    </dt>
                    <dd>{{ ui(paymentStateLabel(management.state)) }}</dd>
                </div>
            </dl>
            <template v-if="management.history.length">
                <h4 class="font-semibold">{{ ui('Storico del pagamento') }}</h4>
                <ol class="relative grid gap-5 border-l border-border pl-5">
                    <li
                        v-for="event in management.history"
                        :key="event.id"
                        class="relative min-w-0 rounded-xl border border-border bg-muted/20 p-3 before:absolute before:top-4 before:-left-[1.6rem] before:size-2.5 before:rounded-full before:border-2 before:border-background before:bg-primary"
                    >
                        <p class="font-medium">
                            {{ ui(paymentReasonLabel(event.reason)) }}
                            <span
                                v-if="
                                    paymentReasonLabel(event.reason) !==
                                    paymentActionLabel(event.action)
                                "
                                class="ml-1 text-muted-foreground"
                                >·
                                {{ ui(paymentActionLabel(event.action)) }}</span
                            >
                        </p>
                        <time
                            :datetime="event.created_at"
                            class="text-muted-foreground"
                            >{{ ui('Evento registrato') }} ·
                            {{ eventDate(event.created_at) }}</time
                        >
                        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="change in timelineChanges(event)"
                                :key="change.key"
                                class="min-w-0"
                            >
                                <dt class="text-muted-foreground">
                                    {{ ui(change.label) }}
                                </dt>
                                <dd class="flex flex-wrap items-center gap-1.5">
                                    <template v-if="change.changed">
                                        <SensitiveValue
                                            v-if="change.amount"
                                            class="text-muted-foreground line-through"
                                            :value="change.before"
                                        />
                                        <span
                                            v-else
                                            class="text-muted-foreground line-through"
                                            >{{ change.before }}</span
                                        >
                                        <span aria-hidden="true">→</span>
                                    </template>
                                    <SensitiveValue
                                        v-if="change.amount"
                                        :value="change.after"
                                    />
                                    <span v-else>{{ change.after }}</span>
                                </dd>
                            </div>
                            <div v-if="event.rectified_amount != null">
                                <dt class="text-muted-foreground">
                                    {{ ui('Importo originario rettificato') }}
                                </dt>
                                <dd>
                                    <SensitiveValue
                                        :value="
                                            formatCurrency(
                                                Number(event.rectified_amount),
                                                currency ?? 'EUR',
                                            )
                                        "
                                    />
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="event.notes"
                            class="whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ event.notes }}
                        </p>
                    </li>
                </ol>
            </template>
        </div>
        <Link
            v-if="occurrenceUrl"
            :href="occurrenceUrl"
            class="inline-flex min-h-11 items-center text-primary"
        >
            {{
                ui('Occorrenza {number} · prevista il {date}', {
                    number: sequenceNumber ?? '',
                    date: expectedDate ?? '',
                })
            }}
        </Link>
    </section>
</template>
