<?php

namespace App\Enums;

enum ScheduledEntryStatusEnum: string
{
    case PLANNED = 'planned';
    case DUE = 'due';
    case MATCHED = 'matched';
    case CONVERTED = 'converted';
    case CANCELLED = 'cancelled';

    public function translationKey(): string
    {
        return match ($this) {
            self::PLANNED => 'transactions.enums.recurring_transaction_occurrence_status.planned',
            self::DUE => 'transactions.enums.recurring_transaction_occurrence_status.due',
            self::MATCHED => 'transactions.enums.recurring_transaction_occurrence_status.matched',
            self::CONVERTED => 'transactions.enums.recurring_transaction_occurrence_status.converted',
            self::CANCELLED => 'transactions.enums.recurring_transaction_occurrence_status.cancelled',
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
