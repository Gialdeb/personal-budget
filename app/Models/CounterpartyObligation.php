<?php

namespace App\Models;

use App\Enums\CounterpartyObligationSourceEnum;
use App\Models\Concerns\HasAppendOnlyHistory;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CounterpartyObligation extends Model
{
    use HasAppendOnlyHistory, HasPublicUuid;

    protected $fillable = ['relation_id', 'source_type', 'source_identity', 'economic_identity', 'recognized_minor', 'economic_date', 'due_date', 'revision_of_id', 'metadata'];

    protected $casts = [
        'source_type' => CounterpartyObligationSourceEnum::class,
        'recognized_minor' => 'integer',
        'economic_date' => 'date',
        'due_date' => 'date',
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

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(CounterpartyObligation::class, 'revision_of_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CounterpartyLedgerEntry::class, 'obligation_id');
    }

    public function allocationEntries(): HasMany
    {
        return $this->hasMany(CounterpartyAllocationEntry::class, 'obligation_id');
    }
}
