<?php

namespace App\Models;

use App\Models\Concerns\HasAppendOnlyHistory;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterpartyAllocationEntry extends Model
{
    use HasAppendOnlyHistory, HasPublicUuid;

    protected $fillable = ['relation_id', 'currency_code', 'payment_id', 'compensation_entry_id', 'obligation_id', 'amount_minor', 'economic_date', 'recorded_at', 'reversal_of_id', 'idempotency_key', 'payload_hash', 'actor_id', 'reason'];

    protected $casts = [
        'amount_minor' => 'integer',
        'economic_date' => 'date',
        'recorded_at' => 'immutable_datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function relation(): BelongsTo
    {
        return $this->belongsTo(CounterpartyRelation::class, 'relation_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CounterpartyPayment::class, 'payment_id');
    }

    public function compensation(): BelongsTo
    {
        return $this->belongsTo(CounterpartyLedgerEntry::class, 'compensation_entry_id');
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(CounterpartyObligation::class, 'obligation_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(CounterpartyAllocationEntry::class, 'reversal_of_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
