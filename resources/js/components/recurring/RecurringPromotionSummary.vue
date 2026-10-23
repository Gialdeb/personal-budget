<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type { RecurringPromotionInput, RecurringPromotion } from '@/types';
const props = defineProps<{
    promotion: RecurringPromotionInput | RecurringPromotion;
    currency: string;
    direction?: string | null;
    hideTransition?: boolean;
}>();
const { t, locale } = useI18n();
const key = 'recurringDetail.promotion.';
const income = computed(() => props.direction === 'income');
const difference = computed(
    () =>
        Math.round(Number(props.promotion.normal_amount) * 100) -
        Math.round(Number(props.promotion.amount) * 100),
);
const percentage = computed(() =>
    Number(props.promotion.amount) > 0
        ? (difference.value / (Number(props.promotion.amount) * 100)) * 100
        : null,
);
function money(value: string | number): string {
    return formatCurrency(Number(value), props.currency);
}
function date(value: string): string {
    return new Date(value + 'T12:00:00').toLocaleDateString(locale.value);
}
</script>
<template>
    <div class="min-w-0 space-y-2 text-sm" data-promotion-summary>
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-semibold">{{ t(key + 'summary') }}</h3>
            <span class="rounded-full bg-background/70 px-2 py-0.5 text-xs">{{
                t(key + 'source')
            }}</span>
        </div>
        <SensitiveValue
            class="block text-xl font-semibold tabular-nums"
            :value="
                t(key + (income ? 'incoming' : 'outgoing'), {
                    amount: money(promotion.amount),
                })
            "
        />
        <p v-if="promotion.duration === 'first_payment'">
            {{ t(key + (income ? 'incomeFirst' : 'first_payment')) }}
        </p>
        <p v-else-if="promotion.duration === 'payments'">
            {{
                t(key + (income ? 'incomeCount' : 'paymentCount'), {
                    count: promotion.payments ?? 1,
                })
            }}
        </p>
        <p v-else-if="promotion.until_date">
            {{ t(key + 'until') }} {{ date(promotion.until_date) }}
        </p>
        <p v-if="'starts_on' in promotion" class="text-xs opacity-75">
            {{ date(promotion.starts_on) }}
        </p>
        <template v-if="promotion.outcome === 'normal'">
            <p v-if="hideTransition">{{ t(key + 'manualDuplicate') }}</p>
            <template v-else>
                <SensitiveValue
                    class="block font-semibold"
                    :value="
                        t(key + (income ? 'afterIncoming' : 'afterOutgoing'), {
                            amount: money(promotion.normal_amount),
                        })
                    "
                />
                <p
                    v-if="difference !== 0 && Number.isFinite(difference)"
                    class="flex flex-wrap gap-2 text-xs"
                >
                    <span>{{ t(key + 'difference') }}</span
                    ><SensitiveValue
                        :value="
                            (difference > 0 ? '+' : '') +
                            money(difference / 100)
                        "
                    /><SensitiveValue
                        v-if="percentage !== null"
                        :value="
                            (percentage > 0 ? '+' : '') +
                            percentage.toLocaleString(locale, {
                                maximumFractionDigits: 2,
                            }) +
                            '%'
                        "
                    />
                </p>
            </template>
        </template>
        <p v-else>
            {{
                t(key + (promotion.outcome === 'keep' ? 'kept' : 'provisional'))
            }}
        </p>
    </div>
</template>
