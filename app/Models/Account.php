<?php

namespace App\Models;

use App\Enums\AccountTypeCodeEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'is_reported',
        'is_default',
        'notes',
        'settings',
        'uuid',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'current_balance' => 'decimal:2',
        'is_manual' => 'boolean',
        'is_active' => 'boolean',
        'is_reported' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
        'currency_code' => 'string',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

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

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
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

    public function creditCardCycleCharges(): HasMany
    {
        return $this->hasMany(CreditCardCycleCharge::class, 'credit_card_account_id');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDefaultOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->ownedBy($userId)
            ->where('is_active', true)
            ->where('is_default', true);
    }

    public function currencyCode(): string
    {
        return $this->currency_code;
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function originalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_memberships')
            ->withPivot([
                'uuid',
                'household_id',
                'role',
                'status',
                'permissions',
                'granted_by_user_id',
                'source',
                'joined_at',
                'left_at',
                'left_reason',
                'revoked_at',
                'revoked_by_user_id',
                'restored_at',
                'restored_by_user_id',
            ])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AccountInvitation::class);
    }

    public function isProtectedCashAccount(): bool
    {
        return $this->accountType?->code === AccountTypeCodeEnum::CASH_ACCOUNT->value
            && $this->name === 'Cassa contanti';
    }

    public function supportsSharing(): bool
    {
        return ! in_array($this->accountType?->code, [
            AccountTypeCodeEnum::CASH_ACCOUNT->value,
            AccountTypeCodeEnum::CREDIT_CARD->value,
        ], true);
    }

    public function isCreditCard(): bool
    {
        return $this->accountType?->code === AccountTypeCodeEnum::CREDIT_CARD->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function creditCardSettings(): array
    {
        return is_array($this->settings) ? $this->settings : [];
    }

    public function creditCardLimit(): ?float
    {
        $limit = data_get($this->creditCardSettings(), 'credit_limit');

        if (! is_numeric($limit)) {
            return null;
        }

        return round((float) $limit, 2);
    }

    public function creditCardLinkedPaymentAccountId(): ?int
    {
        $linkedPaymentAccountId = data_get($this->creditCardSettings(), 'linked_payment_account_id');

        return is_numeric($linkedPaymentAccountId)
            ? (int) $linkedPaymentAccountId
            : null;
    }

    public function creditCardStatementClosingDay(): ?int
    {
        $statementClosingDay = data_get($this->creditCardSettings(), 'statement_closing_day');

        return is_numeric($statementClosingDay)
            ? (int) $statementClosingDay
            : null;
    }

    public function creditCardPaymentDay(): ?int
    {
        $paymentDay = data_get($this->creditCardSettings(), 'payment_day');

        return is_numeric($paymentDay)
            ? (int) $paymentDay
            : null;
    }

    public function creditCardAutoPay(): bool
    {
        return (bool) data_get($this->creditCardSettings(), 'auto_pay', false);
    }
}
