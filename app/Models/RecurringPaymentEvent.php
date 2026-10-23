<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class RecurringPaymentEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['payment' => __('payment_management.history_locked')]));
        static::deleting(fn () => throw ValidationException::withMessages(['payment' => __('payment_management.history_locked')]));
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringEntryOccurrence::class, 'occurrence_id');
    }
}
