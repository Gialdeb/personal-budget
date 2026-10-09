<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedExpenseSettlement extends Model
{
    use HasPublicUuid;

    protected $fillable = ['user_id', 'counterparty_id', 'amount_cents', 'direction', 'settled_at', 'transaction_id', 'notes'];

    protected $casts = ['amount_cents' => 'integer', 'settled_at' => 'date'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'counterparty_id');
    }
}
