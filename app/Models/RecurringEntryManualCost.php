<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\RecurringEntryManualCostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringEntryManualCost extends Model
{
    /** @use HasFactory<RecurringEntryManualCostFactory> */
    use HasFactory, HasPublicUuid;

    protected $fillable = ['date', 'amount', 'currency'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    public function recurringEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringEntry::class);
    }
}
