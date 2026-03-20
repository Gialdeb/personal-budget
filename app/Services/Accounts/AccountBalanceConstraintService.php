<?php

namespace App\Services\Accounts;

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountTypeCodeEnum;
use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Validation\ValidationException;

class AccountBalanceConstraintService
{
    public function defaultAllowNegativeBalance(AccountType $accountType): bool
    {
        return match ($accountType->code) {
            AccountTypeCodeEnum::CASH_ACCOUNT->value,
            AccountTypeCodeEnum::PAYMENT_ACCOUNT->value,
            AccountTypeCodeEnum::SAVINGS_ACCOUNT->value,
            AccountTypeCodeEnum::BUSINESS_ACCOUNT->value,
            AccountTypeCodeEnum::INVESTMENT_ACCOUNT->value,
            AccountTypeCodeEnum::PENSION_ACCOUNT->value => false,
            AccountTypeCodeEnum::CREDIT_CARD->value,
            AccountTypeCodeEnum::LOAN_ACCOUNT->value => true,
            default => $accountType->balance_nature === AccountBalanceNatureEnum::LIABILITY,
        };
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function allowsNegativeBalance(AccountType $accountType, ?array $settings = null): bool
    {
        if ($accountType->code === AccountTypeCodeEnum::CREDIT_CARD->value) {
            return true;
        }

        if ($accountType->code === AccountTypeCodeEnum::CASH_ACCOUNT->value) {
            return false;
        }

        $value = data_get($settings, 'allow_negative_balance');

        if (is_bool($value)) {
            return $value;
        }

        return $this->defaultAllowNegativeBalance($accountType);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $existingSettings
     * @return array<string, mixed>|null
     */
    public function normalizeSettings(AccountType $accountType, array $settings = [], array $existingSettings = []): ?array
    {
        $creditCardKeys = [
            'credit_limit',
            'linked_payment_account_id',
            'statement_closing_day',
            'payment_day',
            'auto_pay',
        ];

        $normalized = $existingSettings;

        if ($accountType->code === AccountTypeCodeEnum::CREDIT_CARD->value) {
            unset($normalized['allow_negative_balance']);

            foreach ($creditCardKeys as $key) {
                if (! array_key_exists($key, $settings)) {
                    continue;
                }

                $value = $settings[$key];

                if ($key === 'auto_pay') {
                    $normalized[$key] = (bool) $value;

                    continue;
                }

                if ($value === null || $value === '') {
                    unset($normalized[$key]);

                    continue;
                }

                $normalized[$key] = $key === 'credit_limit'
                    ? round((float) $value, 2)
                    : $value;
            }

            return $normalized === [] ? null : $normalized;
        }

        foreach ($creditCardKeys as $key) {
            unset($normalized[$key]);
        }

        foreach ($settings as $key => $value) {
            if (in_array($key, $creditCardKeys, true)) {
                continue;
            }

            if ($key === 'allow_negative_balance') {
                $normalized[$key] = $this->allowsNegativeBalance($accountType, [
                    'allow_negative_balance' => (bool) $value,
                ]);

                continue;
            }

            if ($value === null || $value === '') {
                unset($normalized[$key]);

                continue;
            }

            $normalized[$key] = $value;
        }

        if (! array_key_exists('allow_negative_balance', $normalized)) {
            $normalized['allow_negative_balance'] = $this->defaultAllowNegativeBalance($accountType);
        }

        if ($accountType->code === AccountTypeCodeEnum::CASH_ACCOUNT->value) {
            $normalized['allow_negative_balance'] = false;
        }

        return $normalized === [] ? null : $normalized;
    }

    public function ensureBalanceAllowed(Account $account, float $balance): void
    {
        $account->loadMissing('accountType:id,code,name,balance_nature');

        if (! $account->accountType instanceof AccountType) {
            return;
        }

        if ($account->accountType->code === AccountTypeCodeEnum::CREDIT_CARD->value) {
            $creditLimit = $this->creditLimit($account);

            if ($creditLimit !== null && abs(min($balance, 0.0)) > $creditLimit) {
                throw ValidationException::withMessages([
                    'amount' => 'La carta di credito supererebbe il limite disponibile.',
                ]);
            }

            return;
        }

        if (! $this->allowsNegativeBalance($account->accountType, $account->settings) && $balance < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Questo account non consente un saldo negativo.',
            ]);
        }
    }

    public function creditLimit(Account $account): ?float
    {
        $limit = data_get($account->settings, 'credit_limit');

        if (! is_numeric($limit)) {
            return null;
        }

        return round((float) $limit, 2);
    }
}
