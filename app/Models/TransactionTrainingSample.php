<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionTrainingSample extends Model
{
    protected $fillable = [
        'user_id',
        'bank_id',
        'account_id',
        'raw_description',
        'clean_description',
        'normalized_signature',
        'category_id',
        'merchant_id',
        'scope_id',
        'confirmed_by_user',
        'usage_count',
        'last_seen_at',
    ];

    protected $casts = [
        'confirmed_by_user' => 'boolean',
        'usage_count' => 'integer',
        'last_seen_at' => 'datetime',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}
