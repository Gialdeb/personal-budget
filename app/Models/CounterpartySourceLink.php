<?php

namespace App\Models;

use App\Models\Concerns\HasAppendOnlyHistory;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterpartySourceLink extends Model
{
    use HasAppendOnlyHistory, HasPublicUuid;

    protected $fillable = ['relation_id', 'source_type', 'source_identity', 'economic_identity'];

    public function relation(): BelongsTo
    {
        return $this->belongsTo(CounterpartyRelation::class);
    }
}
