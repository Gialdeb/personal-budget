<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Support\CurrencyMinorUnits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class CounterpartyRelation extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = ['user_id', 'counterparty_id', 'currency_code', 'minor_unit', 'migration_state', 'activated_at', 'activation_snapshot'];

    protected $casts = [
        'minor_unit' => 'integer',
        'activated_at' => 'datetime',
        'activation_snapshot' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $relation): void {
            $counterparty = TrackedItem::query()->find($relation->counterparty_id);
            if ($counterparty === null || $counterparty->user_id !== (int) $relation->user_id || $counterparty->account_id !== null
                || ((! $counterparty->is_active || ! $counterparty->is_shared_expense_counterpart) && ! self::hasLegacyEvidence($counterparty, $relation->currency_code))
                || $relation->minor_unit !== CurrencyMinorUnits::precision($relation->currency_code)) {
                throw ValidationException::withMessages(['relation' => 'Invalid personal counterparty relation.']);
            }
        });
        static::updating(function (self $relation): void {
            if ($relation->isDirty(['user_id', 'counterparty_id', 'currency_code', 'minor_unit', 'uuid'])) {
                throw ValidationException::withMessages(['relation' => 'Relation identity and precision cannot change.']);
            }
        });
    }

    public static function hasLegacyEvidence(TrackedItem $party, string $currency): bool
    {
        return $party->counterpartyCreditDebtItems()->where('user_id', $party->user_id)->where('currency_code', $currency)->exists()
            || $party->sharedExpenseLedgerEntries()->where('user_id', $party->user_id)->where('currency', $currency)->exists()
            || $party->sharedExpenseTransactions()->whereIn('account_id', app(AccessibleAccountsQuery::class)->ids($party->user_id))->where('currency', $currency)->exists()
            || ($currency === 'EUR' && $party->sharedExpenseSettlements()->where('user_id', $party->user_id)->exists());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'counterparty_id');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(CounterpartyObligation::class, 'relation_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CounterpartyPayment::class, 'relation_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CounterpartyLedgerEntry::class, 'relation_id');
    }

    public function allocationEntries(): HasMany
    {
        return $this->hasMany(CounterpartyAllocationEntry::class, 'relation_id');
    }
}
