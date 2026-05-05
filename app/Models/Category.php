<?php

namespace App\Models;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\LogsDomainActivity;
use App\Services\Categories\CategoryFoundationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Category extends Model
{
    use HasPublicUuid, LogsActivity, LogsDomainActivity;

    protected $table = 'categories';

    protected $fillable = [
        'user_id',
        'account_id',
        'parent_id',
        'name',
        'name_is_custom',
        'slug',
        'foundation_key',
        'direction_type',
        'group_type',
        'color',
        'icon',
        'sort_order',
        'is_active',
        'is_selectable',
        'is_system',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_selectable' => 'boolean',
        'is_system' => 'boolean',
        'name_is_custom' => 'boolean',
        'direction_type' => CategoryDirectionTypeEnum::class,
        'group_type' => CategoryGroupTypeEnum::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->domainActivityLogOptions('categories', 'category', [
            'user_id',
            'account_id',
            'parent_id',
            'name',
            'name_is_custom',
            'slug',
            'foundation_key',
            'direction_type',
            'group_type',
            'color',
            'icon',
            'sort_order',
            'is_active',
            'is_selectable',
            'is_system',
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
        return $query
            ->where('user_id', $userId)
            ->whereNull('account_id');
    }

    public function scopeSharedForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeVisibleInStandardSettings(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('group_type')
                ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
        });
    }

    public function isTechnicalSystemCategory(): bool
    {
        return $this->is_system
            && $this->group_type === CategoryGroupTypeEnum::TRANSFER;
    }

    public function displayName(?string $locale = null): string
    {
        $resolvedLocale = CategoryFoundationService::resolveFoundationLocale($locale ?? app()->getLocale());
        $storedName = is_string($this->name) ? $this->name : '';

        if ((bool) ($this->name_is_custom ?? false)) {
            return $storedName;
        }

        if (
            is_string($this->foundation_key)
            && $this->foundation_key !== ''
            && CategoryFoundationService::nameIsCanonicalRootDefault($this->foundation_key, $storedName)
        ) {
            return CategoryFoundationService::localizedRootName($this->foundation_key, $resolvedLocale);
        }

        if (
            is_string($this->slug)
            && $this->slug !== ''
            && CategoryFoundationService::nameIsCanonicalChildDefault($this->slug, $storedName)
        ) {
            return CategoryFoundationService::localizedChildName($this->slug, $resolvedLocale) ?? $storedName;
        }

        return $storedName;
    }
}
