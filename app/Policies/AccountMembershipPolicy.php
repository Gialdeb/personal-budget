<?php

namespace App\Policies;

use App\Models\AccountMembership;
use App\Models\User;

class AccountMembershipPolicy
{
    public function view(User $user, AccountMembership $membership): bool
    {
        if ((int) $membership->user_id === (int) $user->id) {
            return true;
        }

        return (int) $membership->account->user_id === (int) $user->id;
    }

    public function leave(User $user, AccountMembership $membership): bool
    {
        return (int) $membership->user_id === (int) $user->id;
    }

    public function revoke(User $user, AccountMembership $membership): bool
    {
        return (int) $membership->account->user_id === (int) $user->id;
    }

    public function updateRole(User $user, AccountMembership $membership): bool
    {
        return (int) $membership->account->user_id === (int) $user->id;
    }

    public function restore(User $user, AccountMembership $membership): bool
    {
        return (int) $membership->account->user_id === (int) $user->id;
    }
}
