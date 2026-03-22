<?php

namespace App\Enums;

enum MerchantAliasMatchTypeEnum: string
{
    case CONTAINS = 'contains';
    case EQUALS = 'equals';
    case STARTS_WITH = 'starts_with';
    case REGEX = 'regex';

    public function translationKey(): string
    {
        return match ($this) {
            self::CONTAINS => 'imports.enums.match_operator.contains',
            self::EQUALS => 'imports.enums.match_operator.equals',
            self::STARTS_WITH => 'imports.enums.match_operator.starts_with',
            self::REGEX => 'imports.enums.match_operator.regex',
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
