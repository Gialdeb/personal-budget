<?php

namespace App\Enums;

enum AccountTypeCodeEnum: string
{
    case PAYMENT_ACCOUNT = 'payment_account';
    case SAVINGS_ACCOUNT = 'savings_account';
    case BUSINESS_ACCOUNT = 'business_account';
    case CREDIT_CARD = 'credit_card';
    case INVESTMENT_ACCOUNT = 'investment_account';
    case PENSION_ACCOUNT = 'pension_account';
    case CASH_ACCOUNT = 'cash_account';
    case LOAN_ACCOUNT = 'loan_account';

    public function translationKey(): string
    {
        return match ($this) {
            self::PAYMENT_ACCOUNT => 'accounts.enums.account_type.payment_account',
            self::SAVINGS_ACCOUNT => 'accounts.enums.account_type.savings_account',
            self::BUSINESS_ACCOUNT => 'accounts.enums.account_type.business_account',
            self::CREDIT_CARD => 'accounts.enums.account_type.credit_card',
            self::INVESTMENT_ACCOUNT => 'accounts.enums.account_type.investment_account',
            self::PENSION_ACCOUNT => 'accounts.enums.account_type.pension_account',
            self::CASH_ACCOUNT => 'accounts.enums.account_type.cash_account',
            self::LOAN_ACCOUNT => 'accounts.enums.account_type.loan_account',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }

    public function balanceNature(): AccountBalanceNatureEnum
    {
        return match ($this) {
            self::PAYMENT_ACCOUNT,
            self::SAVINGS_ACCOUNT,
            self::BUSINESS_ACCOUNT,
            self::INVESTMENT_ACCOUNT,
            self::PENSION_ACCOUNT,
            self::CASH_ACCOUNT => AccountBalanceNatureEnum::ASSET,

            self::CREDIT_CARD,
            self::LOAN_ACCOUNT => AccountBalanceNatureEnum::LIABILITY,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function seedData(): array
    {
        return array_map(
            fn (self $case) => [
                'code' => $case->value,
                'name' => $case->label(),
                'balance_nature' => $case->balanceNature()->value,
            ],
            self::cases()
        );
    }
}
