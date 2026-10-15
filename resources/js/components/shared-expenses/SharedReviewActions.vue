<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useUiCopy } from '@/i18n/ui-copy';
const props = defineProps<{
    reason?: string | null;
    originUrl?: string | null;
    canPreparePayments: boolean;
    processing?: boolean;
}>();
const emit = defineEmits<{ reference: []; payments: [] }>();
const ui = useUiCopy();
const explanations = {
    payment_document:
        'Il rimborso non corrisponde a un pagamento valido. Il documento originale o i suoi collegamenti richiedono una correzione protetta; confermare la persona non risolve il problema.',
    reference:
        'Scegli e conferma la persona a cui appartiene questa quota condivisa.',
    transaction:
        'La transazione non è confermata. Aprila, verifica i dati e salva.',
    payments:
        'Esistono rimborsi già registrati, ma non è ancora indicato quali quote regolano. Collega i rimborsi senza registrarli di nuovo.',
    sources:
        'Più movimenti rappresentano la stessa occorrenza. Non basta confermare un nome: verifica i documenti originali prima di correggere i collegamenti. I pagamenti e lo storico restano protetti.',
} as const;
const explanation = computed(
    () => explanations[props.reason as keyof typeof explanations],
);
</script>
<template>
    <div class="min-w-0 space-y-2">
        <p class="text-sm text-muted-foreground">
            {{ explanation ? ui(explanation) : '' }}
        </p>
        <Button
            v-if="reason === 'reference'"
            size="sm"
            variant="outline"
            @click="emit('reference')"
            >{{ ui('Conferma riferimento') }}</Button
        >
        <Button
            v-else-if="reason === 'payments' && canPreparePayments"
            :disabled="processing"
            size="sm"
            variant="outline"
            @click="emit('payments')"
            >{{ ui('Collega i rimborsi esistenti') }}</Button
        >
        <p v-else-if="reason === 'payments'" class="text-sm">
            {{
                ui(
                    'La ripartizione dei rimborsi richiede l’attivazione della gestione dei pagamenti per questo rapporto.',
                )
            }}
        </p>
        <Link
            v-if="originUrl && reason !== 'reference' && reason !== 'payments'"
            :href="originUrl"
            class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline"
            >{{
                reason === 'transaction'
                    ? ui('Apri e verifica la transazione')
                    : ui('Verifica il movimento originale')
            }}</Link
        >
    </div>
</template>
