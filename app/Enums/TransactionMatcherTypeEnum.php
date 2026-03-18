<?php

namespace App\Enums;

enum TransactionMatcherTypeEnum: string
{
    case CONTAINS = 'contains';
    case EQUALS = 'equals';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';
    case REGEX = 'regex';
    case SIMILARITY = 'similarity';

    public function label(): string
    {
        return match ($this) {
            self::CONTAINS => 'Contiene',
            self::EQUALS => 'Uguale',
            self::STARTS_WITH => 'Inizia con',
            self::ENDS_WITH => 'Finisce con',
            self::REGEX => 'Espressione regolare',
            self::SIMILARITY => 'Somiglianza',
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
