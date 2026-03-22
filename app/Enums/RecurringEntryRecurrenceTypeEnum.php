<?php

namespace App\Enums;

enum RecurringEntryRecurrenceTypeEnum: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';
    case CUSTOM = 'custom';

    public function translationKey(): string
    {
        return match ($this) {
            self::DAILY => 'transactions.enums.recurrence_frequency.daily',
            self::WEEKLY => 'transactions.enums.recurrence_frequency.weekly',
            self::MONTHLY => 'transactions.enums.recurrence_frequency.monthly',
            self::QUARTERLY => 'transactions.enums.recurrence_frequency.quarterly',
            self::YEARLY => 'transactions.enums.recurrence_frequency.yearly',
            self::CUSTOM => 'transactions.enums.recurrence_frequency.custom',
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
