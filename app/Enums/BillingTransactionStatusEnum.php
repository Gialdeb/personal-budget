<?php

namespace App\Enums;

enum BillingTransactionStatusEnum: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
