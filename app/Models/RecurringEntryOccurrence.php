<?php

namespace App\Models;

use App\Enums\RecurringOccurrenceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringEntryOccurrence extends Model
{
    protected $fillable = [
        'recurring_entry_id',
        'expected_date',
        'due_date',
        'expected_amount',
        'status',
        'matched_transaction_id',
        'converted_transaction_id',
        'notes',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'due_date' => 'date',
        'expected_amount' => 'decimal:2',
        'status' => RecurringOccurrenceStatusEnum::class,
    ];

    public function recurringEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringEntry::class);
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    public function convertedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'converted_transaction_id');
    }
}
