<?php

namespace App\Enums;

enum ImportRowParseStatusEnum: string
{
    case PENDING = 'pending';
    case PARSED = 'parsed';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'In attesa',
            self::PARSED => 'Analizzata',
            self::SKIPPED => 'Saltata',
            self::FAILED => 'Fallita',
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
