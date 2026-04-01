<?php

namespace App\Enums;

enum BillingReconciliationStatusEnum: string
{
    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case ManualReview = 'manual_review';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
