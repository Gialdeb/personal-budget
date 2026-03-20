<?php

namespace App\Models;

use App\Enums\TransactionReviewActionEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionReview extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'transaction_id',
        'reviewed_by',
        'old_category_id',
        'new_category_id',
        'old_scope_id',
        'new_scope_id',
        'old_merchant_id',
        'new_merchant_id',
        'review_action',
        'notes',
    ];

    protected $casts = [
        'review_action' => TransactionReviewActionEnum::class,
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function oldCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'old_category_id');
    }

    public function newCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'new_category_id');
    }

    public function oldScope(): BelongsTo
    {
        return $this->belongsTo(Scope::class, 'old_scope_id');
    }

    public function newScope(): BelongsTo
    {
        return $this->belongsTo(Scope::class, 'new_scope_id');
    }

    public function oldMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'old_merchant_id');
    }

    public function newMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'new_merchant_id');
    }
}
