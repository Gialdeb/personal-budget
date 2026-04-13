<?php

namespace App\Services\Accounts;

use App\Enums\AccountTypeCodeEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\User;
use App\Models\UserBank;
use App\Supports\Currency\CurrencySupport;

class AccountProvisioningService
{
    public const DEFAULT_CASH_ACCOUNT_NAME = 'Cassa contanti';

    public const BOOTSTRAP_CASH_ACCOUNT_MARKER = 'default_cash_bootstrap';

    public function __construct(
        protected AccountBalanceConstraintService $balanceConstraintService,
        protected CurrencySupport $currencySupport,
    ) {}

    public function ensureDefaultCashAccount(User $user): ?Account
    {
        $accountType = $this->resolveAccountType(AccountTypeCodeEnum::CASH_ACCOUNT);

        return Account::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'account_type_id' => $accountType->id,
                'name' => self::DEFAULT_CASH_ACCOUNT_NAME,
            ],
            [
                'bank_id' => null,
                'user_bank_id' => null,
                'scope_id' => null,
                'currency_code' => $this->userBaseCurrencyCode($user),
                'currency' => $this->userBaseCurrencyCode($user),
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_manual' => true,
                'is_active' => true,
                'settings' => [
                    ...$this->balanceConstraintService->normalizeSettings($accountType, [], []),
                    'provisioned_as' => self::BOOTSTRAP_CASH_ACCOUNT_MARKER,
                ],
            ]
        );
    }

    public function syncBootstrapCashAccountCurrency(User $user, string $currencyCode): void
    {
        $accountType = $this->resolveAccountType(AccountTypeCodeEnum::CASH_ACCOUNT);

        Account::query()
            ->where('user_id', $user->id)
            ->where('account_type_id', $accountType->id)
            ->with('accountType:id,code')
            ->withCount([
                'transactions',
                'recurringEntries',
                'scheduledEntries',
                'openingBalances',
                'imports',
                'reconciliations',
            ])
            ->get()
            ->first(fn (Account $account): bool => $this->isBootstrapCashAccount($account))
            ?->forceFill([
                'currency_code' => $currencyCode,
                'currency' => $currencyCode,
            ])
            ->save();
    }

    protected function isBootstrapCashAccount(Account $account): bool
    {
        $settings = is_array($account->settings) ? $account->settings : [];
        $wasProvisionedAsBootstrap = ($settings['provisioned_as'] ?? null) === self::BOOTSTRAP_CASH_ACCOUNT_MARKER;

        return $account->accountType?->code === AccountTypeCodeEnum::CASH_ACCOUNT->value
            && ($wasProvisionedAsBootstrap || $account->name === self::DEFAULT_CASH_ACCOUNT_NAME)
            && $account->name === self::DEFAULT_CASH_ACCOUNT_NAME
            && $account->bank_id === null
            && $account->user_bank_id === null
            && $account->scope_id === null
            && $account->iban === null
            && $account->account_number_masked === null
            && $account->notes === null
            && abs((float) ($account->opening_balance ?? 0)) < 0.005
            && abs((float) ($account->current_balance ?? 0)) < 0.005
            && (int) ($account->transactions_count ?? 0) === 0
            && (int) ($account->recurring_entries_count ?? 0) === 0
            && (int) ($account->scheduled_entries_count ?? 0) === 0
            && (int) ($account->opening_balances_count ?? 0) === 0
            && (int) ($account->imports_count ?? 0) === 0
            && (int) ($account->reconciliations_count ?? 0) === 0;
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
            'currency_code' => $this->userBaseCurrencyCode($user),
            'currency' => $this->userBaseCurrencyCode($user),
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

    protected function userBaseCurrencyCode(User $user): string
    {
        return $user->base_currency_code ?: $this->currencySupport->default();
    }
}
