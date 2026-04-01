<?php

namespace App\Models;

use App\Enums\BillingProviderEnum;
use App\Enums\BillingReconciliationStatusEnum;
use App\Enums\BillingTransactionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'billing_plan_id',
        'billing_subscription_id',
        'provider',
        'provider_transaction_id',
        'provider_event_id',
        'customer_email',
        'customer_name',
        'currency',
        'amount',
        'status',
        'paid_at',
        'received_at',
        'is_recurring',
        'reconciliation_status',
        'reconciled_at',
        'raw_payload',
        'metadata',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'provider' => BillingProviderEnum::class,
            'amount' => 'decimal:2',
            'status' => BillingTransactionStatusEnum::class,
            'paid_at' => 'datetime',
            'received_at' => 'datetime',
            'is_recurring' => 'boolean',
            'reconciliation_status' => BillingReconciliationStatusEnum::class,
            'reconciled_at' => 'datetime',
            'raw_payload' => 'array',
            'metadata' => 'array',
            'admin_notes' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class);
    }

    public function billingSubscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class);
    }
}
