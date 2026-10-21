<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringPlanRefundPayment extends Model
{
    protected $fillable = ['recurring_plan_refund_id', 'transaction_id', 'occurrence_id', 'amount_cents'];

    protected $casts = ['amount_cents' => 'integer'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
