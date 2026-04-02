<?php

namespace App\Enums;

enum ExportPeriodPresetEnum: string
{
    case ALL_TIME = 'all_time';
    case THIS_MONTH = 'this_month';
    case LAST_MONTH = 'last_month';
    case THIS_YEAR = 'this_year';
    case CUSTOM_RANGE = 'custom_range';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
