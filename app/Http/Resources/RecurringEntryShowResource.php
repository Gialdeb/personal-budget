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
        $canEdit = ! $this->resource->isArchivedComponent() && in_array((int) $this->account_id, is_array($editableAccountIds) ? $editableAccountIds : [], true);
        $occurrences = $this->occurrences;

        return [
            'entry' => (new RecurringEntryIndexResource($this->resource))->resolve(),
            'activity_periods' => $this->periods->map(fn ($period): array => [
                'id' => $period->id, 'kind' => $period->kind, 'recorded_on' => $period->created_at?->toDateString(), 'starts_on' => $period->starts_on?->toDateString(), 'ends_on' => $period->ends_on?->toDateString(),
                'ended_on' => $period->ended_at?->toDateString(), 'ended_reason' => $period->ended_reason, 'terms' => $period->terms,
            ])->values()->all(),
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
                'recurrence_comparison' => $change->recurrence_comparison,
            ])->values()->all(),
            'occurrences' => RecurringEntryOccurrenceResource::collection($occurrences)->resolve(),
            'occurrence_years' => $this->detail_summary !== null ? $this->occurrence_years : [],
            'occurrence_page' => $this->occurrence_page,
            'summary' => $this->detail_summary,
            'price_conditions' => $this->price_conditions,
            'cost_trend' => $this->cost_trend ?? [],
            'compound_cost_trend' => $this->compound_cost_trend,
            'manual_costs' => $this->entry_type?->value === 'recurring'
                ? $this->manualCosts->map(fn ($cost): array => [
                    'uuid' => $cost->uuid, 'date' => $cost->date->toDateString(),
                    'amount' => (float) $cost->amount, 'currency' => $cost->currency,
                ])->values()->all() : [],
            'actions' => [
                'can_reactivate' => $canEdit && $this->entry_type?->value === 'recurring' && $this->salarySchedule === null && in_array($this->status?->value, ['paused', 'cancelled'], true),
                'can_interrupt' => $canEdit && $this->entry_type?->value === 'recurring' && $this->salarySchedule === null && $this->status?->value === 'active',
                'can_recover_arrears' => $canEdit && in_array($this->status?->value, ['paused', 'cancelled'], true) && ! $this->periods->contains(fn ($period) => $period->kind === 'reactivated'),
                'can_pause' => $canEdit && $this->status?->value === 'active',
                'can_resume' => $canEdit && in_array($this->status?->value, ['paused', 'cancelled'], true),
                'can_cancel' => $canEdit && $this->status?->value !== 'cancelled',
                'can_delete' => $canEdit && $this->salarySchedule === null && $this->compoundComponent === null,
                'has_converted_occurrences' => $this->detail_summary['converted_occurrences'] > 0,
            ],
        ];
    }
}
