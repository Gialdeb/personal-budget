<?php

namespace App\Services\Recurring;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\Account;
use App\Models\RecurringEntryOccurrence;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\UserYearService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRecurringOccurrenceService
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected UserYearService $userYearService,
    ) {}

    public function update(RecurringEntryOccurrence $occurrence, User $actor, string $dueDate, string $accountUuid): RecurringEntryOccurrence
    {
        return DB::transaction(function () use ($occurrence, $actor, $dueDate, $accountUuid): RecurringEntryOccurrence {
            $lockedOccurrence = RecurringEntryOccurrence::query()->with('recurringEntry.user')->lockForUpdate()->findOrFail($occurrence->getKey());

            $this->ensureCanBeUpdated($lockedOccurrence);

            $account = $this->accessibleAccountsQuery->editable($actor)->where('accounts.uuid', $accountUuid)->first();

            if (! $account instanceof Account) {
                throw ValidationException::withMessages(['account_uuid' => __('transactions.validation.account_unavailable')]);
            }

            $entry = $lockedOccurrence->recurringEntry;
            $this->userYearService->ensureDateYearIsOpen($entry->user, $dueDate, 'occurrence');

            $lockedOccurrence->forceFill([
                'due_date' => $dueDate,
                'account_id' => $account->id === $entry->account_id ? null : $account->id,
            ])->save();

            return $lockedOccurrence->fresh(['account', 'recurringEntry.account', 'convertedTransaction.refundTransaction']);
        });
    }

    protected function ensureCanBeUpdated(RecurringEntryOccurrence $occurrence): void
    {
        if ($occurrence->converted_transaction_id !== null || $occurrence->matched_transaction_id !== null || ! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED], true)) {
            throw ValidationException::withMessages(['occurrence' => __('transactions.validation.recurring_occurrence_locked')]);
        }
    }
}
