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
    public function __construct(private readonly SharedOccurrenceCounterpartyResolver $counterparties) {}

    public function post(RecurringEntryOccurrence $occurrence, ?User $actor = null, array $metadata = []): SharedExpenseLedgerEntry
    {
        return DB::transaction(function () use ($occurrence, $actor, $metadata): SharedExpenseLedgerEntry {
            $occurrence = RecurringEntryOccurrence::query()
                ->with(['recurringEntry.sharedExpenseCounterparty', 'sharedExpenseLedgerEntry'])
                ->lockForUpdate()
                ->findOrFail($occurrence->getKey());
            $entry = $occurrence->recurringEntry()->lockForUpdate()->firstOrFail();
            $occurrence->setRelation('recurringEntry', $entry);

            abort_if($actor !== null && $entry->user_id !== $actor->id, 404);
            if ($occurrence->sharedExpenseLedgerEntry instanceof SharedExpenseLedgerEntry && ! isset($metadata['amount'])) {
                return $occurrence->sharedExpenseLedgerEntry;
            }

            $counterparty = $this->counterparties->forPosting($occurrence);
            if ($counterparty === null || ($metadata['shared_expense_paid_by'] ?? $occurrence->shared_expense_paid_by ?? $entry->shared_expense_paid_by) !== SharedExpenseAllocation::PAID_BY_COUNTERPARTY) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_is_not_a_shared_expense_paid_by_the_counterparty_1dbd4015')]);
            }

            $expectedCents = (int) round((float) $occurrence->expected_amount * 100);
            $paymentCents = isset($metadata['amount']) ? (int) round((float) $metadata['amount'] * 100) : $expectedCents;
            $totalCents = $paymentCents + $occurrence->externalPaymentCents();
            if ($paymentCents <= 0 || $paymentCents > $occurrence->remainingPaymentCents() || $expectedCents <= 0 || $occurrence->paymentAllocations()->exists() || ! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED, RecurringOccurrenceStatusEnum::REFUNDED], true)) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_cannot_be_recorded_in_its_current_state_57f7acb0')]);
            }
            $myShare = $occurrence->shared_expense_my_share_cents ?? $entry->shared_expense_my_share_cents ?? intdiv($expectedCents, 2);
            $myShareCents = intdiv($totalCents * $myShare, $expectedCents);
            $ledgerEntry = $occurrence->sharedExpenseLedgerEntry ?? new SharedExpenseLedgerEntry;
            $ledgerEntry->fill([
                'user_id' => $entry->user_id,
                'counterparty_id' => $counterparty->id,
                'recurring_entry_occurrence_id' => $occurrence->id,
                'entry_date' => $metadata['transaction_date'] ?? $occurrence->due_date ?? $occurrence->expected_date,
                'title' => $entry->title,
                'currency' => $entry->currency,
                'total_cents' => $totalCents,
                'paid_by' => SharedExpenseAllocation::PAID_BY_COUNTERPARTY,
                'my_share_cents' => $myShareCents,
                'counterparty_share_cents' => $totalCents - $myShareCents,
                'status' => 'recorded',
            ])->save();

            return $ledgerEntry;
        }, 5);
    }
}
