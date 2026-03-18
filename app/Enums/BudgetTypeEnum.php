<?php

namespace App\Enums;

enum BudgetTypeEnum: string
{
    case TARGET = 'target';
    case LIMIT = 'limit';
    case FORECAST = 'forecast';

    public function label(): string
    {
        return match ($this) {
            self::TARGET => 'Obiettivo',
            self::LIMIT => 'Limite',
            self::FORECAST => 'Previsione',
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
