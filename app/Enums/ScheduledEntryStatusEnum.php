<?php

namespace App\Enums;

enum ScheduledEntryStatusEnum: string
{
    case PLANNED = 'planned';
    case DUE = 'due';
    case MATCHED = 'matched';
    case CONVERTED = 'converted';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Pianificata',
            self::DUE => 'In scadenza',
            self::MATCHED => 'Abbinata',
            self::CONVERTED => 'Convertita',
            self::CANCELLED => 'Annullata',
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
