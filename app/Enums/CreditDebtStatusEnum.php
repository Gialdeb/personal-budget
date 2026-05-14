<?php

namespace App\Enums;

enum CreditDebtStatusEnum: string
{
    case OPEN = 'open';
    case PARTIAL = 'partial';
    case SETTLED = 'settled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
