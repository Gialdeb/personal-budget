<?php

namespace App\Services\Recurring;

use App\Models\Account;
use App\Models\RecurringEntry;

class SharedAccountRecurringConvergenceService
{
    public function ensureForAccount(Account $account): void
    {
        $ownerUserId = (int) $account->user_id;

        RecurringEntry::query()
            ->where('account_id', $account->id)
            ->where('user_id', '!=', $ownerUserId)
            ->update([
                'user_id' => $ownerUserId,
                'updated_at' => now(),
            ]);
    }
}
