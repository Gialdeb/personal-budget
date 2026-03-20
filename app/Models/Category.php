<?php

namespace App\Models;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasPublicUuid;

    protected $table = 'categories';

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'slug',
        'direction_type',
        'group_type',
        'color',
        'icon',
        'sort_order',
        'is_active',
        'is_selectable',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_selectable' => 'boolean',
        'direction_type' => CategoryDirectionTypeEnum::class,
        'group_type' => CategoryGroupTypeEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function compatibleTrackedItems(): BelongsToMany
    {
        return $this->belongsToMany(
            TrackedItem::class,
            'tracked_item_categories'
        )->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transactionSplits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function transactionMatchers(): HasMany
    {
        return $this->hasMany(TransactionMatcher::class);
    }

    public function transactionTrainingSamples(): HasMany
    {
        return $this->hasMany(TransactionTrainingSample::class);
    }

    public function defaultMerchants(): HasMany
    {
        return $this->hasMany(Merchant::class, 'default_category_id');
    }

    public function oldTransactionReviews(): HasMany
    {
        return $this->hasMany(TransactionReview::class, 'old_category_id');
    }

    public function newTransactionReviews(): HasMany
    {
        return $this->hasMany(TransactionReview::class, 'new_category_id');
    }

    public function recurringEntries(): HasMany
    {
        return $this->hasMany(RecurringEntry::class);
    }

    public function scheduledEntries(): HasMany
    {
        return $this->hasMany(ScheduledEntry::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
