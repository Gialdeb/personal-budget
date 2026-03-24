<?php

namespace App\Enums;

enum TransactionKindEnum: string
{
    case MANUAL = 'manual';
    case OPENING_BALANCE = 'opening_balance';
    case SCHEDULED = 'scheduled';
    case REFUND = 'refund';

    public function translationKey(): string
    {
        return match ($this) {
            self::MANUAL => 'transactions.enums.kind.manual',
            self::OPENING_BALANCE => 'transactions.enums.kind.opening_balance',
            self::SCHEDULED => 'transactions.enums.kind.scheduled',
            self::REFUND => 'transactions.enums.kind.refund',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
