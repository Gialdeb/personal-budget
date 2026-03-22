<?php

namespace App\Enums;

enum ImportRowParseStatusEnum: string
{
    case PENDING = 'pending';
    case PARSED = 'parsed';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';

    public function translationKey(): string
    {
        return match ($this) {
            self::PENDING => 'imports.enums.row_parse_status.pending',
            self::PARSED => 'imports.enums.row_parse_status.parsed',
            self::SKIPPED => 'imports.enums.row_parse_status.skipped',
            self::FAILED => 'imports.enums.row_parse_status.failed',
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
