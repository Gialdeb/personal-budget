<?php

namespace App\Services\Accounts;

use App\Enums\AccountTypeCodeEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\User;
use App\Models\UserBank;

class AccountProvisioningService
{
    public function __construct(
        protected AccountBalanceConstraintService $balanceConstraintService
    ) {}

    public function ensureDefaultCashAccount(User $user): ?Account
    {
        $accountType = $this->resolveAccountType(AccountTypeCodeEnum::CASH_ACCOUNT);

        return Account::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'account_type_id' => $accountType->id,
                'name' => 'Cassa contanti',
            ],
            [
                'bank_id' => null,
                'user_bank_id' => null,
                'scope_id' => null,
                'currency' => 'EUR',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_manual' => true,
                'is_active' => true,
                'settings' => $this->balanceConstraintService->normalizeSettings($accountType, [], []),
            ]
        );
    }

    public function ensureBaseAccountForUserBank(User $user, UserBank $userBank): ?Account
    {
        $accountType = $this->resolveAccountType(AccountTypeCodeEnum::PAYMENT_ACCOUNT);

        $existingAccount = Account::query()
            ->where('user_id', $user->id)
            ->where('user_bank_id', $userBank->id)
            ->where('account_type_id', $accountType->id)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        if ($existingAccount instanceof Account) {
            return $existingAccount;
        }

        return Account::query()->create([
            'user_id' => $user->id,
            'bank_id' => $userBank->bank_id,
            'user_bank_id' => $userBank->id,
            'account_type_id' => $accountType->id,
            'scope_id' => null,
            'name' => $this->baseAccountName($userBank),
            'currency' => 'EUR',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_manual' => true,
            'is_active' => true,
            'settings' => $this->balanceConstraintService->normalizeSettings($accountType, [], []),
        ]);
    }

    protected function resolveAccountType(AccountTypeCodeEnum $accountTypeCode): AccountType
    {
        return AccountType::query()->firstOrCreate(
            ['code' => $accountTypeCode->value],
            [
                'name' => $accountTypeCode->label(),
                'balance_nature' => $accountTypeCode->balanceNature()->value,
            ]
        );
    }

    protected function baseAccountName(UserBank $userBank): string
    {
        return 'Conto '.$userBank->name;
    }
}
