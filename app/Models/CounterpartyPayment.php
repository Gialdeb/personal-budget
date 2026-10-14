<?php

namespace App\Models;

use App\Enums\CounterpartyPaymentDirectionEnum;
use App\Models\Concerns\HasAppendOnlyHistory;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CounterpartyPayment extends Model
{
    use HasAppendOnlyHistory, HasPublicUuid;

    protected $fillable = ['relation_id', 'direction', 'amount_minor', 'economic_date', 'account_id', 'account_uuid', 'transaction_id', 'transaction_uuid', 'status', 'legacy_source_type', 'legacy_source_identity', 'idempotency_key', 'payload_hash', 'revision_of_id', 'return_of_id', 'metadata'];

    protected $casts = [
        'direction' => CounterpartyPaymentDirectionEnum::class,
        'amount_minor' => 'integer',
        'economic_date' => 'date',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(CounterpartyPayment::class, 'revision_of_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CounterpartyLedgerEntry::class, 'payment_id');
    }

    public function allocationEntries(): HasMany
    {
        return $this->hasMany(CounterpartyAllocationEntry::class, 'payment_id');
    }
}
