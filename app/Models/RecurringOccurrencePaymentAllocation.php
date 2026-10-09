<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOccurrencePaymentAllocation extends Model
{
    protected $fillable = ['occurrence_id', 'transaction_id', 'amount_cents'];

    protected $casts = ['amount_cents' => 'integer'];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringEntryOccurrence::class, 'occurrence_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereHas('transaction', fn (Builder $transaction) => $transaction->where('status', 'confirmed'));
    }
}
