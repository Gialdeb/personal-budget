<?php

namespace App\Http\Resources;

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
            'occurrence_years' => $this->detail_summary !== null ? $this->occurrence_years : [],
            'occurrence_page' => $this->occurrence_page,
            'summary' => $this->detail_summary,
            'actions' => [
                'can_pause' => $canEdit && $this->status?->value === 'active',
                'can_resume' => $canEdit && in_array($this->status?->value, ['paused', 'cancelled'], true),
                'can_cancel' => $canEdit && $this->status?->value !== 'cancelled',
                'can_delete' => $canEdit && $this->salarySchedule === null,
                'has_converted_occurrences' => $this->detail_summary['converted_occurrences'] > 0,
            ],
        ];
    }
}
