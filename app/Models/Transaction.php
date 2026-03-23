<?php

namespace App\Models;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasPublicUuid;

    protected $fillable = [
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
        'notes',
        'tracked_item_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'posted_at' => 'datetime',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'is_transfer' => 'boolean',
        'direction' => TransactionDirectionEnum::class,
        'kind' => TransactionKindEnum::class,
        'source_type' => TransactionSourceTypeEnum::class,
        'status' => TransactionStatusEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->hasMany(RecurringEntryOccurrence::class, 'matched_transaction_id');
    }

    public function convertedRecurringOccurrences(): HasMany
    {
        return $this->hasMany(RecurringEntryOccurrence::class, 'converted_transaction_id');
    }

    public function matchedScheduledEntries(): HasMany
    {
        return $this->hasMany(ScheduledEntry::class, 'matched_transaction_id');
    }

    public function trackedItem(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class);
    }

    public function isOpeningBalance(): bool
    {
        return $this->kind === TransactionKindEnum::OPENING_BALANCE;
    }
}
