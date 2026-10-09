<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedExpenseLedgerEntry extends Model
{
    use HasPublicUuid;

    protected $fillable = ['user_id', 'counterparty_id', 'recurring_entry_occurrence_id', 'entry_date', 'title', 'currency', 'total_cents', 'paid_by', 'my_share_cents', 'counterparty_share_cents', 'status', 'transaction_context'];

    protected $casts = ['entry_date' => 'date', 'total_cents' => 'integer', 'my_share_cents' => 'integer', 'counterparty_share_cents' => 'integer', 'transaction_context' => 'array'];

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'counterparty_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringEntryOccurrence::class, 'recurring_entry_occurrence_id');
    }
}
