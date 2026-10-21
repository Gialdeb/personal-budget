<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOccurrencePaymentAllocation extends Model
{
    protected $fillable = ['occurrence_id', 'transaction_id', 'amount_cents'];

    protected $casts = ['amount_cents' => 'integer'];

    protected static function booted(): void
    {
        static::creating(fn (self $allocation) => $allocation->transaction->ensureNoPlanRefund());
        static::updating(function (self $allocation): void {
            Transaction::query()->findOrFail($allocation->getRawOriginal('transaction_id'))->ensureNoPlanRefund();
            $allocation->transaction->ensureNoPlanRefund();
        });
        static::deleting(fn (self $allocation) => $allocation->transaction->ensureNoPlanRefund());
    }

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
        return $query->whereHas('transaction', fn (Builder $transaction) => $transaction->where('status', 'confirmed')->whereDoesntHave('refundTransaction', fn (Builder $refund) => $refund->where('status', 'confirmed')));
    }
}
