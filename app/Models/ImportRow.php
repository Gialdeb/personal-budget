<?php

namespace App\Models;

use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
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
        'normalized_payload',
        'parse_status',
        'status',
        'parse_error',
        'transaction_id',
        'fingerprint',
        'errors',
        'warnings',
        'rolled_back_at',
        'imported_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'row_index' => 'integer',
        'transaction_id' => 'integer',
        'rolled_back_at' => 'datetime',
        'imported_at' => 'datetime',
        'parse_status' => ImportRowParseStatusEnum::class,
        'status' => ImportRowStatusEnum::class,
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
