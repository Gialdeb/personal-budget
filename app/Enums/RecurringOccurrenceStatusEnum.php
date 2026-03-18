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

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Pianificata',
            self::DUE => 'In scadenza',
            self::MATCHED => 'Abbinata',
            self::SKIPPED => 'Saltata',
            self::CANCELLED => 'Annullata',
            self::CONVERTED => 'Convertita',
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
