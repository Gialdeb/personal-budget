<?php

namespace App\Enums;

enum TransactionKindEnum: string
{
    case MANUAL = 'manual';
    case BALANCE_ADJUSTMENT = 'balance_adjustment';
    case OPENING_BALANCE = 'opening_balance';
    case SCHEDULED = 'scheduled';
    case REFUND = 'refund';
    case CREDIT_CARD_SETTLEMENT = 'credit_card_settlement';

    public function translationKey(): string
    {
        return match ($this) {
            self::MANUAL => 'transactions.enums.kind.manual',
            self::BALANCE_ADJUSTMENT => 'transactions.enums.kind.balance_adjustment',
            self::OPENING_BALANCE => 'transactions.enums.kind.opening_balance',
            self::SCHEDULED => 'transactions.enums.kind.scheduled',
            self::REFUND => 'transactions.enums.kind.refund',
            self::CREDIT_CARD_SETTLEMENT => 'transactions.enums.kind.credit_card_settlement',
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
