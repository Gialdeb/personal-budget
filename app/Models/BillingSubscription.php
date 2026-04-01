<?php

namespace App\Models;

use App\Enums\BillingProviderEnum;
use App\Enums\BillingSubscriptionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'billing_plan_id',
        'status',
        'provider',
        'is_supporter',
        'started_at',
        'ends_at',
        'last_transaction_id',
        'last_paid_at',
        'next_reminder_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => BillingSubscriptionStatusEnum::class,
            'provider' => BillingProviderEnum::class,
            'is_supporter' => 'boolean',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_paid_at' => 'datetime',
            'next_reminder_at' => 'datetime',
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

    public function lastTransaction(): BelongsTo
    {
        return $this->belongsTo(BillingTransaction::class, 'last_transaction_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class);
    }

    public function hasActiveSupportWindow(): bool
    {
        return $this->is_supporter
            && $this->status === BillingSubscriptionStatusEnum::Supporting
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function reminderIsDue(): bool
    {
        return $this->next_reminder_at !== null
            && $this->next_reminder_at->isPast();
    }
}
