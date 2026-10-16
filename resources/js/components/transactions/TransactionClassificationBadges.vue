<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import {
    economicCategory,
    transactionAttributes,
    transactionTones,
} from '@/lib/transaction-presentation.js';
import type { MonthlyTransactionSheetTransaction } from '@/types';
const props = withDefaults(
    defineProps<{
        transaction: MonthlyTransactionSheetTransaction;
        compact?: boolean;
    }>(),
    { compact: false },
);
const { t } = useI18n();
const category = computed(() => economicCategory(props.transaction));
const primaryLabel = computed(() =>
    category.value === 'opening'
        ? t('transactions.sheet.grid.openingBadge')
        : category.value === 'income' || category.value === 'expense'
          ? props.transaction.direction_label
          : props.transaction.type,
);
const attributeLabels = computed(() => ({
    deleted: t('transactions.sheet.grid.deletedBadge'),
    refunded: t('transactions.sheet.classification.refunded'),
    shared: t('transactions.sheet.classification.shared'),
    recurring: t('transactions.sheet.grid.recurringBadge'),
}));
</script>
<template>
    <div class="contents">
        <Badge
            :class="[
                transactionTones[category].badge,
                compact
                    ? 'rounded-md border px-1.5 py-0.5 text-[10px]'
                    : 'rounded-full border px-2.5 py-1 text-[11px]',
            ]"
            :title="compact ? transaction.type : undefined"
            >{{ primaryLabel }}</Badge
        >
        <Badge
            v-if="
                !compact &&
                transaction.type &&
                transaction.type !== primaryLabel &&
                !transaction.is_opening_balance &&
                transaction.kind !== 'refund'
            "
            class="rounded-full border px-2.5 py-1 text-[11px]"
            :class="transactionTones.neutral.badge"
            >{{ transaction.type }}</Badge
        >
        <Badge
            v-for="attribute in transactionAttributes(transaction)"
            :key="attribute"
            class="rounded-full border px-2 py-0.5 text-[10px]"
            :class="transactionTones[attribute].badge"
            >{{ attributeLabels[attribute] }}</Badge
        >
        <template v-if="(transaction.recurring_payment_links?.length ?? 0) > 1">
            <Link
                v-for="link in transaction.recurring_payment_links"
                :key="link.occurrence_uuid"
                :href="link.show_url"
                class="rounded-full border border-sky-200 bg-sky-50 px-2 py-1 text-[11px] text-sky-800 underline-offset-2 hover:underline dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200"
                >{{ link.title }} · {{ link.due_date }}</Link
            >
        </template>
        <Badge
            v-if="transaction.is_credit_debt_transaction"
            class="rounded-full border px-2 py-0.5 text-[10px]"
            :class="transactionTones.neutral.badge"
            >{{ t('transactions.sheet.grid.creditDebtBadge') }}</Badge
        >
    </div>
</template>
