<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Check,
    Info,
    LoaderCircle,
    Pencil,
    RefreshCcw,
    RotateCcw,
    Trash2,
    TriangleAlert,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { MonthlyTransactionSheetTransaction } from '@/types/transactions';

const props = defineProps<{
    transaction: MonthlyTransactionSheetTransaction;
    canEdit: boolean;
    processing: boolean;
    showAudit: boolean;
}>();
const emit = defineEmits<{ action: [name: string] }>();
const { t } = useI18n();
const actions = computed(() => {
    const row = props.transaction;
    const editable = props.canEdit;
    const blue =
        'text-blue-600 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-500/15';
    const orange =
        'text-orange-600 hover:bg-orange-50 dark:text-orange-300 dark:hover:bg-orange-500/15';
    const rose =
        'text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/15';

    return [
        {
            name: 'edit',
            show: editable && row.can_edit,
            icon: Pencil,
            label: t('transactions.sheet.actions.edit'),
            tone: blue,
        },
        {
            name: 'recurring',
            show: !!row.recurring_entry_show_url,
            href: row.recurring_entry_show_url,
            icon: ArrowUpRight,
            label: t('transactions.sheet.actions.openRecurring'),
            tone: blue,
        },
        {
            name: 'management',
            show:
                !!row.management_url &&
                row.management_url !== row.recurring_entry_show_url,
            href: row.management_url,
            icon: ArrowUpRight,
            label: row.management_label ?? '',
            tone: blue,
        },
        {
            name: 'restore',
            show: editable && row.can_restore,
            icon: RotateCcw,
            label: t('transactions.sheet.actions.restore'),
            tone: blue,
        },
        {
            name: 'forceDelete',
            show: editable && row.can_force_delete,
            icon: Trash2,
            label: t('transactions.sheet.actions.forceDelete'),
            tone: rose,
        },
        {
            name: 'undoRefund',
            show:
                editable &&
                (row.can_undo_refund || !!row.undo_refund_unavailable_reason),
            icon: RotateCcw,
            label: t('transactions.sheet.actions.undoRefund'),
            reason: row.undo_refund_unavailable_reason,
            tone: orange,
        },
        {
            name: 'confirmPayment',
            show: editable && row.can_confirm_payment,
            icon: Check,
            label: t('transactions.sheet.confirmRegistration'),
            tone: 'text-emerald-600 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/15',
        },
        {
            name: 'paymentProblem',
            show: editable && row.can_report_payment_problem,
            icon: TriangleAlert,
            label: t('transactions.sheet.paymentProblem.trigger'),
            tone: orange,
        },
        {
            name: 'refund',
            show: editable && row.can_refund,
            icon: RefreshCcw,
            label: t('transactions.sheet.actions.refund'),
            tone: orange,
        },
        {
            name: 'delete',
            show: editable && row.can_delete,
            icon: Trash2,
            label: t('transactions.sheet.actions.delete'),
            tone: rose,
        },
        {
            name: 'audit',
            show: props.showAudit,
            icon: Info,
            label: t('transactions.sheet.actions.auditInfo'),
            tone: 'text-slate-500 dark:text-slate-300',
        },
        {
            name: 'shared',
            show:
                editable &&
                row.can_edit &&
                !row.management_url &&
                !!row.shared_expense_counterparty_uuid,
            icon: UsersRound,
            label: t('transactions.sheet.actions.manageShared'),
            tone: 'text-violet-600 hover:bg-violet-50 dark:text-violet-300 dark:hover:bg-violet-500/15',
        },
    ].filter((action) => action.show);
});
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <div
            class="transaction-direct-actions flex flex-wrap justify-end gap-1"
        >
            <Tooltip v-for="action in actions" :key="action.name">
                <TooltipTrigger as-child>
                    <span
                        class="inline-flex"
                        :tabindex="action.reason ? 0 : undefined"
                        :aria-label="action.reason || undefined"
                    >
                        <Link
                            v-if="action.href"
                            :href="action.href"
                            :aria-label="action.label"
                            :title="action.label"
                            class="transaction-row-action"
                            :class="action.tone"
                        >
                            <ArrowUpRight class="size-4" aria-hidden="true" />
                        </Link>
                        <Button
                            v-else
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="transaction-row-action"
                            :class="action.tone"
                            :disabled="
                                !!action.reason ||
                                (processing && action.name !== 'audit')
                            "
                            :aria-label="action.label"
                            :title="action.reason || action.label"
                            @click="emit('action', action.name)"
                        >
                            <LoaderCircle
                                v-if="
                                    processing &&
                                    action.name === 'confirmPayment'
                                "
                                class="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                            <component
                                :is="action.icon"
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                        </Button>
                    </span>
                </TooltipTrigger>
                <TooltipContent
                    position-strategy="fixed"
                    :collision-padding="12"
                    class="max-w-64"
                    >{{ action.reason || action.label }}</TooltipContent
                >
            </Tooltip>
            <p
                v-if="transaction.undo_refund_unavailable_reason"
                class="basis-full text-left text-xs text-muted-foreground"
                role="note"
            >
                {{ transaction.undo_refund_unavailable_reason }}
            </p>
        </div>
    </TooltipProvider>
</template>

<style scoped>
.transaction-row-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    min-width: 32px;
    border-radius: 8px;
}
.transaction-row-action:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 2px;
}
@media (pointer: coarse), (max-width: 1279px) {
    .transaction-row-action {
        width: 44px;
        height: 44px;
        min-width: 44px;
    }
}
</style>
