<?php

namespace App\Http\Resources;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Services\Recurring\RecurringOccurrenceReadService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringEntry */
class RecurringEntryIndexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $editableAccountIds = $request->attributes->get('recurring_editable_account_ids', []);
        $canEdit = in_array((int) $this->account_id, is_array($editableAccountIds) ? $editableAccountIds : [], true);
        $occurrences = $this->detail_summary !== null ? collect() : $this->occurrences;
        $pendingOccurrences = $occurrences->filter(function ($occurrence): bool {
            return in_array($occurrence->status, [
                RecurringOccurrenceStatusEnum::PENDING,
                RecurringOccurrenceStatusEnum::GENERATED,
                RecurringOccurrenceStatusEnum::REFUNDED,
            ], true) && app(RecurringOccurrenceReadService::class)->remaining($occurrence) > 0 && ! $occurrence->isRetiredRefundedHistory();
        });
        $convertedOccurrences = $occurrences->filter(
            fn ($occurrence): bool => $occurrence->converted_transaction_id !== null
        );

        return [
            'uuid' => $this->uuid,
            'salary_url' => $this->salarySchedule ? route('salary-employments.show', $this->salarySchedule->employment->uuid) : null,
            'show_url' => route('recurring-entries.show', $this->uuid),
            'title' => $this->title,
            'description' => $this->description,
            'notes' => $this->notes,
            'currency' => $this->currency,
            'entry_type' => $this->entry_type?->value,
            'direction' => $this->direction?->value,
            'status' => $this->status?->value,
            'expected_amount' => $this->expected_amount !== null ? (float) $this->expected_amount : null,
            'is_amount_variable' => (bool) $this->is_amount_variable,
            'total_amount' => $this->total_amount !== null ? (float) $this->total_amount : null,
            'installments_count' => $this->installments_count,
            'recurrence_type' => $this->recurrence_type?->value,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_rule' => $this->recurrence_rule,
            'start_date' => $this->start_date?->toDateString(),
            'trial_duration' => $this->trial_duration,
            'trial_unit' => $this->trial_unit,
            'first_billing_date' => $this->firstBillingDate()->toDateString(),
            'trial_active' => (bool) $this->trial_duration && today()->lt($this->firstBillingDate()),
            'trial_locked' => (bool) $this->trial_duration && $this->occurrences()->where(function ($query): void {
                $query->whereNotNull('converted_transaction_id')->orWhereNotNull('matched_transaction_id')
                    ->orWhereHas('paymentAllocations', fn ($allocations) => $allocations->valid())
                    ->orWhereHas('sharedExpenseLedgerEntry')->orWhere('status', 'completed');
            })->exists(),
            'end_date' => $this->end_date?->toDateString(),
            'end_mode' => $this->end_mode?->value,
            'next_occurrence_date' => $this->next_occurrence_date?->toDateString(),
            'occurrences_limit' => $this->occurrences_limit,
            'auto_generate_occurrences' => (bool) $this->auto_generate_occurrences,
            'auto_create_transaction' => (bool) $this->auto_create_transaction,
            'has_activity_periods' => (bool) ($this->getAttribute('has_activity_periods') ?? $this->periods()->where('kind', 'reactivated')->exists()),
            'is_active' => (bool) $this->is_active,
            'reminder_days_before' => collect($this->reminder_days_before ?? [])
                ->map(fn ($days): int => (int) $days)
                ->sort()
                ->values()
                ->all(),
            'scope' => $this->scope === null ? null : [
                'uuid' => $this->scope->uuid,
                'name' => $this->scope->name,
            ],
            'account' => $this->account === null ? null : [
                'uuid' => $this->account->uuid,
                'name' => $this->account->name,
                'currency' => $this->account->currency,
            ],
            'category' => $this->category === null ? null : [
                'uuid' => $this->category->uuid,
                'name' => $this->category->displayName(),
            ],
            'tracked_item' => $this->trackedItem === null ? null : [
                'uuid' => $this->trackedItem->uuid,
                'name' => app(TrackedItemIdentityService::class)->presentation($this->trackedItem)['label'],
                'logo_url' => $this->trackedItem->logoUrl(),
            ],
            'shared_expense_counterparty' => $this->sharedExpenseCounterparty === null ? null : [
                'uuid' => $this->sharedExpenseCounterparty->uuid,
                'name' => $this->sharedExpenseCounterparty->name,
            ],
            'shared_expense_paid_by' => $this->shared_expense_paid_by,
            'shared_expense_my_share' => $this->shared_expense_my_share_cents === null ? null : number_format($this->shared_expense_my_share_cents / 100, 2, '.', ''),
            'shared_expense_counterparty_share' => $this->shared_expense_counterparty_share_cents === null ? null : number_format($this->shared_expense_counterparty_share_cents / 100, 2, '.', ''),
            'merchant' => $this->merchant === null ? null : [
                'uuid' => $this->merchant->uuid,
                'name' => $this->merchant->name,
            ],
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'created_by' => $this->createdByUser === null ? null : [
                'uuid' => $this->createdByUser->uuid,
                'name' => $this->createdByUser->name,
                'email' => $this->createdByUser->email,
            ],
            'updated_by' => $this->updatedByUser === null ? null : [
                'uuid' => $this->updatedByUser->uuid,
                'name' => $this->updatedByUser->name,
                'email' => $this->updatedByUser->email,
            ],
            'can_edit' => $canEdit,
            'stats' => $this->detail_summary !== null ? [
                ...$this->detail_summary,
                'remaining_occurrences' => $this->detail_summary['pending_occurrences'],
                'remaining_amount' => $this->entry_type?->value === 'installment' ? $this->detail_summary['remaining_amount'] : null,
                'can_realign_refunded_schedule' => $this->entry_type?->value === 'recurring' && ! $this->trial_duration && $this->detail_summary['can_realign_refunded_schedule'],
            ] : [
                'total_occurrences' => $occurrences->count(),
                'pending_occurrences' => $pendingOccurrences->count(),
                'converted_occurrences' => $convertedOccurrences->count(),
                'can_realign_refunded_schedule' => $this->entry_type?->value === 'recurring'
                    && ! $this->trial_duration
                    && $occurrences->contains(fn ($occurrence): bool => $occurrence->convertedTransaction?->refundTransaction !== null),
                'remaining_occurrences' => $pendingOccurrences->count(),
                'remaining_amount' => $this->entry_type?->value === 'installment'
                    ? round((float) $pendingOccurrences->sum(fn ($occurrence) => app(RecurringOccurrenceReadService::class)->remaining($occurrence) / 100), 2)
                    : null,
            ],
        ];
    }
}
