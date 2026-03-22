<?php

namespace App\Enums;

enum TransactionSourceTypeEnum: string
{
    case IMPORT = 'import';
    case MANUAL = 'manual';
    case GENERATED = 'generated';
    case ADJUSTMENT = 'adjustment';

    public function translationKey(): string
    {
        return match ($this) {
            self::IMPORT => 'transactions.enums.source_type.import',
            self::MANUAL => 'transactions.enums.source_type.manual',
            self::GENERATED => 'transactions.enums.source_type.generated',
            self::ADJUSTMENT => 'transactions.enums.source_type.adjustment',
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
