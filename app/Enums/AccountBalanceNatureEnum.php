<?php

namespace App\Enums;

enum AccountBalanceNatureEnum: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';

    public function translationKey(): string
    {
        return match ($this) {
            self::ASSET => 'dashboard.enums.AccountBalanceNature.asset',
            self::LIABILITY => 'dashboard.enums.AccountBalanceNature.liability',
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
}
