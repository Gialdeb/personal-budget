<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { useUiCopy } from '@/i18n/ui-copy';
import { formatCurrency } from '@/lib/currency';
import type { RecurringEntryShowPayload } from '@/types';

defineProps<{
    payments: RecurringEntryShowPayload['occurrences'][number]['payments'];
    currency: string | null;
    history?: RecurringEntryShowPayload['occurrences'][number]['payment_history'];
    externalPayment?: RecurringEntryShowPayload['occurrences'][number]['external_payment'];
}>();
const ui = useUiCopy();
</script>

<template>
    <section
        v-if="payments?.length || externalPayment || history?.length"
        class="min-w-0 space-y-2 text-xs"
    >
        <Link
            v-if="externalPayment"
            :href="externalPayment.show_url"
            class="flex min-h-11 flex-wrap items-center gap-2 rounded-xl border border-border px-3 py-2 hover:bg-muted"
        >
            <span class="font-semibold"
                >{{ externalPayment.settled ? '✓ Pagata da' : 'Anticipata da' }}
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
            <Link
                :href="payment.show_url"
                class="inline-flex min-h-11 items-center gap-2"
                >{{ payment.date }} ·
                {{ formatCurrency(payment.amount, currency ?? 'EUR') }} ·
                {{
                    payment.refunded
                        ? 'Pagamento rimborsato'
                        : 'Pagamento precedente'
                }}</Link
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
    </section>
</template>
