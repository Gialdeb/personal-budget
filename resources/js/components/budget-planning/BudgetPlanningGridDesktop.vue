<script setup lang="ts">
import {
    ChevronDown,
    ChevronRight,
    FolderTree,
    Sigma,
    PanelTopClose,
    PanelTopOpen,
} from 'lucide-vue-next';
import { computed } from 'vue';
import BudgetCellInput from '@/components/budget-planning/BudgetCellInput.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';
import type {
    BudgetCellSaveState,
    BudgetPlanningMonth,
    BudgetPlanningRow,
    BudgetPlanningSection,
} from '@/types';

const props = defineProps<{
    months: BudgetPlanningMonth[];
    sections: BudgetPlanningSection[];
    currency: string;
    collapsedRows: number[];
    collapsedSections: string[];
    cellStates: Record<string, BudgetCellSaveState>;
    readonly?: boolean;
}>();

const emit = defineEmits<{
    toggleRow: [rowId: number];
    toggleSection: [sectionKey: string];
    saveCell: [payload: { categoryId: number; month: number; amount: number }];
}>();

const collapsedIds = computed(() => new Set(props.collapsedRows));
const collapsedSectionIds = computed(() => new Set(props.collapsedSections));

function visibleRows(section: BudgetPlanningSection) {
    return section.flat_rows.filter((row) =>
        row.ancestor_ids.every((ancestorId) => !collapsedIds.value.has(ancestorId)),
    );
}

function cellKey(categoryId: number, month: number): string {
    return `${categoryId}:${month}`;
}

function rowTone(row: Omit<BudgetPlanningRow, 'children'>): string {
    if (row.has_children) {
        return 'bg-slate-50/95 dark:bg-slate-900/90';
    }

    return 'bg-white/80 dark:bg-slate-950/60';
}

function sectionTone(sectionKey: string): string {
    return {
        income:
            'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-500/25 dark:bg-emerald-500/8',
        expense:
            'border-slate-200/80 bg-slate-50/80 dark:border-white/10 dark:bg-slate-900/70',
        bill: 'border-cyan-200/80 bg-cyan-50/70 dark:border-cyan-500/25 dark:bg-cyan-500/8',
        debt: 'border-rose-200/80 bg-rose-50/70 dark:border-rose-500/25 dark:bg-rose-500/8',
        saving:
            'border-violet-200/80 bg-violet-50/70 dark:border-violet-500/25 dark:bg-violet-500/8',
    }[sectionKey] ?? 'border-white/70 bg-white/80 dark:border-white/10 dark:bg-slate-950/70';
}

function sectionHeaderTone(sectionKey: string): string {
    return {
        income:
            'border-emerald-200/70 bg-emerald-50/90 dark:border-emerald-500/25 dark:bg-emerald-500/10',
        expense:
            'border-slate-200/70 bg-slate-50/90 dark:border-white/10 dark:bg-slate-900/70',
        bill: 'border-cyan-200/70 bg-cyan-50/90 dark:border-cyan-500/25 dark:bg-cyan-500/10',
        debt: 'border-rose-200/70 bg-rose-50/90 dark:border-rose-500/25 dark:bg-rose-500/10',
        saving:
            'border-violet-200/70 bg-violet-50/90 dark:border-violet-500/25 dark:bg-violet-500/10',
    }[sectionKey] ?? 'border-slate-200/70 bg-slate-50/90 dark:border-white/10 dark:bg-slate-900/70';
}
</script>

