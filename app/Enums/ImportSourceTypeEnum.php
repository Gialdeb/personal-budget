<?php

namespace App\Enums;

enum ImportSourceTypeEnum: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case PDF = 'pdf';

    public function translationKey(): string
    {
        return match ($this) {
            self::CSV => 'imports.enums.source_file_type.csv',
            self::XLSX => 'imports.enums.source_file_type.xlsx',
            self::PDF => 'imports.enums.source_file_type.pdf',
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
