<?php

namespace App\Policies;

use App\Enums\AccountMembershipStatusEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\User;

class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        if ((int) $account->user_id === (int) $user->id) {
            return true;
        }

        return AccountMembership::query()
            ->where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->where('status', AccountMembershipStatusEnum::ACTIVE)
            ->exists();
    }

    public function viewMembers(User $user, Account $account): bool
    {
        return (int) $account->user_id === (int) $user->id;
    }

    public function viewInvitations(User $user, Account $account): bool
    {
        return (int) $account->user_id === (int) $user->id;
    }

    public function invite(User $user, Account $account): bool
    {
        return (int) $account->user_id === (int) $user->id;
    }
}
