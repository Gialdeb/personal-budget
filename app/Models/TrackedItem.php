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
        'website_identity_id',
        'parent_id',
        'name',
        'slug',
        'type',
        'website_url',
        'is_active',
        'is_shared_expense_counterpart',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_shared_expense_counterpart' => 'boolean',
        'settings' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->domainActivityLogOptions('tracked_items', 'tracked_item', [
            'user_id',
            'account_id',
            'website_identity_id',
            'parent_id',
            'name',
            'slug',
            'type',
            'website_url',
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

    public function websiteIdentity(): BelongsTo
    {
        return $this->belongsTo(WebsiteIdentity::class);
    }

    public function logoUrl(): ?string
    {
        return $this->websiteIdentity?->logoUrl();
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

    public function sharedExpenseTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'shared_expense_counterparty_id');
    }

    public function sharedExpenseRecurringEntries(): HasMany
    {
        return $this->hasMany(RecurringEntry::class, 'shared_expense_counterparty_id');
    }

    public function sharedExpenseSettlements(): HasMany
    {
        return $this->hasMany(SharedExpenseSettlement::class, 'counterparty_id');
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

    public function creditDebtItems(): HasMany
    {
        return $this->hasMany(CreditDebtItem::class, 'reference_id');
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

    public function scopeSharedExpenseCounterparties(Builder $query, int $userId): Builder
    {
        return $query->ownedBy($userId)
            ->where('is_active', true)
            ->where('is_shared_expense_counterpart', true);
    }

    public function scopeSharedExpenseHistory(Builder $query, int $userId): Builder
    {
        return $query
            ->ownedBy($userId)
            ->where(function (Builder $query): void {
                $query
                    ->where('is_shared_expense_counterpart', true)
                    ->orWhereIn('id', Transaction::query()->select('shared_expense_counterparty_id')->whereNotNull('shared_expense_counterparty_id'))
                    ->orWhereIn('id', SharedExpenseLedgerEntry::query()->select('counterparty_id'))
                    ->orWhereIn('id', SharedExpenseSettlement::query()->select('counterparty_id'));
            });
    }

    public function scopeInCatalog(Builder $query, int $userId, ?int $accountId = null): Builder
    {
        if ($accountId === null) {
            return $query->ownedBy($userId);
        }

        return $query->sharedForAccount($accountId);
    }
}
