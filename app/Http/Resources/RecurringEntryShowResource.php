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
            'entry' => (new RecurringEntryIndexResource($this->resource))->resolve(),
            'occurrences' => RecurringEntryOccurrenceResource::collection($occurrences)->resolve(),
            'summary' => [
                'total_occurrences' => $occurrences->count(),
                'pending_occurrences' => $pendingOccurrences->count(),
                'converted_occurrences' => $convertedOccurrences->count(),
                'converted_amount' => round((float) $convertedOccurrences->sum(fn ($occurrence) => (float) ($occurrence->expected_amount ?? 0)), 2),
                'remaining_amount' => round((float) $pendingOccurrences->sum(fn ($occurrence) => (float) ($occurrence->expected_amount ?? 0)), 2),
            ],
            'actions' => [
                'can_pause' => $this->status?->value === 'active',
                'can_resume' => $this->status?->value === 'paused',
                'can_cancel' => $this->status?->value !== 'cancelled',
                'has_converted_occurrences' => $convertedOccurrences->isNotEmpty(),
            ],
        ];
    }
}
