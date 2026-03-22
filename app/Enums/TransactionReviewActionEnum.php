<?php

namespace App\Enums;

enum TransactionReviewActionEnum: string
{
    case CONFIRMED = 'confirmed';
    case CORRECTED = 'corrected';
    case IGNORED = 'ignored';

    public function translationKey(): string
    {
        return match ($this) {
            self::CONFIRMED => 'transactions.enums.review_status.confirmed',
            self::CORRECTED => 'transactions.enums.review_status.corrected',
            self::IGNORED => 'transactions.enums.review_status.ignored',
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
