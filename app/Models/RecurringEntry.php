<?php

namespace App\Models;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\LogsDomainActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RecurringEntry extends Model
{
    use HasPublicUuid, LogsActivity, LogsDomainActivity;

    public function firstBillingDate(): CarbonImmutable
    {
        $start = CarbonImmutable::parse($this->start_date);
        if ($this->entry_type !== RecurringEntryTypeEnum::RECURRING || ! $this->trial_duration) {
            return $start;
        }

        return match ($this->trial_unit) {
            'days' => $start->addDays($this->trial_duration),
            'weeks' => $start->addWeeks($this->trial_duration),
            'months' => $start->addMonthsNoOverflow($this->trial_duration),
            default => $start,
        };
    }

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
        'is_amount_variable',
        'total_amount',
        'currency',
        'entry_type',
        'status',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_rule',
        'start_date',
        'trial_duration',
        'trial_unit',
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
        'reminder_days_before',
        'tracked_item_id',
        'shared_expense_counterparty_id',
        'shared_expense_paid_by',
        'shared_expense_my_share_cents',
        'shared_expense_counterparty_share_cents',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'is_amount_variable' => 'boolean',
        'total_amount' => 'decimal:2',
        'start_date' => 'date',
        'trial_duration' => 'integer',
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
        'shared_expense_my_share_cents' => 'integer',
        'shared_expense_counterparty_share_cents' => 'integer',
        'reminder_days_before' => 'array',
        'direction' => TransactionDirectionEnum::class,
        'entry_type' => RecurringEntryTypeEnum::class,
        'status' => RecurringEntryStatusEnum::class,
        'end_mode' => RecurringEndModeEnum::class,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::class,
    ];

    protected $attributes = [
        'is_amount_variable' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->domainActivityLogOptions('recurring_entries', 'recurring_entry', [
            'user_id',
            'account_id',
            'scope_id',
            'category_id',
            'merchant_id',
            'title',
            'description',
            'direction',
            'expected_amount',
            'is_amount_variable',
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
            'reminder_days_before',
            'tracked_item_id',
            'shared_expense_counterparty_id',
            'shared_expense_paid_by',
            'shared_expense_my_share_cents',
            'shared_expense_counterparty_share_cents',
            'created_by_user_id',
            'updated_by_user_id',
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

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
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

    public function sharedExpenseCounterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'shared_expense_counterparty_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringEntryOccurrence::class);
    }

    public function amountChanges(): HasMany
    {
        return $this->hasMany(RecurringEntryAmountChange::class)->orderBy('created_at')->orderBy('id');
    }

    public function salarySchedule(): HasOne
    {
        return $this->hasOne(SalarySchedule::class);
    }
}
