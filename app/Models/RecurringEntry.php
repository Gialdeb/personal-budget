<?php

namespace App\Models;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringEntry extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'user_id',
        'account_id',
        'scope_id',
        'category_id',
        'merchant_id',
        'title',
        'description',
        'direction',
        'expected_amount',
        'total_amount',
        'currency',
        'entry_type',
        'status',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_rule',
        'start_date',
        'end_date',
        'next_occurrence_date',
        'end_mode',
        'occurrences_limit',
        'installments_count',
        'due_day',
        'auto_generate_occurrences',
        'auto_create_transaction',
        'is_active',
        'notes',
        'tracked_item_id',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_occurrence_date' => 'date',
        'due_day' => 'integer',
        'recurrence_interval' => 'integer',
        'recurrence_rule' => 'array',
        'occurrences_limit' => 'integer',
        'installments_count' => 'integer',
        'auto_generate_occurrences' => 'boolean',
        'auto_create_transaction' => 'boolean',
        'is_active' => 'boolean',
        'direction' => TransactionDirectionEnum::class,
        'entry_type' => RecurringEntryTypeEnum::class,
        'status' => RecurringEntryStatusEnum::class,
        'end_mode' => RecurringEndModeEnum::class,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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

    public function trackedItem(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringEntryOccurrence::class);
    }
}
