<?php

namespace App\Enums;

enum TransactionStatusEnum: string
{
    case DRAFT = 'draft';
    case AUTO_CATEGORIZED = 'auto_categorized';
    case REVIEW_NEEDED = 'review_needed';
    case CONFIRMED = 'confirmed';
    case IGNORED = 'ignored';

    public function translationKey(): string
    {
        return match ($this) {
            self::DRAFT => 'transactions.enums.status.draft',
            self::AUTO_CATEGORIZED => 'transactions.enums.status.auto_categorized',
            self::REVIEW_NEEDED => 'transactions.enums.status.review_needed',
            self::CONFIRMED => 'transactions.enums.status.confirmed',
            self::IGNORED => 'transactions.enums.status.ignored',
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
