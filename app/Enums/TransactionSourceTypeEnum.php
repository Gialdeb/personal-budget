<?php

namespace App\Enums;

enum TransactionSourceTypeEnum: string
{
    case IMPORT = 'import';
    case MANUAL = 'manual';
    case GENERATED = 'generated';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::IMPORT => 'Importazione',
            self::MANUAL => 'Manuale',
            self::GENERATED => 'Generata',
            self::ADJUSTMENT => 'Rettifica',
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
