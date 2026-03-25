<script setup lang="ts">
import { Layers3 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import BudgetPlanningMobileRow from '@/components/budget-planning/BudgetPlanningMobileRow.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type {
    BudgetCellSaveState,
    BudgetPlanningMonth,
    BudgetPlanningSection,
} from '@/types';

defineProps<{
    months: BudgetPlanningMonth[];
    sections: BudgetPlanningSection[];
    currency: string;
    collapsedRows: string[];
    cellStates: Record<string, BudgetCellSaveState>;
    readonly?: boolean;
}>();
const { t } = useI18n();

const emit = defineEmits<{
    toggleRow: [rowUuid: string];
    saveCell: [
        payload: { categoryUuid: string; month: number; amount: number },
    ];
}>();

function sectionTone(sectionKey: string): string {
    return (
        {
            income: 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-500/25 dark:bg-emerald-500/8',
            expense:
                'border-slate-200/80 bg-slate-50/80 dark:border-white/10 dark:bg-slate-950/70',
            bill: 'border-cyan-200/80 bg-cyan-50/70 dark:border-cyan-500/25 dark:bg-cyan-500/8',
            debt: 'border-rose-200/80 bg-rose-50/70 dark:border-rose-500/25 dark:bg-rose-500/8',
            saving: 'border-violet-200/80 bg-violet-50/70 dark:border-violet-500/25 dark:bg-violet-500/8',
        }[sectionKey] ??
        'border-white/70 bg-white/85 dark:border-white/10 dark:bg-slate-950/70'
    );
}
</script>

<template>
    <div class="space-y-5 lg:hidden">
        <Card
            v-for="section in sections"
            :key="section.key"
            :class="cn('overflow-hidden shadow-sm', sectionTone(section.key))"
        >
            <CardHeader
                class="border-b border-slate-200/70 bg-slate-50/90 dark:border-white/10 dark:bg-slate-900/70"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle
                            class="text-base font-semibold text-slate-950 dark:text-white"
                        >
                            {{ section.label }}
                        </CardTitle>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ section.description }}
                        </p>
                    </div>

                    <div
                        class="rounded-full bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white dark:bg-white dark:text-slate-950"
                    >
                        {{ formatCurrency(section.total_raw, currency) }}
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 p-4">
                <div
                    class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-50 p-3 dark:bg-slate-900"
                >
                    <div
                        v-for="(value, index) in section.totals_by_month_raw"
                        :key="`${section.key}-${index}`"
                        class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 text-xs shadow-sm dark:bg-slate-950"
                    >
                        <span
                            class="font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{ months[index]?.short_label }}
                        </span>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                        >
                            {{ formatCurrency(value, currency) }}
                        </span>
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                >
                    <Layers3 class="size-4" />
                    {{ t('planning.grid.groupCategories') }}
                </div>

                <div class="space-y-3">
                    <BudgetPlanningMobileRow
                        v-for="row in section.rows"
                        :key="row.uuid"
                        :row="row"
                        :months="months"
                        :currency="currency"
                        :collapsed-rows="collapsedRows"
                        :cell-states="cellStates"
                        :readonly="readonly"
                        @toggle-row="emit('toggleRow', $event)"
                        @save-cell="emit('saveCell', $event)"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
