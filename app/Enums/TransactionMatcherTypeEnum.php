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

    public function translationKey(): string
    {
        return match ($this) {
            self::CONTAINS => 'transactions.enums.rule_operator.contains',
            self::EQUALS => 'transactions.enums.rule_operator.equals',
            self::STARTS_WITH => 'transactions.enums.rule_operator.starts_with',
            self::ENDS_WITH => 'transactions.enums.rule_operator.ends_with',
            self::REGEX => 'transactions.enums.rule_operator.regex',
            self::SIMILARITY => 'transactions.enums.rule_operator.similarity',
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
