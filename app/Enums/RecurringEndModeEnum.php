<?php

namespace App\Enums;

enum RecurringEndModeEnum: string
{
    case NEVER = 'never';
    case AFTER_OCCURRENCES = 'after_occurrences';
    case UNTIL_DATE = 'until_date';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
