<?php

namespace App\Enums;

enum RecurringEntryTypeEnum: string
{
    case RECURRING = 'recurring';
    case INSTALLMENT = 'installment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
