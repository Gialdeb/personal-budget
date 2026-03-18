<?php

namespace App\Enums;

enum AccountBalanceSnapshotSourceTypeEnum: string
{
    case MANUAL = 'manual';
    case IMPORT = 'import';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manuale',
            self::IMPORT => 'Importazione',
            self::SYSTEM => 'Sistema',
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
