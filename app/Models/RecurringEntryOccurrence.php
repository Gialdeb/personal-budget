<?php

namespace App\Models;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringEntryOccurrence extends Model
{
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::updating(function (self $occurrence): void {
            if ($occurrence->isDirty('expected_amount') && $occurrence->sharedExpenseLedgerEntry()->exists()
                && $occurrence->converted_transaction_id === null && $occurrence->matched_transaction_id === null) {
                $occurrence->status = $occurrence->externalPaymentCents() > 0 && $occurrence->externalPaymentCents() >= (int) round((float) $occurrence->expected_amount * 100)
                    ? RecurringOccurrenceStatusEnum::COMPLETED : RecurringOccurrenceStatusEnum::PENDING;
                if ($occurrence->status === RecurringOccurrenceStatusEnum::PENDING) {
                    $occurrence->automatic_posting_blocked_at = now();
                }
            }
            if ($occurrence->isDirty('expected_amount') && (int) round((float) $occurrence->expected_amount * 100) < $occurrence->allocatedPaymentCents()) {
                throw ValidationException::withMessages(['amount' => __('ui.the_amount_is_less_than_the_payments_already_allocated_e0ae57fd')]);
            }
        });
    }

    protected $fillable = [
        'recurring_entry_id',
        'account_id',
        'sequence_number',
        'expected_date',
        'due_date',
        'expected_amount',
        'status',
        'matched_transaction_id',
        'converted_transaction_id',
        'notes',
        'shared_expense_paid_by',
        'shared_expense_my_share_cents',
        'shared_expense_counterparty_share_cents',
    ];

    protected $casts = [
        'automatic_posting_blocked_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'sequence_number' => 'integer',
        'expected_date' => 'date',
        'due_date' => 'date',
        'expected_amount' => 'decimal:2',
        'amount_confirmed_at' => 'datetime',
        'status' => RecurringOccurrenceStatusEnum::class,
        'shared_expense_my_share_cents' => 'integer',
        'shared_expense_counterparty_share_cents' => 'integer',
    ];

    public function recurringEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    public function convertedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'converted_transaction_id');
    }

    public function sharedExpenseLedgerEntry(): HasOne
    {
        return $this->hasOne(SharedExpenseLedgerEntry::class);
    }

    public function scopeOpenObligations(Builder $query): Builder
    {
        $allocated = RecurringOccurrencePaymentAllocation::query()->valid()
            ->selectRaw('COALESCE(SUM(amount_cents), 0)')
            ->whereColumn('occurrence_id', $this->qualifyColumn('id'));

        return $query->whereIn('status', ['pending', 'generated'])
            ->whereRaw('ROUND('.$this->qualifyColumn('expected_amount').' * 100) > ('.$allocated->toSql().')', $allocated->getBindings())
            ->whereNull('converted_transaction_id')
            ->whereNull('matched_transaction_id')
            ->whereDoesntHave('sharedExpenseLedgerEntry', fn (Builder $ledger) => $ledger
                ->recorded()->where('paid_by', 'counterparty')
                ->whereColumn('total_cents', '>=', DB::raw('ROUND(recurring_entry_occurrences.expected_amount * 100)')))
            ->where('expected_amount', '>', 0)
            ->where(function (Builder $dates): void {
                $dates->whereDate('due_date', '<=', today())
                    ->orWhere(fn (Builder $fallback) => $fallback->whereNull('due_date')->whereDate('expected_date', '<=', today()));
            })
            ->where(function (Builder $amounts): void {
                $amounts->whereNotNull('amount_confirmed_at')
                    ->orWhereHas('recurringEntry', fn (Builder $entry) => $entry->where('is_amount_variable', false));
            });
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(RecurringOccurrencePaymentAllocation::class, 'occurrence_id');
    }

    public function allocatedPaymentCents(): int
    {
        return (int) $this->paymentAllocations()->valid()->sum('amount_cents');
    }

    public function externalPaymentCents(): int
    {
        $ledger = $this->sharedExpenseLedgerEntry;

        return $ledger !== null && $ledger->isRecorded() && $ledger->paid_by === 'counterparty'
            ? $ledger->total_cents : 0;
    }

    public function remainingPaymentCents(): int
    {
        if ($this->convertedTransaction()->exists() || $this->matchedTransaction()->exists()) {
            return 0;
        }

        return max(0, (int) round((float) $this->expected_amount * 100) - $this->allocatedPaymentCents() - $this->externalPaymentCents());
    }

    public function salaryDetail(): HasOne
    {
        return $this->hasOne(SalaryOccurrenceDetail::class, 'recurring_entry_occurrence_id');
    }
}
