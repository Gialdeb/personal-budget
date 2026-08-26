<?php

namespace App\Http\Resources;

use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
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
        $canEdit = $entry !== null
            && in_array((int) $entry->account_id, is_array($editableAccountIds) ? $editableAccountIds : [], true);
        $canUpdatePendingAmount = in_array($this->status, [
            RecurringOccurrenceStatusEnum::PENDING,
            RecurringOccurrenceStatusEnum::GENERATED,
        ], true);
        $canUpdateConvertedAmount = $this->status === RecurringOccurrenceStatusEnum::COMPLETED
            && $this->convertedTransaction !== null
            && $this->convertedTransaction->recurring_entry_occurrence_id === $this->id
            && $this->convertedTransaction->refundTransaction === null;

        return [
            'uuid' => $this->uuid,
            'sequence_number' => $this->sequence_number,
            'expected_date' => $this->expected_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'expected_amount' => $this->expected_amount !== null ? (float) $this->expected_amount : null,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'can_update_amount' => $canEdit
                && (bool) $entry?->is_amount_variable
                && $entry?->entry_type === RecurringEntryTypeEnum::RECURRING
                && $this->matched_transaction_id === null
                && ($canUpdatePendingAmount || $canUpdateConvertedAmount)
                && (
                    $this->converted_transaction_id === null
                    || $canUpdateConvertedAmount
                ),
            'can_convert' => $canEdit
                && $this->converted_transaction_id === null
                && in_array($this->status?->value, ['pending', 'generated'], true),
            'can_skip' => $canEdit
                && $this->converted_transaction_id === null
                && $this->status?->value === 'pending',
            'can_cancel' => $canEdit
                && $this->converted_transaction_id === null
                && in_array($this->status?->value, ['pending', 'generated'], true),
            'can_undo_conversion' => $canEdit
                && $this->convertedTransaction !== null
                && $this->convertedTransaction->kind?->value === 'scheduled'
                && $this->convertedTransaction->refundTransaction === null,
            'converted_transaction' => $this->convertedTransaction === null ? null : [
                'uuid' => $this->convertedTransaction->uuid,
                'kind' => $this->convertedTransaction->kind?->value,
                'transaction_date' => $this->convertedTransaction->transaction_date?->toDateString(),
                'amount' => (float) $this->convertedTransaction->amount,
                'currency' => $this->convertedTransaction->currency,
                'show_url' => $this->transactionShowUrl($this->convertedTransaction),
                'is_refunded' => $this->convertedTransaction->refundTransaction !== null,
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
