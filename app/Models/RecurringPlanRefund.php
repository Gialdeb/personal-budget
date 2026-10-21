<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringPlanRefund extends Model
{
    protected $fillable = ['request_uuid', 'plan_uuid', 'user_id', 'transaction_id', 'amount_cents'];

    protected $casts = ['amount_cents' => 'integer'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RecurringPlanRefundPayment::class);
    }
}
