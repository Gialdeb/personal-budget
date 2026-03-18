<?php

namespace App\Enums;

enum CategoryDirectionTypeEnum: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';
    case MIXED = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Entrata',
            self::EXPENSE => 'Spesa',
            self::TRANSFER => 'Trasferimento',
            self::MIXED => 'Misto',
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
