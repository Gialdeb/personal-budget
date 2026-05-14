<?php

namespace App\Enums;

enum CreditDebtTypeEnum: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
