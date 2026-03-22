<?php

namespace App\Enums;

enum BudgetTypeEnum: string
{
    case TARGET = 'target';
    case LIMIT = 'limit';
    case FORECAST = 'forecast';

    public function translationKey(): string
    {
        return match ($this) {
            self::TARGET => 'planning.enums.budget_goal_type.target',
            self::LIMIT => 'planning.enums.budget_goal_type.limit',
            self::FORECAST => 'planning.enums.budget_goal_type.forecast',
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
