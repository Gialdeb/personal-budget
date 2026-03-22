<?php

namespace App\Enums;

enum RecurringOccurrenceStatusEnum: string
{
    case PLANNED = 'planned';
    case DUE = 'due';
    case MATCHED = 'matched';
    case SKIPPED = 'skipped';
    case CANCELLED = 'cancelled';
    case CONVERTED = 'converted';

    public function translationKey(): string
    {
        return match ($this) {
            self::PLANNED => 'transactions.enums.recurring_transaction_status.planned',
            self::DUE => 'transactions.enums.recurring_transaction_status.due',
            self::MATCHED => 'transactions.enums.recurring_transaction_status.matched',
            self::SKIPPED => 'transactions.enums.recurring_transaction_status.skipped',
            self::CANCELLED => 'transactions.enums.recurring_transaction_status.cancelled',
            self::CONVERTED => 'transactions.enums.recurring_transaction_status.converted',
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
