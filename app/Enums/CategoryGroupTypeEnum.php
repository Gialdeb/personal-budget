<?php

namespace App\Enums;

enum CategoryGroupTypeEnum: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case BILL = 'bill';
    case DEBT = 'debt';
    case SAVING = 'saving';
    case TAX = 'tax';
    case INVESTMENT = 'investment';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Entrate',
            self::EXPENSE => 'Spese',
            self::BILL => 'Bollette',
            self::DEBT => 'Debiti',
            self::SAVING => 'Risparmio',
            self::TAX => 'Tasse',
            self::INVESTMENT => 'Investimenti',
            self::TRANSFER => 'Trasferimenti',
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
