<?php

namespace App\Enums;

enum MerchantAliasMatchTypeEnum: string
{
    case CONTAINS = 'contains';
    case EQUALS = 'equals';
    case STARTS_WITH = 'starts_with';
    case REGEX = 'regex';

    public function label(): string
    {
        return match ($this) {
            self::CONTAINS => 'Contiene',
            self::EQUALS => 'Uguale',
            self::STARTS_WITH => 'Inizia con',
            self::REGEX => 'Espressione regolare',
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
