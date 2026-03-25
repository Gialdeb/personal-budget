<?php

namespace App\Policies;

use App\Models\AccountInvitation;
use App\Models\User;

class AccountInvitationPolicy
{
    public function view(User $user, AccountInvitation $invitation): bool
    {
        if ((int) $invitation->account->user_id === (int) $user->id) {
            return true;
        }

        return $user->email !== null
            && mb_strtolower($user->email) === mb_strtolower($invitation->email);
    }

    public function accept(User $user, AccountInvitation $invitation): bool
    {
        return $user->email !== null
            && mb_strtolower($user->email) === mb_strtolower($invitation->email);
    }
}
