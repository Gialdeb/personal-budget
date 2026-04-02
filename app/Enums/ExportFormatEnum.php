<?php

namespace App\Enums;

enum ExportFormatEnum: string
{
    case CSV = 'csv';
    case JSON = 'json';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
