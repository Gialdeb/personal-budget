<?php

namespace App\Models;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionMatcherFieldEnum;
use App\Enums\TransactionMatcherTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionMatcher extends Model
{
    protected $fillable = [
        'user_id',
        'bank_id',
        'account_id',
        'merchant_id',
        'category_id',
        'scope_id',
        'direction',
        'match_field',
        'match_type',
        'pattern',
        'normalized_pattern',
        'confidence_score',
        'auto_confirm',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'auto_confirm' => 'boolean',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'direction' => TransactionDirectionEnum::class,
        'match_field' => TransactionMatcherFieldEnum::class,
        'match_type' => TransactionMatcherTypeEnum::class,
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

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}
