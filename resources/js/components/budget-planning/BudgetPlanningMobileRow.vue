<script setup lang="ts">
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import BudgetCellInput from '@/components/budget-planning/BudgetCellInput.vue';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type {
    BudgetCellSaveState,
    BudgetPlanningMonth,
    BudgetPlanningRow,
} from '@/types';

defineProps<{
    row: BudgetPlanningRow;
    months: BudgetPlanningMonth[];
    currency: string;
    collapsedRows: string[];
    cellStates: Record<string, BudgetCellSaveState>;
    readonly?: boolean;
}>();
const { t } = useI18n();

const emit = defineEmits<{
    toggleRow: [rowUuid: string];
    saveCell: [payload: { categoryUuid: string; month: number; amount: number }];
}>();

function cellKey(categoryUuid: string, month: number): string {
    return `${categoryUuid}:${month}`;
}
</script>

<template>
    <div class="space-y-3">
        <div
            :class="
                cn(
                    'rounded-2xl border p-4 shadow-sm',
                    row.has_children
                        ? 'border-slate-200/80 bg-slate-50/90 dark:border-white/10 dark:bg-slate-900/70'
                        : 'border-white/70 bg-white/90 dark:border-white/10 dark:bg-slate-950/70',
                )
            "
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p
                        class="truncate"
                        :class="
                            row.has_children
                                ? 'text-sm font-semibold uppercase tracking-[0.16em] text-slate-950 dark:text-white'
                                : 'text-base font-semibold text-slate-950 dark:text-white'
                        "
                    >
                        {{ row.name }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{
                            row.has_children
                                ? t('planning.grid.automaticSummary')
                                : row.full_path
                        }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <p class="text-right text-sm font-semibold text-slate-950 dark:text-white">
                        {{ formatCurrency(row.row_total_raw, currency) }}
                    </p>

                    <button
                        v-if="row.has_children"
                        type="button"
                        class="flex size-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-400"
                        @click="emit('toggleRow', row.uuid)"
                    >
                        <ChevronRight
                            v-if="collapsedRows.includes(row.uuid)"
                            class="size-4"
                        />
                        <ChevronDown
                            v-else
                            class="size-4"
                        />
                    </button>
                </div>
            </div>

            <div
                v-if="!row.has_children"
                class="mt-4 grid grid-cols-2 gap-3"
            >
                <div
                    v-for="month in months"
                    :key="`${row.uuid}-${month.value}`"
                    class="space-y-1"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                        {{ month.short_label }}
                    </p>
                    <BudgetCellInput
                        :amount-raw="row.monthly_amounts_raw[month.value - 1]"
                        :state="
                            cellStates[cellKey(row.uuid, month.value)] ?? 'idle'
                        "
                        :currency="currency"
                        :disabled="readonly"
                        @save="
                            emit('saveCell', {
                                categoryUuid: row.uuid,
                                month: month.value,
                                amount: $event,
                            })
                        "
                    />
                </div>
            </div>

            <div
                v-else
                class="mt-4 grid grid-cols-2 gap-2 rounded-2xl bg-white/80 p-3 dark:bg-slate-950/60"
            >
                <div
                    v-for="month in months"
                    :key="`${row.uuid}-summary-${month.value}`"
                    class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs dark:bg-slate-900"
                >
                    <span class="font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                        {{ month.short_label }}
                    </span>
                    <span class="font-semibold text-slate-900 dark:text-white">
                        {{
                            formatCurrency(
                                row.monthly_amounts_raw[month.value - 1],
                                currency,
                            )
                        }}
                    </span>
                </div>
            </div>
        </div>

        <div
            v-if="row.has_children && !collapsedRows.includes(row.uuid)"
            class="space-y-3 border-l border-dashed border-slate-300 pl-4 dark:border-slate-700"
        >
            <BudgetPlanningMobileRow
                v-for="child in row.children"
                :key="child.uuid"
                :row="child"
                :months="months"
                :currency="currency"
                :collapsed-rows="collapsedRows"
                :cell-states="cellStates"
                :readonly="readonly"
                @toggle-row="emit('toggleRow', $event)"
                @save-cell="emit('saveCell', $event)"
            />
        </div>
    </div>
</template>
