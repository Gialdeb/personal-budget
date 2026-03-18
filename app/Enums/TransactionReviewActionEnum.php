<?php

namespace App\Enums;

enum TransactionReviewActionEnum: string
{
    case CONFIRMED = 'confirmed';
    case CORRECTED = 'corrected';
    case IGNORED = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED => 'Confermata',
            self::CORRECTED => 'Corretta',
            self::IGNORED => 'Ignorata',
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
