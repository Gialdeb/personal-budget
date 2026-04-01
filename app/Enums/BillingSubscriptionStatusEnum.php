<?php

namespace App\Enums;

enum BillingSubscriptionStatusEnum: string
{
    case Free = 'free';
    case Supporting = 'supporting';
    case Inactive = 'inactive';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
