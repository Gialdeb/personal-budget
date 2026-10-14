<?php

namespace App\Models;

use App\Enums\CounterpartyLedgerEventEnum;
use App\Models\Concerns\HasAppendOnlyHistory;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CounterpartyLedgerEntry extends Model
{
    use HasAppendOnlyHistory, HasPublicUuid;

    protected $fillable = ['relation_id', 'currency_code', 'obligation_id', 'payment_id', 'signed_minor', 'event_type', 'source_type', 'source_identity', 'source_version', 'idempotency_key', 'payload_hash', 'economic_date', 'recorded_at', 'reversal_of_id', 'transaction_id', 'transaction_uuid', 'actor_id', 'reason', 'metadata'];

    protected $casts = [
        'signed_minor' => 'integer',
        'source_version' => 'integer',
        'event_type' => CounterpartyLedgerEventEnum::class,
        'economic_date' => 'date',
        'recorded_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function relation(): BelongsTo
    {
        return $this->belongsTo(CounterpartyRelation::class, 'relation_id');
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(CounterpartyObligation::class, 'obligation_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CounterpartyPayment::class, 'payment_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(CounterpartyLedgerEntry::class, 'reversal_of_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function allocationEntries(): HasMany
    {
        return $this->hasMany(CounterpartyAllocationEntry::class, 'compensation_entry_id');
    }
}
