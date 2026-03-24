<?php

namespace App\Enums;

enum RecurringOccurrenceStatusEnum: string
{
    case PENDING = 'pending';
    case GENERATED = 'generated';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function translationKey(): string
    {
        return match ($this) {
            self::PENDING => 'transactions.enums.recurring_transaction_status.pending',
            self::GENERATED => 'transactions.enums.recurring_transaction_status.generated',
            self::COMPLETED => 'transactions.enums.recurring_transaction_status.completed',
            self::SKIPPED => 'transactions.enums.recurring_transaction_status.skipped',
            self::CANCELLED => 'transactions.enums.recurring_transaction_status.cancelled',
            self::REFUNDED => 'transactions.enums.recurring_transaction_status.refunded',
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
