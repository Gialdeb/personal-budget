<script setup lang="ts">
import { HandCoins, Pencil, UsersRound } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import type { MonthlyTransactionSheetTransaction } from '@/types/transactions';

defineProps<{
    movements: MonthlyTransactionSheetTransaction[];
    counterparties: { value: string; label: string }[];
    currency: string;
    canEdit: boolean;
}>();
const counterparty = defineModel<string>('counterparty', { required: true });
const emit = defineEmits<{
    edit: [movement: MonthlyTransactionSheetTransaction];
}>();
const { t, locale } = useI18n();
</script>

<template>
    <section
        aria-labelledby="shared-ledger-heading"
        class="border-t border-border bg-violet-50/30 px-4 py-4 sm:px-5 dark:bg-violet-500/5"
        data-test="shared-ledger-movements"
    >
        <header
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-2.5">
                <UsersRound
                    class="mt-0.5 size-4 shrink-0 text-violet-600 dark:text-violet-300"
                    aria-hidden="true"
                />
                <div>
                    <h2
                        id="shared-ledger-heading"
                        class="text-sm font-semibold"
                    >
                        {{ t('transactions.sharedLedger.title') }}
                        <span
                            class="ml-1 text-xs font-normal text-muted-foreground"
                            >{{ movements.length }}</span
                        >
                    </h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ t('transactions.sharedLedger.helper') }}
                    </p>
                </div>
            </div>
            <div class="flex min-w-0 items-center gap-2 sm:w-72">
                <span
                    id="shared-ledger-filter-label"
                    class="shrink-0 text-xs text-muted-foreground"
                    >{{ t('transactions.sharedLedger.advancedBy') }}</span
                >
                <SearchableSelect
                    v-model="counterparty"
                    :options="[
                        {
                            value: 'all',
                            label: t('transactions.sharedLedger.all'),
                        },
                        ...counterparties,
                    ]"
                    :placeholder="t('transactions.sharedLedger.all')"
                    :search-placeholder="t('transactions.sharedLedger.search')"
                    aria-labelledby="shared-ledger-filter-label"
                    trigger-class="h-9 min-w-0 rounded-lg text-xs"
                />
            </div>
        </header>
        <ul
            v-if="movements.length"
            class="mt-3 divide-y divide-border overflow-hidden rounded-xl border border-border bg-background"
        >
            <li
                v-for="movement in movements"
                :key="movement.uuid"
                class="flex items-start gap-3 p-3 sm:items-center sm:px-4"
                :data-ledger-uuid="movement.uuid"
            >
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-200"
                >
                    <HandCoins class="size-4" aria-hidden="true" />
                </div>
                <div
                    class="grid min-w-0 flex-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-4"
                >
                    <div class="min-w-0 space-y-1">
                        <div
                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <span class="text-sm font-semibold break-words">{{
                                movement.shared_expense_counterparty_label
                            }}</span>
                            <Badge
                                variant="outline"
                                class="border-violet-200 bg-violet-50 text-[10px] font-medium text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-200"
                                >{{
                                    t('transactions.sharedLedger.badge')
                                }}</Badge
                            >
                        </div>
                        <p class="text-sm break-words">
                            {{
                                movement.description ||
                                t('transactions.sharedLedger.untitled')
                            }}
                        </p>
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        >
                            <time :datetime="movement.date || undefined">{{
                                movement.date
                                    ? new Date(
                                          `${movement.date}T12:00:00`,
                                      ).toLocaleDateString(locale, {
                                          day: 'numeric',
                                          month: 'short',
                                          year: 'numeric',
                                      })
                                    : ''
                            }}</time>
                            <span v-if="movement.tracked_item_label"
                                >{{ t('transactions.sharedLedger.reference') }}:
                                {{ movement.tracked_item_label }}</span
                            >
                        </div>
                    </div>
                    <dl
                        class="flex flex-wrap gap-x-5 gap-y-1 text-xs sm:text-right"
                    >
                        <div>
                            <dt class="text-muted-foreground">
                                {{ t('transactions.sharedLedger.total') }}
                            </dt>
                            <dd class="mt-0.5 font-medium tabular-nums">
                                <SensitiveValue
                                    :value="
                                        formatCurrency(
                                            movement.amount_value_raw,
                                            movement.currency_code || currency,
                                        )
                                    "
                                />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                {{ t('transactions.sharedLedger.myShare') }}
                            </dt>
                            <dd
                                class="mt-0.5 text-sm font-semibold text-violet-800 tabular-nums dark:text-violet-200"
                            >
                                <SensitiveValue
                                    :value="
                                        formatCurrency(
                                            Number(
                                                movement.shared_expense_my_share ||
                                                    0,
                                            ),
                                            movement.currency_code || currency,
                                        )
                                    "
                                />
                            </dd>
                        </div>
                    </dl>
                </div>
                <Button
                    v-if="canEdit && movement.can_edit"
                    variant="ghost"
                    size="icon"
                    class="size-9 shrink-0 rounded-lg text-muted-foreground"
                    :aria-label="
                        t('transactions.sharedLedger.edit', {
                            title:
                                movement.description ||
                                t('transactions.sharedLedger.untitled'),
                        })
                    "
                    @click="emit('edit', movement)"
                >
                    <Pencil class="size-4" aria-hidden="true" />
                </Button>
            </li>
        </ul>
        <p v-else class="mt-2 text-xs text-muted-foreground" role="status">
            {{ t('transactions.sharedLedger.empty') }}
        </p>
    </section>
</template>
