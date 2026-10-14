<?php

namespace App\Models;

use App\Models\Concerns\HasAppendOnlyHistory;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class CounterpartyOperation extends Model
{
    use HasAppendOnlyHistory, HasPublicUuid;

    protected $fillable = ['relation_id', 'operation_key', 'payload_hash', 'kind', 'actor_id', 'result'];

    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
