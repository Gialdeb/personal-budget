<?php

namespace App\Enums;

enum TransactionMatcherFieldEnum: string
{
    case BANK_DESCRIPTION_RAW = 'bank_description_raw';
    case BANK_DESCRIPTION_CLEAN = 'bank_description_clean';
    case COUNTERPARTY_NAME = 'counterparty_name';

    public function label(): string
    {
        return match ($this) {
            self::BANK_DESCRIPTION_RAW => 'Descrizione banca originale',
            self::BANK_DESCRIPTION_CLEAN => 'Descrizione banca pulita',
            self::COUNTERPARTY_NAME => 'Controparte',
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
