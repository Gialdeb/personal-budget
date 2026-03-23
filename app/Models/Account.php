<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'user_id',
        'bank_id',
        'user_bank_id',
        'account_type_id',
        'scope_id',
        'currency_code',
        'name',
        'iban',
        'account_number_masked',
        'currency',
        'opening_balance',
        'opening_balance_date',
        'current_balance',
        'is_manual',
        'is_active',
        'notes',
        'settings',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'current_balance' => 'decimal:2',
        'is_manual' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
        'currency_code' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function userBank(): BelongsTo
    {
        return $this->belongsTo(UserBank::class);
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function openingBalances(): HasMany
    {
        return $this->hasMany(AccountOpeningBalance::class);
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(AccountBalanceSnapshot::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(AccountReconciliation::class);
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
        return $query->where('user_id', $userId);
    }

    public function currencyCode(): string
    {
        return $this->currency_code;
    }
}
