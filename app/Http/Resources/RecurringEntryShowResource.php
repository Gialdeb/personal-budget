<?php

namespace App\Http\Resources;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringEntry */
class RecurringEntryShowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $editableAccountIds = $request->attributes->get('recurring_editable_account_ids', []);
        $canEdit = in_array((int) $this->account_id, is_array($editableAccountIds) ? $editableAccountIds : [], true);
        $occurrences = $this->occurrences;
        $pendingOccurrences = $occurrences->filter(function ($occurrence): bool {
            return in_array($occurrence->status, [
                RecurringOccurrenceStatusEnum::PENDING,
                RecurringOccurrenceStatusEnum::GENERATED,
                RecurringOccurrenceStatusEnum::REFUNDED,
            ], true) && $occurrence->remainingPaymentCents() > 0 && ! $occurrence->isRetiredRefundedHistory();
        });
        $convertedOccurrences = $occurrences->filter(
            fn ($occurrence): bool => $occurrence->converted_transaction_id !== null
        );

        return [
            'entry' => (new RecurringEntryIndexResource($this->resource))->resolve(),
            'amount_history' => $this->amountChanges->map(fn ($change): array => [
                'id' => $change->id,
                'previous_amount' => (float) $change->previous_amount,
                'new_amount' => (float) $change->new_amount,
                'currency' => $change->currency,
                'effective_from' => $change->effective_from->toDateString(),
                'previous_until' => $change->effective_from->copy()->subDay()->toDateString(),
                'changed_at' => $change->created_at->toJSON(),
                'difference' => $change->difference(),
                'percentage' => $change->percentage(),
            ])->values()->all(),
            'occurrences' => RecurringEntryOccurrenceResource::collection($occurrences)->resolve(),
            'summary' => [
                ...$this->paidSummary(),
                'total_occurrences' => $occurrences->count(),
                'settled_occurrences' => $occurrences->filter(fn ($occurrence): bool => ($occurrence->externalPaymentCents() > 0 || $occurrence->converted_transaction_id !== null || $occurrence->matched_transaction_id !== null || $occurrence->allocatedPaymentCents() > 0) && $occurrence->remainingPaymentCents() === 0)->count(),
                'pending_occurrences' => $pendingOccurrences->count(),
                'converted_occurrences' => $convertedOccurrences->count(),
                'converted_amount' => round((float) $convertedOccurrences->sum(fn ($occurrence) => (float) ($occurrence->expected_amount ?? 0)), 2),
                'remaining_amount' => $pendingOccurrences->sum(fn ($occurrence): int => $occurrence->remainingPaymentCents()) / 100,
            ],
            'actions' => [
                'can_pause' => $canEdit && $this->status?->value === 'active',
                'can_resume' => $canEdit && in_array($this->status?->value, ['paused', 'cancelled'], true),
                'can_cancel' => $canEdit && $this->status?->value !== 'cancelled',
                'can_delete' => $canEdit && $this->salarySchedule === null,
                'has_converted_occurrences' => $convertedOccurrences->isNotEmpty(),
            ],
        ];
    }

    /** @return array{paid_count: int, paid_amount: float, paid_year: int|null} */
    protected function paidSummary(): array
    {
        $year = $this->entry_type?->value === 'installment' ? null : today()->year;
        $amount = 0;
        $count = 0;
        $seen = [];
        foreach ($this->occurrences as $occurrence) {
            $paidInPeriod = 0;
            foreach ($occurrence->personalSettlementTransactions()->with('refundTransaction')->get() as $transaction) {
                if (isset($seen[$transaction->id]) || $transaction->transaction_date->isAfter(today()) || ($year !== null && $transaction->transaction_date->year !== $year)) {
                    continue;
                }
                $paidInPeriod += $transaction->activeAmountCents();
                $seen[$transaction->id] = true;
            }
            $ledger = $occurrence->sharedExpenseLedgerEntry;
            if ($occurrence->externalPaymentCents() > 0 && $ledger->entry_date->lte(today()) && ($year === null || $ledger->entry_date->year === $year)) {
                $paidInPeriod += $occurrence->externalPaymentCents();
            }
            foreach ($occurrence->paymentAllocations as $allocation) {
                $transaction = $allocation->transaction;
                if (! $transaction || $transaction->status?->value !== 'confirmed' || $transaction->transaction_date->isAfter(today()) || $transaction->refundTransaction?->status?->value === 'confirmed' || ($year !== null && $transaction->transaction_date->year !== $year)) {
                    continue;
                }
                $paidInPeriod += $allocation->amount_cents;
            }
            $amount += $paidInPeriod;
            if ($paidInPeriod > 0 && $occurrence->remainingPaymentCents() === 0) {
                $count++;
            }
        }

        return ['paid_count' => $count, 'paid_amount' => $amount / 100, 'paid_year' => $year];
    }
}
