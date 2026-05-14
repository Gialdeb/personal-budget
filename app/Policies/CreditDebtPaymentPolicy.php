<?php

namespace App\Policies;

use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\User;

class CreditDebtPaymentPolicy
{
    public function create(User $user, CreditDebtItem $creditDebtItem): bool
    {
        return $creditDebtItem->user_id === $user->id;
    }

    public function delete(User $user, CreditDebtPayment $creditDebtPayment): bool
    {
        return $creditDebtPayment->user_id === $user->id;
    }
}
