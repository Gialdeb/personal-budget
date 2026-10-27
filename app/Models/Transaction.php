<?php

namespace App\Models;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\LogsDomainActivity;
use App\Services\Counterparties\CounterpartyBankMutation;
use App\Services\Counterparties\CounterpartySourceGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Transaction extends Model
{
    use HasPublicUuid, LogsActivity, LogsDomainActivity;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            if ($transaction->refunded_transaction_id !== null) {
                self::query()->findOrFail($transaction->refunded_transaction_id)->ensureNoPlanRefund();
            }
            app(CounterpartySourceGuard::class)->assertRefund($transaction);
        });
        static::deleting(function (self $transaction): void {
            if ($transaction->payment_rectified_at !== null) {
                throw ValidationException::withMessages(['payment' => __('payment_management.rectified_locked')]);
            }
            $transaction->ensureNoPlanRefund();
            app(CounterpartyBankMutation::class)->assertGenericMutationAllowed($transaction);
            app(CounterpartySourceGuard::class)->assertChange($transaction, true);
        });
        static::restoring(fn (self $transaction) => app(CounterpartyBankMutation::class)->assertGenericMutationAllowed($transaction));
        static::updating(function (self $transaction): void {
            if ($transaction->getRawOriginal('payment_rectified_at') !== null && array_diff(array_keys($transaction->getDirty()), ['balance_after', 'updated_at']) !== []) {
                throw ValidationException::withMessages(['payment' => __('payment_management.rectified_locked')]);
            }
            if (array_diff(array_keys($transaction->getDirty()), ['balance_after', 'updated_at']) !== []) {
                $transaction->ensureNoPlanRefund();
            }
            if ($transaction->isDirty(['amount', 'direction', 'currency', 'currency_code', 'account_id', 'user_id', 'status', 'transaction_date', 'value_date', 'kind', 'source_type', 'is_transfer', 'shared_expense_counterparty_id', 'shared_expense_paid_by', 'shared_expense_my_share_cents', 'shared_expense_counterparty_share_cents', 'refunded_transaction_id', 'related_transaction_id', 'deleted_at'])) {
                app(CounterpartyBankMutation::class)->assertGenericMutationAllowed($transaction);
                app(CounterpartySourceGuard::class)->assertRefund($transaction);
                app(CounterpartySourceGuard::class)->assertChange($transaction);
            }
            $transaction->validateOccurrenceAllocations();
        });
        static::created(function (self $transaction): void {
            if ($transaction->recurring_entry_occurrence_id !== null || $transaction->refunded_transaction_id !== null) {
                $transaction->synchronizeRecurringSettlement();
            }
        });
        static::updated(fn (self $transaction) => $transaction->synchronizeRecurringSettlement());
        static::deleted(fn (self $transaction) => $transaction->synchronizeRecurringSettlement());
        static::restored(fn (self $transaction) => $transaction->synchronizeRecurringSettlement());
        static::restoring(function (self $transaction): void {
            $restored = clone $transaction;
            $restored->deleted_at = null;
            app(CounterpartySourceGuard::class)->assertRefund($restored);
            $transaction->validateOccurrenceAllocations();
        });
    }

    protected function synchronizeRecurringSettlement(): void
    {
        $sourceId = $this->refunded_transaction_id ?? $this->id;
        if (RecurringPlanRefundPayment::query()->where('transaction_id', $sourceId)->exists()) {
            return;
        }
        $occurrenceId = $this->recurring_entry_occurrence_id ?? ($this->refunded_transaction_id === null ? null : $this->refundedTransaction?->recurring_entry_occurrence_id);
        RecurringEntryOccurrence::withHistoricalPlan()->where(function (Builder $query) use ($sourceId, $occurrenceId): void {
            $query->where('converted_transaction_id', $sourceId)->orWhere('matched_transaction_id', $sourceId);
            if ($occurrenceId !== null) {
                $query->orWhere('id', $occurrenceId);
            }
        })->lockForUpdate()->get()->each(fn (RecurringEntryOccurrence $occurrence) => $occurrence->synchronizeSettlementState());
    }

    protected function validateOccurrenceAllocations(): void
    {
        if ($this->status?->value !== 'confirmed' || $this->deleted_at !== null) {
            return;
        }
        $allocations = $this->occurrencePaymentAllocations()->with('occurrence.recurringEntry')->get();
        if ($allocations->isEmpty()) {
            return;
        }
        if ($allocations->sum('amount_cents') > (int) round((float) $this->amount * 100) || $this->shared_expense_counterparty_id !== null) {
            throw ValidationException::withMessages(['amount' => __('ui.this_transaction_already_has_allocated_payments_05026576')]);
        }
        $wasValid = $this->getRawOriginal('status') === 'confirmed' && $this->getRawOriginal('deleted_at') === null;
        foreach ($allocations as $allocation) {
            $occurrence = $allocation->occurrence;
            $paid = $occurrence->allocatedPaymentCents() + ($wasValid ? 0 : $allocation->amount_cents);
            if ($paid > (int) round((float) $occurrence->expected_amount * 100)
                || $this->currency !== $occurrence->recurringEntry->currency
                || $this->direction !== $occurrence->recurringEntry->direction) {
                throw ValidationException::withMessages(['amount' => __('ui.the_allocation_exceeds_the_remaining_balance_or_is_incompatible_5d9801ff')]);
            }
        }
    }

    public function ensureNoPlanRefund(): void
    {
        if (! $this->exists) {
            return;
        }
        $this->newQuery()->withTrashed()->whereKey($this->id)->lockForUpdate()->firstOrFail();
        if (RecurringPlanRefund::query()->where('transaction_id', $this->id)->exists()
            || RecurringPlanRefundPayment::query()->where('transaction_id', $this->id)->exists()) {
            throw ValidationException::withMessages(['transaction' => __('recurring_refunds.locked')]);
        }
    }

    public function activeAmountCents(): int
    {
        if ($this->trashed() || $this->status !== TransactionStatusEnum::CONFIRMED) {
            return 0;
        }
        $refund = $this->refundTransaction;

        return max(0, (int) round((float) $this->amount * 100)
            - ($refund?->status === TransactionStatusEnum::CONFIRMED ? (int) round((float) $refund->amount * 100) : 0));
    }

    public function scopeNotRectified(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('payment_rectified_at'));
    }

    public function occurrencePaymentAllocations(): HasMany
    {
        return $this->hasMany(RecurringOccurrencePaymentAllocation::class);
    }

    protected $fillable = [
        'payment_original_amount', 'payment_rectified_at', 'payment_rectification_reason',
        'payment_verification_started_at',
        'payment_verification_due_at',
        'user_id',
        'account_id',
        'import_id',
        'import_row_id',
        'scope_id',
        'category_id',
        'merchant_id',
        'transaction_date',
        'value_date',
        'posted_at',
        'direction',
        'kind',
        'amount',
        'currency',
        'currency_code',
        'base_currency_code',
        'exchange_rate',
        'exchange_rate_date',
        'converted_base_amount',
        'exchange_rate_source',
        'description',
        'bank_description_raw',
        'bank_description_clean',
        'bank_operation_type',
        'counterparty_name',
        'reference_code',
        'balance_after',
        'source_type',
        'status',
        'matched_rule_id',
        'matched_sample_id',
        'match_strategy',
        'confidence_score',
        'external_hash',
        'reconciliation_key',
        'is_transfer',
        'related_transaction_id',
        'recurring_entry_occurrence_id',
        'refunded_transaction_id',
        'created_by_user_id',
        'updated_by_user_id',
        'notes',
        'tracked_item_id',
        'shared_expense_counterparty_id',
        'shared_expense_paid_by',
        'shared_expense_my_share_cents',
        'shared_expense_counterparty_share_cents',
    ];

    protected $casts = [
        'payment_original_amount' => 'decimal:2',
        'payment_rectified_at' => 'datetime',
        'payment_verification_started_at' => 'datetime',
        'payment_verification_due_at' => 'datetime',
        'payment_verified_at' => 'datetime',
        'transaction_date' => 'date',
        'value_date' => 'date',
        'posted_at' => 'datetime',
        'deleted_at' => 'datetime',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'converted_base_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'exchange_rate_date' => 'date',
        'confidence_score' => 'decimal:2',
        'is_transfer' => 'boolean',
        'shared_expense_my_share_cents' => 'integer',
        'shared_expense_counterparty_share_cents' => 'integer',
        'currency_code' => 'string',
        'base_currency_code' => 'string',
        'exchange_rate_source' => 'string',
        'direction' => TransactionDirectionEnum::class,
        'kind' => TransactionKindEnum::class,
        'source_type' => TransactionSourceTypeEnum::class,
        'status' => TransactionStatusEnum::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->domainActivityLogOptions('transactions', 'transaction', [
            'user_id',
            'account_id',
            'import_id',
            'import_row_id',
            'scope_id',
            'category_id',
            'merchant_id',
            'transaction_date',
            'value_date',
            'posted_at',
            'direction',
            'kind',
            'amount',
            'currency',
            'currency_code',
            'base_currency_code',
            'exchange_rate',
            'exchange_rate_date',
            'converted_base_amount',
            'exchange_rate_source',
            'description',
            'bank_description_clean',
            'bank_operation_type',
            'counterparty_name',
            'reference_code',
            'balance_after',
            'source_type',
            'status',
            'matched_rule_id',
            'matched_sample_id',
            'match_strategy',
            'confidence_score',
            'reconciliation_key',
            'is_transfer',
            'related_transaction_id',
            'recurring_entry_occurrence_id',
            'refunded_transaction_id',
            'created_by_user_id',
            'updated_by_user_id',
            'notes',
            'tracked_item_id',
            'shared_expense_counterparty_id',
            'shared_expense_paid_by',
            'shared_expense_my_share_cents',
            'shared_expense_counterparty_share_cents',
            'deleted_at',
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'related_transaction_id');
    }

    public function linkedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'related_transaction_id');
    }

    public function recurringOccurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringEntryOccurrence::class, 'recurring_entry_occurrence_id')->withoutGlobalScope(RecurringEntryOccurrence::ACTIVE_PLAN_SCOPE);
    }

    public function creditDebtPayment(): HasOne
    {
        return $this->hasOne(CreditDebtPayment::class)->withTrashed();
    }

    public function refundedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'refunded_transaction_id');
    }

    public function refundTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'refunded_transaction_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TransactionReview::class);
    }

    public function matchedRule(): BelongsTo
    {
        return $this->belongsTo(TransactionMatcher::class, 'matched_rule_id');
    }

    public function matchedSample(): BelongsTo
    {
        return $this->belongsTo(TransactionTrainingSample::class, 'matched_sample_id');
    }

    public function matchedRecurringOccurrences(): HasMany
    {
        return $this->hasMany(RecurringEntryOccurrence::class, 'matched_transaction_id')->withoutGlobalScope(RecurringEntryOccurrence::ACTIVE_PLAN_SCOPE);
    }

    public function convertedRecurringOccurrences(): HasMany
    {
        return $this->hasMany(RecurringEntryOccurrence::class, 'converted_transaction_id')->withoutGlobalScope(RecurringEntryOccurrence::ACTIVE_PLAN_SCOPE);
    }

    public function matchedScheduledEntries(): HasMany
    {
        return $this->hasMany(ScheduledEntry::class, 'matched_transaction_id');
    }

    public function trackedItem(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class);
    }

    public function sharedExpenseCounterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'shared_expense_counterparty_id');
    }

    public function sharedExpenseSettlement(): HasOne
    {
        return $this->hasOne(SharedExpenseSettlement::class);
    }

    public function isOpeningBalance(): bool
    {
        return $this->kind === TransactionKindEnum::OPENING_BALANCE;
    }

    public function isCreditCardSettlement(): bool
    {
        return $this->kind === TransactionKindEnum::CREDIT_CARD_SETTLEMENT;
    }
}
