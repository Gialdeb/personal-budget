<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/actions/App/Http/Controllers/CounterpartyPaymentController';
import { useUiCopy } from '@/i18n/ui-copy';
const ui = useUiCopy();
export type CommonRelation = {
    uuid: string;
    counterparty_uuid: string;
    name: string;
    currency: string;
    active: boolean;
    can_manage: boolean;
};
defineProps<{ relations: CommonRelation[]; shared?: boolean }>();
</script>
<template>
    <div v-if="relations.length" class="flex flex-wrap gap-2">
        <Link
            v-for="relation in relations"
            :key="relation.uuid"
            :href="
                show.url(relation.uuid, {
                    query: shared ? { scope: 'shared' } : {},
                })
            "
            class="rounded-lg border bg-card px-3 py-2 text-sm hover:bg-muted"
            >{{ relation.name }} · {{ relation.currency }} ·
            {{
                relation.active
                    ? relation.can_manage
                        ? ui('Saldo e pagamento comune')
                        : ui('Saldo e storico (sola lettura)')
                    : ui('Verifica e attiva gestione comune')
            }}</Link
        >
    </div>
</template>
