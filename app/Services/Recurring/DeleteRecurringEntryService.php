<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRecurringEntryService
{
    public function delete(RecurringEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->assertCanPreservePlan($entry);
            $entry->delete();
        });
    }

    public function restore(RecurringEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $entry = RecurringEntry::withTrashed()->lockForUpdate()->findOrFail($entry->id);
            if (! $entry->trashed()) {
                return;
            }
            $this->assertCanPreservePlan($entry);
            $entry->restore();
        });
    }

    protected function assertCanPreservePlan(RecurringEntry $entry): void
    {
        $entry->occurrences()->withHistoricalPlan()->lockForUpdate()->get(['id']);
        if ($entry->compoundComponent()->exists()) {
            throw ValidationException::withMessages(['entry' => __('compound_recurring.protected_component')]);
        }
        if ($entry->salarySchedule()->exists()) {
            throw ValidationException::withMessages(['entry' => __('salary.use_end_employment')]);
        }
    }
}
