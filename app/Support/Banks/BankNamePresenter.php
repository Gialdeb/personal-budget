<?php

namespace App\Support\Banks;

use App\Models\Account;
use App\Models\Bank;

class BankNamePresenter
{
    public static function present(?Bank $bank, ?string $fallback = null): ?string
    {
        if ($bank !== null) {
            return $bank->presentableName();
        }

        return $fallback;
    }

    public static function forAccount(Account $account): ?string
    {
        return self::present(
            $account->userBank?->bank ?? $account->bank,
            $account->userBank?->name ?? $account->bank?->name,
        );
    }
}
