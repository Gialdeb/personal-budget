<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { useUiCopy } from '@/i18n/ui-copy';
import { formatCurrency } from '@/lib/currency';
import type { RecurringEntryShowPayload } from '@/types';

defineProps<{
    payments: RecurringEntryShowPayload['occurrences'][number]['payments'];
    currency: string | null;
}>();
const ui = useUiCopy();
</script>

<template>
    <section v-if="payments?.length" class="min-w-0 space-y-2 text-xs">
        <h4 class="font-semibold">{{ ui('Pagamenti') }}</h4>
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
