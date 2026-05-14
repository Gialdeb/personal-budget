<?php

namespace App\Policies;

use App\Models\CreditDebtItem;
use App\Models\User;

class CreditDebtItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditDebtItem $creditDebtItem): bool
    {
        return $creditDebtItem->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CreditDebtItem $creditDebtItem): bool
    {
        return $creditDebtItem->user_id === $user->id;
    }

    public function delete(User $user, CreditDebtItem $creditDebtItem): bool
    {
        return $creditDebtItem->user_id === $user->id;
    }
}
