<?php

namespace App\Models;

use App\Enums\MerchantAliasMatchTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantAlias extends Model
{
    protected $fillable = [
        'merchant_id',
        'alias',
        'normalized_alias',
        'match_type',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'match_type' => MerchantAliasMatchTypeEnum::class,
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
