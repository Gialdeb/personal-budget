<?php

namespace App\Http\Resources;

use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Services\Recurring\RecurringOccurrenceReadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringEntryOccurrence */
class RecurringEntryOccurrenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $editableAccountIds = $request->attributes->get('recurring_editable_account_ids', []);
        $entry = $this->recurringEntry;
        $aggregateRefundedIds = $this->read_aggregate_refund_transactions ?? [];
        $canEdit = $entry !== null && ! $entry->isArchivedComponent()
            && in_array((int) $entry->account_id, is_array($editableAccountIds) ? $editableAccountIds : [], true);
        $canUpdatePendingAmount = in_array($this->status, [
            RecurringOccurrenceStatusEnum::PENDING,
            RecurringOccurrenceStatusEnum::GENERATED,
        ], true);
        $canUpdateConvertedAmount = $this->status === RecurringOccurrenceStatusEnum::COMPLETED
            && $this->convertedTransaction !== null
            && $this->convertedTransaction->recurring_entry_occurrence_id === $this->id
            && $this->convertedTransaction->refundTransaction === null;
        $canUpdateOccurrence = $canEdit
            && $this->matched_transaction_id === null
            && $this->converted_transaction_id === null
            && in_array($this->status, [
                RecurringOccurrenceStatusEnum::PENDING,
                RecurringOccurrenceStatusEnum::GENERATED,
            ], true);
        $account = $this->account ?? $entry?->account;
        $isDue = in_array($this->status?->value, ['pending', 'generated', 'refunded'], true)
            && ($this->due_date ?? $this->expected_date)?->lte(today());
        $requiresAmountConfirmation = (bool) $entry?->is_amount_variable && ! $entry?->auto_create_transaction && $this->amount_confirmed_at === null && $this->converted_transaction_id === null && $this->matched_transaction_id === null;
        $hasAllocations = $this->resource->relationLoaded('paymentAllocations') ? $this->resource->paymentAllocations->isNotEmpty() : $this->resource->paymentAllocations()->exists();
        $allocatedCents = app(RecurringOccurrenceReadService::class)->paid($this->resource);
        $ledger = $this->sharedExpenseLedgerEntry;
        $remainingCents = app(RecurringOccurrenceReadService::class)->remaining($this->resource);
        $paidCents = 0;
        $payments = $this->resource->paymentAllocations->filter(fn ($allocation): bool => $allocation->transaction !== null
            && $allocation->transaction->status?->value === 'confirmed'
            && $allocation->transaction->refundTransaction?->status?->value !== 'confirmed')
            ->sortBy(fn ($allocation): string => $allocation->transaction->transaction_date->toDateString().'-'.str_pad((string) $allocation->id, 20, '0', STR_PAD_LEFT))
            ->map(function ($allocation) use (&$paidCents): array {
                $transaction = $allocation->transaction;
                $paidCents += $allocation->amount_cents;

                return [
                    'uuid' => $transaction->uuid,
                    'date' => $transaction->transaction_date->toDateString(),
                    'amount' => $allocation->amount_cents / 100,
                    'classification' => $paidCents >= (int) round((float) $this->expected_amount * 100) ? 'balance' : 'advance',
                    'account' => $transaction->account?->name,
                    'reference' => $transaction->trackedItem?->name,
                    'show_url' => $this->transactionShowUrl($transaction),
                ];
            })->values()->all();

        return [
            'uuid' => $this->uuid,
            'entry_uuid' => $entry?->uuid,
            'component_name' => $entry?->compoundComponent?->displayName(),
            'payment_defaults' => $entry?->compoundComponent === null ? null : [
                'category_uuid' => $entry->category?->uuid,
                'tracked_item_uuid' => $entry->trackedItem?->uuid,
                'shared_expense_paid_by' => $entry->shared_expense_paid_by,
                'shared_expense_counterparty' => $entry->sharedExpenseCounterparty === null ? null : [
                    'uuid' => $entry->sharedExpenseCounterparty->uuid,
                    'name' => $entry->sharedExpenseCounterparty->name,
                ],
            ],
            'sequence_number' => $this->sequence_number,
            'expected_date' => $this->expected_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'has_date_override' => $this->due_date !== null
                && ! $this->due_date->equalTo($this->expected_date),
            'resource' => $account === null ? null : [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'currency' => $account->currency,
                'is_overridden' => $this->account_id !== null,
            ],
            'expected_amount' => $this->expected_amount !== null ? (float) $this->expected_amount : null,
            'status' => $this->status?->value,
            'is_refunded' => $aggregateRefundedIds !== [] || $this->convertedTransaction?->refundTransaction !== null || $this->status?->value === 'refunded',
            'allocated_amount' => $allocatedCents / 100,
            'payments' => $payments,
            'payment_history' => ($this->resource->relationLoaded('settlementTransactions') ? $this->resource->settlementTransactions : $this->resource->settlementTransactions()->with('refundTransaction')->get())->concat(collect([$this->matchedTransaction])->filter())->unique('id')->where('id', '!=', $this->converted_transaction_id ?? 0)->sortBy('id')->values()->map(fn (Transaction $transaction): array => [
                'uuid' => $transaction->uuid, 'date' => $transaction->transaction_date->toDateString(),
                'amount' => (float) $transaction->amount, 'show_url' => $this->transactionShowUrl($transaction),
                'refunded' => $transaction->refundTransaction !== null,
                'refund_url' => $transaction->refundTransaction === null ? null : $this->transactionShowUrl($transaction->refundTransaction),
            ])->all(),
            'external_payment' => $ledger === null ? null : [
                'uuid' => $ledger->uuid,
                'payer' => $ledger->counterparty?->name,
                'amount' => $ledger->total_cents / 100,
                'date' => $ledger->entry_date?->toDateString(),
                'currency' => $ledger->currency,
                'settled' => $this->resource->externalPaymentCents() >= (int) round((float) $this->expected_amount * 100),
                'show_url' => route('shared-expenses.index', ['year' => $ledger->entry_date->year, 'month' => $ledger->entry_date->month, 'entry' => $ledger->uuid]),
            ],
            'can_register_payment' => $canEdit
                && $entry?->user_id === $request->user()?->id
                && $entry?->shared_expense_counterparty_id === null
                && ($this->read_can_register_payment ?? $this->resource->newQuery()->openObligations()->whereKey($this->id)->exists()),
            'remaining_amount' => $remainingCents / 100,
            'settlement_status' => $remainingCents === 0 ? 'settled' : ($allocatedCents > 0 ? 'partial' : ($isDue ? ($requiresAmountConfirmation ? 'amount_confirmation' : 'due') : null)),
            'automatic_posting_blocked_at' => $this->automatic_posting_blocked_at?->toJSON(),
            'payment_failed_at' => $this->payment_failed_at?->toJSON(),
            'notes' => $this->notes,
            'can_update_amount' => $canEdit
                && (! $entry?->salarySchedule || ! $hasAllocations)
                && (bool) $entry?->is_amount_variable
                && $entry?->entry_type === RecurringEntryTypeEnum::RECURRING
                && $this->matched_transaction_id === null
                && ($canUpdatePendingAmount || $canUpdateConvertedAmount)
                && (
                    $this->converted_transaction_id === null
                    || $canUpdateConvertedAmount
                ),
            'can_convert' => $canEdit
                && ! $hasAllocations
                && ($ledger === null || $remainingCents > 0)
                && $remainingCents > 0
                && ! $this->resource->isRetiredRefundedHistory()
                && in_array($this->status?->value, ['pending', 'generated', 'refunded'], true),
            'can_update_occurrence' => $canUpdateOccurrence,
            'can_skip' => $canEdit
                && $ledger === null
                && $allocatedCents === 0
                && $this->converted_transaction_id === null
                && $this->status?->value === 'pending',
            'can_cancel' => $canEdit
                && $ledger === null
                && $allocatedCents === 0
                && $this->converted_transaction_id === null
                && in_array($this->status?->value, ['pending', 'generated', 'refunded'], true),
            'can_restore' => $canEdit
                && $this->matched_transaction_id === null
                && $this->converted_transaction_id === null
                && $this->status?->value === 'skipped',
            'can_undo_conversion' => $canEdit && ($ledger !== null || ($this->convertedTransaction !== null
                && $this->convertedTransaction->kind?->value === 'scheduled'
                && $this->convertedTransaction->refundTransaction === null)),
            'converted_transaction' => $this->convertedTransaction === null ? null : [
                'payer' => $this->convertedTransaction->shared_expense_paid_by === 'self' ? 'Io' : null,
                'uuid' => $this->convertedTransaction->uuid,
                'kind' => $this->convertedTransaction->kind?->value,
                'transaction_date' => $this->convertedTransaction->transaction_date?->toDateString(),
                'amount' => (float) $this->convertedTransaction->amount,
                'currency' => $this->convertedTransaction->currency,
                'show_url' => $this->transactionShowUrl($this->convertedTransaction),
                'is_refunded' => $this->convertedTransaction->refundTransaction !== null || in_array((int) $this->converted_transaction_id, $aggregateRefundedIds, true),
                'can_refund' => $canEdit
                    && $this->canRefundFromRecurringContext()
                    && in_array($this->convertedTransaction->kind?->value, ['manual', 'scheduled'], true)
                    && $this->convertedTransaction->refundTransaction === null,
                'refund_transaction' => $this->convertedTransaction->refundTransaction === null ? null : [
                    'uuid' => $this->convertedTransaction->refundTransaction->uuid,
                    'transaction_date' => $this->convertedTransaction->refundTransaction->transaction_date?->toDateString(),
                    'show_url' => $this->transactionShowUrl($this->convertedTransaction->refundTransaction),
                ],
            ],
        ];
    }

    protected function transactionShowUrl(Transaction $transaction): ?string
    {
        if ($transaction->transaction_date === null) {
            return null;
        }

        return route('transactions.show', [
            'year' => $transaction->transaction_date->year,
            'month' => $transaction->transaction_date->month,
            'highlight' => $transaction->uuid,
            'source' => 'recurring',
        ]);
    }

    protected function canRefundFromRecurringContext(): bool
    {
        if ($this->convertedTransaction === null) {
            return false;
        }

        if ($this->read_can_refund !== null) {
            return (bool) $this->read_can_refund;
        }

        $latestConvertedOccurrenceId = RecurringEntryOccurrence::query()
            ->where('recurring_entry_id', $this->recurring_entry_id)
            ->whereNotNull('converted_transaction_id')
            ->orderByRaw('COALESCE(due_date, expected_date) desc')
            ->orderByDesc('sequence_number')
            ->orderByDesc('id')
            ->value('id');

        return $latestConvertedOccurrenceId === $this->id;
    }
}
