<?php

namespace App\Enums;

enum ImportFormatTypeEnum: string
{
    case GENERIC_CSV = 'generic_csv';
    case BANK_CSV = 'bank_csv';
    case BANK_PDF = 'bank_pdf';

    public function translationKey(): string
    {
        return match ($this) {
            self::GENERIC_CSV => 'imports.enums.format_type.generic_csv',
            self::BANK_CSV => 'imports.enums.format_type.bank_csv',
            self::BANK_PDF => 'imports.enums.format_type.bank_pdf',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }
}
