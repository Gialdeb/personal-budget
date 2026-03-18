<?php

namespace App\Enums;

enum TransactionStatusEnum: string
{
    case DRAFT = 'draft';
    case AUTO_CATEGORIZED = 'auto_categorized';
    case REVIEW_NEEDED = 'review_needed';
    case CONFIRMED = 'confirmed';
    case IGNORED = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Bozza',
            self::AUTO_CATEGORIZED => 'Categorizzata automaticamente',
            self::REVIEW_NEEDED => 'Da revisionare',
            self::CONFIRMED => 'Confermata',
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
