<?php

namespace App\Models;

use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'user_id',
        'bank_id',
        'account_id',
        'import_format_id',
        'original_filename',
        'stored_filename',
        'mime_type',
        'source_type',
        'parser_key',
        'status',
        'rows_count',
        'ready_rows_count',
        'review_rows_count',
        'invalid_rows_count',
        'duplicate_rows_count',
        'imported_rows_count',
        'imported_at',
        'rolled_back_at',
        'completed_at',
        'failed_at',
        'error_message',
        'meta',
        'user_id',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'rolled_back_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'rows_count' => 'integer',
        'ready_rows_count' => 'integer',
        'review_rows_count' => 'integer',
        'invalid_rows_count' => 'integer',
        'duplicate_rows_count' => 'integer',
        'imported_rows_count' => 'integer',
        'meta' => 'array',
        'source_type' => ImportSourceTypeEnum::class,
        'status' => ImportStatusEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function importFormat(): BelongsTo
    {
        return $this->belongsTo(ImportFormat::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
