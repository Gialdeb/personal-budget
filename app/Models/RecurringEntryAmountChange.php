<?php

namespace App\Models;

use Database\Factories\RecurringEntryAmountChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringEntryAmountChange extends Model
{
    /** @use HasFactory<RecurringEntryAmountChangeFactory> */
    use HasFactory;

    protected $fillable = ['previous_amount', 'new_amount', 'effective_from', 'currency', 'recurrence_comparison'];

    protected function casts(): array
    {
        return ['recurrence_comparison' => 'array', 'previous_amount' => 'decimal:2', 'new_amount' => 'decimal:2', 'effective_from' => 'date'];
    }

    public function recurringEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringEntry::class);
    }

    public function difference(): float
    {
        return round((float) $this->new_amount - (float) $this->previous_amount, 2);
    }

    public function percentage(): ?float
    {
        if ($this->recurrence_comparison !== null && ! $this->recurrence_comparison['comparable']) {
            return null;
        }
        $previousCents = (int) round((float) $this->previous_amount * 100);

        return $previousCents === 0 ? null : round((int) round($this->difference() * 100) / $previousCents * 100, 2);
    }
}
