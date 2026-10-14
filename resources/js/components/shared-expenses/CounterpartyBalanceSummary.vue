<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { useUiCopy } from '@/i18n/ui-copy';
const ui = useUiCopy();
const { locale } = useI18n();
defineProps<{
    balances: {
        currency: string;
        manual_cents: number;
        shared_cents: number;
        total_cents: number;
    }[];
}>();
function money(value: number, currency: string): string {
    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency,
        signDisplay: 'exceptZero',
    }).format(value / 100);
}
</script>
<template>
    <div class="grid gap-3 text-sm">
        <dl
            v-for="balance in balances"
            :key="balance.currency"
            class="grid gap-1.5"
        >
            <div class="flex flex-wrap justify-between gap-2">
                <dt>
                    {{ ui('Crediti/debiti manuali') }} · {{ balance.currency }}
                </dt>
                <dd>
                    <SensitiveValue
                        :value="money(balance.manual_cents, balance.currency)"
                    />
                </dd>
            </div>
            <div class="flex flex-wrap justify-between gap-2">
                <dt>{{ ui('Saldo spese condivise') }}</dt>
                <dd>
                    <SensitiveValue
                        :value="money(balance.shared_cents, balance.currency)"
                    />
                </dd>
            </div>
            <div
                class="flex flex-wrap justify-between gap-2 border-t pt-1.5 font-semibold"
            >
                <dt>{{ ui('Saldo complessivo') }}</dt>
                <dd>
                    <SensitiveValue
                        :value="money(balance.total_cents, balance.currency)"
                    />
                </dd>
            </div>
        </dl>
        <p class="text-xs text-muted-foreground">
            {{ ui('Positivo: ti deve denaro. Negativo: devi denaro.') }}
        </p>
    </div>
</template>
