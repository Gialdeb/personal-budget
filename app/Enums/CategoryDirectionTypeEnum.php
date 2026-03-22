<?php

namespace App\Enums;

enum CategoryDirectionTypeEnum: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';
    case MIXED = 'mixed';

    public function translationKey(): string
    {
        return match ($this) {
            self::INCOME => 'app.enums.category_directions.income',
            self::EXPENSE => 'app.enums.category_directions.expense',
            self::TRANSFER => 'app.enums.category_directions.transfer',
            self::MIXED => 'app.enums.category_directions.mixed',
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
