<?php

namespace App\Http\Resources;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
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
        $occurrences = $this->occurrences;
        $pendingOccurrences = $occurrences->filter(function ($occurrence): bool {
            return in_array($occurrence->status, [
                RecurringOccurrenceStatusEnum::PENDING,
                RecurringOccurrenceStatusEnum::GENERATED,
            ], true);
        });
        $convertedOccurrences = $occurrences->filter(
            fn ($occurrence): bool => $occurrence->converted_transaction_id !== null
        );

        return [
            'uuid' => $this->uuid,
            'show_url' => route('recurring-entries.show', $this->uuid),
            'title' => $this->title,
            'description' => $this->description,
            'notes' => $this->notes,
            'currency' => $this->currency,
            'entry_type' => $this->entry_type?->value,
            'direction' => $this->direction?->value,
            'status' => $this->status?->value,
            'expected_amount' => $this->expected_amount !== null ? (float) $this->expected_amount : null,
            'total_amount' => $this->total_amount !== null ? (float) $this->total_amount : null,
            'installments_count' => $this->installments_count,
            'recurrence_type' => $this->recurrence_type?->value,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_rule' => $this->recurrence_rule,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'end_mode' => $this->end_mode?->value,
            'next_occurrence_date' => $this->next_occurrence_date?->toDateString(),
            'occurrences_limit' => $this->occurrences_limit,
            'auto_generate_occurrences' => (bool) $this->auto_generate_occurrences,
            'auto_create_transaction' => (bool) $this->auto_create_transaction,
            'is_active' => (bool) $this->is_active,
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
                'name' => $this->trackedItem->name,
            ],
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
            'stats' => [
                'total_occurrences' => $occurrences->count(),
                'pending_occurrences' => $pendingOccurrences->count(),
                'converted_occurrences' => $convertedOccurrences->count(),
                'remaining_occurrences' => $pendingOccurrences->count(),
                'remaining_amount' => $this->entry_type?->value === 'installment'
                    ? round((float) $pendingOccurrences->sum(fn ($occurrence) => (float) ($occurrence->expected_amount ?? 0)), 2)
                    : null,
            ],
        ];
    }
}
