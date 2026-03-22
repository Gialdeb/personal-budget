<?php

namespace App\Enums;

enum CategoryGroupTypeEnum: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case BILL = 'bill';
    case DEBT = 'debt';
    case SAVING = 'saving';
    case TAX = 'tax';
    case INVESTMENT = 'investment';
    case TRANSFER = 'transfer';

    public function translationKey(): string
    {
        return match ($this) {
            self::INCOME => 'app.enums.category_groups.income',
            self::EXPENSE => 'app.enums.category_groups.expense',
            self::BILL => 'app.enums.category_groups.bill',
            self::DEBT => 'app.enums.category_groups.debt',
            self::SAVING => 'app.enums.category_groups.saving',
            self::TAX => 'app.enums.category_groups.tax',
            self::INVESTMENT => 'app.enums.category_groups.investment',
            self::TRANSFER => 'app.enums.category_groups.transfer',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