<template>
    <div class="hidden gap-6 lg:grid">
        <Card
            v-for="section in sections"
            :key="section.key"
            :class="
                cn(
                    'overflow-hidden shadow-sm backdrop-blur',
                    sectionTone(section.key),
                )
            "
        >
            <CardHeader
                :class="
                    cn(
                        'border-b pb-4',
                        sectionHeaderTone(section.key),
                    )
                "
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <CardTitle class="text-lg font-semibold text-slate-950 dark:text-white">
                            {{ section.label }}
                        </CardTitle>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ section.description }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-2 rounded-full bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white dark:bg-white dark:text-slate-950">
                            <FolderTree class="size-3.5" />
                            {{ visibleRows(section).length }} righe
                        </div>
                        <button
                            type="button"
                            class="flex h-9 items-center gap-2 rounded-full border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950 dark:border-white/10 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-white/20 dark:hover:text-white"
                            @click="emit('toggleSection', section.key)"
                        >
                            <PanelTopOpen
                                v-if="collapsedSectionIds.has(section.key)"
                                class="size-3.5"
                            />
                            <PanelTopClose
                                v-else
                                class="size-3.5"
                            />
                            {{
                                collapsedSectionIds.has(section.key)
                                    ? 'Espandi blocco'
                                    : 'Collassa blocco'
                            }}
                        </button>
                    </div>
                </div>
            </CardHeader>

            <CardContent
                v-if="!collapsedSectionIds.has(section.key)"
                class="p-0"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-white/95 dark:bg-slate-950/95">
                                <th class="sticky left-0 z-20 min-w-72 border-b border-slate-200/70 bg-white/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:border-white/10 dark:bg-slate-950/95 dark:text-slate-400">
                                    Categoria
                                </th>
                                <th
                                    v-for="month in months"
                                    :key="month.value"
                                    class="min-w-28 border-b border-slate-200/70 px-3 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:border-white/10 dark:text-slate-400"
                                >
                                    {{ month.short_label }}
                                </th>
                                <th class="sticky right-0 z-10 min-w-32 border-b border-slate-200/70 bg-white/95 px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:border-white/10 dark:bg-slate-950/95 dark:text-slate-400">
                                    Totale
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="row in visibleRows(section)"
                                :key="row.id"
                                :class="cn('transition', rowTone(row))"
                            >
                                <td
                                    class="sticky left-0 z-10 border-b border-slate-200/70 px-4 py-2.5 align-middle dark:border-white/10"
                                    :class="rowTone(row)"
                                >
                                    <div
                                        class="flex items-center gap-2"
                                        :style="{ paddingLeft: `${row.depth * 18}px` }"
                                    >
                                        <button
                                            v-if="row.has_children"
                                            type="button"
                                            class="flex size-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-950 dark:border-white/10 dark:bg-slate-950 dark:text-slate-400 dark:hover:border-white/20 dark:hover:text-white"
                                            @click="emit('toggleRow', row.id)"
                                        >
                                            <ChevronRight
                                                v-if="collapsedIds.has(row.id)"
                                                class="size-4"
                                            />
                                            <ChevronDown
                                                v-else
                                                class="size-4"
                                            />
                                        </button>
                                        <span
                                            v-else
                                            class="block size-2 rounded-full bg-slate-300 dark:bg-slate-700"
                                        />

                                        <div class="min-w-0">
                                            <p
                                                class="truncate"
                                                :class="
                                                    row.has_children
                                                        ? 'font-semibold uppercase tracking-[0.14em] text-slate-950 dark:text-white'
                                                        : 'font-medium text-slate-700 dark:text-slate-200'
                                                "
                                            >
                                                {{ row.name }}
                                            </p>
                                            <p class="truncate text-xs text-slate-400 dark:text-slate-500">
                                                {{
                                                    row.has_children
                                                        ? 'Riepilogo automatico'
                                                        : row.budget_type
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    v-for="month in months"
                                    :key="`${row.id}-${month.value}`"
                                    class="border-b border-slate-200/70 px-2 py-2 align-middle dark:border-white/10"
                                >
                                    <BudgetCellInput
                                        v-if="row.is_editable"
                                        :amount-raw="row.monthly_amounts_raw[month.value - 1]"
                                        :state="
                                            cellStates[
                                                cellKey(row.id, month.value)
                                            ] ?? 'idle'
                                        "
                                        :currency="currency"
                                        :disabled="readonly"
                                        dense
                                        @save="
                                            emit('saveCell', {
                                                categoryId: row.id,
                                                month: month.value,
                                                amount: $event,
                                            })
                                        "
                                    />
                                    <div
                                        v-else
                                        class="flex h-8 items-center justify-end rounded-lg px-2 text-sm font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            formatCurrency(
                                                row.monthly_amounts_raw[
                                                    month.value - 1
                                                ],
                                                currency,
                                            )
                                        }}
                                    </div>
                                </td>

                                <td
                                    class="sticky right-0 z-10 border-b border-slate-200/70 px-4 py-2 text-right font-semibold text-slate-950 dark:border-white/10 dark:text-white"
                                    :class="rowTone(row)"
                                >
                                    {{ formatCurrency(row.row_total_raw, currency) }}
                                </td>
                            </tr>
                        </tbody>

                        <tfoot>
                            <tr class="bg-slate-950 text-white dark:bg-white dark:text-slate-950">
                                <td class="sticky left-0 z-10 px-4 py-3" :class="'bg-inherit'">
                                    <div class="flex items-center gap-2 font-semibold uppercase tracking-[0.16em]">
                                        <Sigma class="size-4" />
                                        Totale {{ section.label }}
                                    </div>
                                </td>
                                <td
                                    v-for="(value, index) in section.totals_by_month_raw"
                                    :key="`${section.key}-${index}`"
                                    class="px-3 py-3 text-right text-sm font-semibold"
                                >
                                    {{ formatCurrency(value, currency) }}
                                </td>
                                <td class="sticky right-0 z-10 bg-inherit px-4 py-3 text-right text-sm font-semibold">
                                    {{ formatCurrency(section.total_raw, currency) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
