<?php

namespace App\Enums;

enum AccountBalanceNatureEnum: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';

    public function label(): string
    {
        return match ($this) {
            self::ASSET => 'Attività',
            self::LIABILITY => 'Passività',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
