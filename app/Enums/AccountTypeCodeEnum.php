<?php

namespace App\Enums;

enum AccountTypeCodeEnum: string
{
    case BANK = 'bank';
    case CASH = 'cash';
    case CARD = 'card';
    case WALLET = 'wallet';
    case SAVINGS = 'savings';
    case LOAN = 'loan';

    public function label(): string
    {
        return match ($this) {
            self::BANK => 'Conto bancario',
            self::CASH => 'Contanti',
            self::CARD => 'Carta',
            self::WALLET => 'Wallet',
            self::SAVINGS => 'Risparmio',
            self::LOAN => 'Prestito',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function seedData(): array
    {
        return array_map(
            fn (self $case) => [
                'code' => $case->value,
                'name' => $case->label(),
            ],
            self::cases()
        );
    }
}
