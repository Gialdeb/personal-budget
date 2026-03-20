<?php

namespace App\Models;

use App\Enums\ImportRowParseStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRow extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'import_id',
        'row_index',
        'raw_date',
        'raw_value_date',
        'raw_description',
        'raw_amount',
        'raw_balance',
        'raw_payload',
        'parse_status',
        'parse_error',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'row_index' => 'integer',
        'parse_status' => ImportRowParseStatusEnum::class,
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
