<?php

namespace App\Services\SharedExpenses;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedExpenseOccurrencePostingService
{
    public function post(RecurringEntryOccurrence $occurrence, ?User $actor = null): SharedExpenseLedgerEntry
    {
        return DB::transaction(function () use ($occurrence): SharedExpenseLedgerEntry {
            $occurrence = RecurringEntryOccurrence::query()
                ->with(['recurringEntry.sharedExpenseCounterparty', 'sharedExpenseLedgerEntry'])
                ->lockForUpdate()
                ->findOrFail($occurrence->getKey());
            $entry = $occurrence->recurringEntry;

            if ($occurrence->sharedExpenseLedgerEntry instanceof SharedExpenseLedgerEntry) {
                return $occurrence->sharedExpenseLedgerEntry;
            }

            if ($entry->shared_expense_counterparty_id === null || ($occurrence->shared_expense_paid_by ?? $entry->shared_expense_paid_by) !== SharedExpenseAllocation::PAID_BY_COUNTERPARTY) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_is_not_a_shared_expense_paid_by_the_counterparty_1dbd4015')]);
            }

            $totalCents = (int) round((float) $occurrence->expected_amount * 100);
            if (! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED], true)) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_cannot_be_recorded_in_its_current_state_57f7acb0')]);
            }
            $ledgerEntry = SharedExpenseLedgerEntry::query()->create([
                'user_id' => $entry->user_id,
                'counterparty_id' => $entry->shared_expense_counterparty_id,
                'recurring_entry_occurrence_id' => $occurrence->id,
                'entry_date' => $occurrence->due_date ?? $occurrence->expected_date,
                'title' => $entry->title,
                'currency' => $entry->currency,
                'total_cents' => $totalCents,
                'paid_by' => SharedExpenseAllocation::PAID_BY_COUNTERPARTY,
                'my_share_cents' => $occurrence->shared_expense_my_share_cents ?? intdiv($totalCents, 2),
                'counterparty_share_cents' => $occurrence->shared_expense_counterparty_share_cents ?? ($totalCents - intdiv($totalCents, 2)),
                'status' => 'recorded',
            ]);

            $occurrence->forceFill(['status' => RecurringOccurrenceStatusEnum::COMPLETED])->save();

            return $ledgerEntry;
        });
    }
}
