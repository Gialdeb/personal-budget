<?php

namespace App\Services\Dashboard;

use App\Enums\TransactionKindEnum;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySourceLink;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only presentation of the existing mutation restrictions. */
class TransactionRowActionService
{
    /** @param Collection<int, Transaction> $transactions */
    public function prepare(Collection $transactions, array $editableAccountIds): void
    {
        $transfers = $transactions->filter(fn (Transaction $transaction): bool => (bool) $transaction->is_transfer);
        $pairs = $transfers->isEmpty() ? collect() : Transaction::withTrashed()->where(function ($query) use ($transfers): void {
            $query->whereIn('id', $transfers->pluck('related_transaction_id')->filter())->orWhereIn('related_transaction_id', $transfers->pluck('id'));
        })->get(['id', 'related_transaction_id', 'account_id']);
        foreach ($transfers as $transaction) {
            $pair = $pairs->firstWhere('id', $transaction->related_transaction_id) ?? $pairs->firstWhere('related_transaction_id', $transaction->id);
            $transaction->setAttribute('register_transfer_pair_is_read_only', $pair !== null && ! in_array((int) $pair->account_id, $editableAccountIds, true));
        }
        $allocatedAmount = DB::table('counterparty_allocation_entries')
            ->selectRaw('SUM(amount_minor)')
            ->whereColumn('obligation_id', 'counterparty_obligations.id');
        $allocatedShare = CounterpartySourceLink::query()
            ->selectRaw('1')
            ->join('counterparty_relations as shared_relation', 'shared_relation.id', '=', 'counterparty_source_links.relation_id')
            ->join('counterparty_obligations', function ($join): void {
                $join->on('counterparty_obligations.relation_id', '=', 'counterparty_source_links.relation_id')
                    ->on('counterparty_obligations.economic_identity', '=', 'counterparty_source_links.economic_identity');
            })
            ->where('counterparty_source_links.source_type', 'shared_transaction_share')
            ->whereColumn('counterparty_source_links.source_identity', 'transactions.uuid')
            ->whereNotNull('shared_relation.activated_at')
            ->where($allocatedAmount, '>', 0)
            ->limit(1);
        $states = DB::table('transactions')
            ->whereIn('transactions.id', $transactions->pluck('id'))
            ->leftJoin('counterparty_payments as payment', 'payment.transaction_id', '=', 'transactions.id')
            ->leftJoin('counterparty_relations as payment_relation', 'payment_relation.id', '=', 'payment.relation_id')
            ->select([
                'transactions.id as register_transaction_id',
                'payment.id', 'payment.transaction_id', 'payment.return_of_id', 'payment.legacy_source_type', 'payment.relation_id',
                'payment_relation.uuid as relation_uuid', 'payment_relation.user_id as relation_user_id',
                'payment_relation.activated_at as relation_activated_at',
            ])
            ->selectSub($allocatedShare, 'shared_is_allocated')
            ->get()->keyBy('register_transaction_id');
        foreach ($transactions as $transaction) {
            $state = $states->get($transaction->id);
            $payment = $state?->id === null ? null : (new CounterpartyPayment)->newFromBuilder((array) $state);
            if ($payment !== null) {
                $relation = $state->relation_uuid === null ? null : (new CounterpartyRelation)->newFromBuilder([
                    'id' => $state->relation_id,
                    'uuid' => $state->relation_uuid,
                    'user_id' => $state->relation_user_id,
                    'activated_at' => $state->relation_activated_at,
                ]);
                $payment->setRelation('relation', $relation);
            }
            $transaction->setRelation('registerCounterpartyPayment', $payment);
            $transaction->setAttribute('register_shared_is_allocated', $transaction->shared_expense_counterparty_id !== null && (bool) $state?->shared_is_allocated);
        }
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(Transaction $transaction, array $row, int $viewerId): array
    {
        $payment = ($transaction->relationLoaded('registerCounterpartyPayment') ? $transaction->getRelation('registerCounterpartyPayment') : null);
        $paymentOwned = $payment !== null && ($payment->legacy_source_type === null || $payment->relation?->activated_at !== null);
        $original = $transaction->refundedTransaction;
        $originalPayment = ($original?->relationLoaded('registerCounterpartyPayment') ? $original->getRelation('registerCounterpartyPayment') : null);
        $originalOwned = $originalPayment !== null && ($originalPayment->legacy_source_type === null || $originalPayment->relation?->activated_at !== null);
        $occurrence = $original?->recurringOccurrence;
        $automaticOccurrence = $transaction->recurringOccurrence;
        $canVerify = $row['can_report_payment_problem']
            && $transaction->kind === TransactionKindEnum::SCHEDULED
            && $automaticOccurrence?->matched_transaction_id === null;
        $row['can_confirm_payment'] = $canVerify && $row['requires_payment_confirmation'];
        $row['can_report_payment_problem'] = $canVerify && $transaction->matchedRecurringOccurrences->isEmpty();
        $row['can_edit'] = $row['can_edit']
            && $transaction->kind !== TransactionKindEnum::CREDIT_CARD_SETTLEMENT
            && ($transaction->recurring_entry_occurrence_id === null || ($transaction->payment_verification_started_at !== null && $row['type_key'] === $transaction->direction?->value));
        $undoBlocked = $original === null || $originalOwned
            || $original->creditDebtPayment !== null || $original->sharedExpenseSettlement !== null
            || ($occurrence !== null && (int) round((float) $transaction->amount * 100) > $occurrence->remainingPaymentCents());
        $row['can_undo_refund'] = $row['can_undo_refund'] && ! $undoBlocked && ! $paymentOwned;
        $row['undo_refund_unavailable_reason'] = null;
        $row['management_url'] = null;
        $row['management_label'] = null;

        $canonicalPayment = $paymentOwned ? $payment : ($originalOwned ? $originalPayment : null);
        if ($canonicalPayment?->relation !== null && $canonicalPayment->relation->user_id === $viewerId) {
            $row['management_url'] = route('counterparties.show', $canonicalPayment->relation->uuid);
            $row['management_label'] = __('transactions.row_actions.manage_counterparty');
        } elseif ($row['credit_debt_item_show_url'] !== null && $transaction->creditDebtPayment?->item?->user_id === $viewerId) {
            $row['management_url'] = $row['credit_debt_item_show_url'];
            $row['management_label'] = __('transactions.row_actions.manage_credit_debt');
        } elseif ($original?->creditDebtPayment?->item !== null && $original->creditDebtPayment->item->user_id === $viewerId) {
            $item = $original->creditDebtPayment->item;
            $row['management_url'] = route('credits-debts.index', ['year' => $item->due_date?->year ?? $original->transaction_date->year, 'month' => $item->due_date?->month ?? $original->transaction_date->month, 'selected' => $item->uuid]);
            $row['management_label'] = __('transactions.row_actions.manage_credit_debt');
        } elseif ($transaction->sharedExpenseSettlement !== null || $original?->sharedExpenseSettlement !== null) {
            $row['management_url'] = route('shared-expenses.index');
            $row['management_label'] = __('transactions.row_actions.manage_shared');
        } elseif ($undoBlocked && $occurrence?->recurringEntry !== null) {
            $row['management_url'] = route('recurring-entries.show', [$occurrence->recurringEntry->uuid, 'highlight' => $occurrence->uuid]);
            $row['management_label'] = __('transactions.row_actions.manage_recurring');
        }

        if ($paymentOwned) {
            foreach (['can_edit', 'can_delete', 'can_restore', 'can_force_delete', 'can_refund', 'can_confirm_payment', 'can_report_payment_problem'] as $capability) {
                $row[$capability] = false;
            }
        }

        if ($row['can_refund'] && $automaticOccurrence !== null) {
            $latestId = $automaticOccurrence->newQuery()->where('recurring_entry_id', $automaticOccurrence->recurring_entry_id)->whereNotNull('converted_transaction_id')->orderByRaw('COALESCE(due_date, expected_date) desc')->orderByDesc('sequence_number')->orderByDesc('id')->value('id');
            if ($latestId !== $automaticOccurrence->id) {
                $row['can_refund'] = false;
                $row['management_url'] ??= $row['recurring_entry_show_url'];
                $row['management_label'] ??= __('transactions.row_actions.manage_recurring');
            }
        }

        if ($transaction->register_shared_is_allocated) {
            $row['can_refund'] = false;
            $row['can_delete'] = false;
            $row['can_force_delete'] = false;
            $row['can_report_payment_problem'] = false;
            $row['management_url'] = route('shared-expenses.index', ['counterparty' => $transaction->sharedExpenseCounterparty->uuid]);
            $row['management_label'] = __('transactions.row_actions.manage_shared');
        }

        if ($transaction->kind === TransactionKindEnum::REFUND && ! $row['can_undo_refund'] && $row['management_url'] === null && ! $transaction->trashed()) {
            $row['undo_refund_unavailable_reason'] = __('transactions.row_actions.undo_unavailable');
        }

        return $row;
    }
}
