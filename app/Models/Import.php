<?php

namespace App\Models;

use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    protected $fillable = [
        'user_id',
        'bank_id',
        'account_id',
        'original_filename',
        'stored_filename',
        'mime_type',
        'source_type',
        'parser_key',
        'status',
        'imported_at',
        'error_message',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
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

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
