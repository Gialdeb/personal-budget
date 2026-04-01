<?php

namespace App\Enums;

enum BillingProviderEnum: string
{
    case Manual = 'manual';
    case Kofi = 'kofi';
    case Stripe = 'stripe';
    case Paypal = 'paypal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
