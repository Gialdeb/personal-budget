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

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Giornaliera',
            self::WEEKLY => 'Settimanale',
            self::MONTHLY => 'Mensile',
            self::QUARTERLY => 'Trimestrale',
            self::YEARLY => 'Annuale',
            self::CUSTOM => 'Personalizzata',
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
