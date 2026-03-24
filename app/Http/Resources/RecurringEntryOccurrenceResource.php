<?php

namespace App\Http\Resources;

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
        return [
            'uuid' => $this->uuid,
            'sequence_number' => $this->sequence_number,
            'expected_date' => $this->expected_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'expected_amount' => $this->expected_amount !== null ? (float) $this->expected_amount : null,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'can_convert' => $this->converted_transaction_id === null
                && in_array($this->status?->value, ['pending', 'generated'], true),
            'can_skip' => $this->converted_transaction_id === null
                && $this->status?->value === 'pending',
            'can_cancel' => $this->converted_transaction_id === null
                && in_array($this->status?->value, ['pending', 'generated'], true),
            'can_undo_conversion' => $this->convertedTransaction !== null
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
                'can_refund' => $this->canRefundFromRecurringContext()
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
