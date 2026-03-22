<?php

namespace App\Enums;

enum TransactionDirectionEnum: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::INCOME => __('app.enums.transaction_directions.income'),
            self::EXPENSE => __('app.enums.transaction_directions.expense'),
            self::TRANSFER => __('app.enums.transaction_directions.transfer'),
        };
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
