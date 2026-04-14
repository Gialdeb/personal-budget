<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\LogsDomainActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TrackedItem extends Model
{
    use HasPublicUuid, LogsActivity, LogsDomainActivity;

    protected $fillable = [
        'user_id',
        'account_id',
        'parent_id',
        'name',
        'slug',
        'type',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->domainActivityLogOptions('tracked_items', 'tracked_item', [
            'user_id',
            'account_id',
            'parent_id',
            'name',
            'slug',
            'type',
            'is_active',
            'settings',
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
        return $this->belongsTo(TrackedItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TrackedItem::class, 'parent_id');
    }

    public function compatibleCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'tracked_item_categories'
        )->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function recurringEntries(): HasMany
    {
        return $this->hasMany(RecurringEntry::class);
    }

    public function scheduledEntries(): HasMany
    {
        return $this->hasMany(ScheduledEntry::class);
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

    public function scopeInCatalog(Builder $query, int $userId, ?int $accountId = null): Builder
    {
        if ($accountId === null) {
            return $query->ownedBy($userId);
        }

        return $query->sharedForAccount($accountId);
    }
}
