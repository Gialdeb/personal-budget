<?php

namespace App\Models;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class RecurringEntryOccurrence extends Model
{
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::updating(function (self $occurrence): void {
            foreach (['converted_transaction_id', 'matched_transaction_id'] as $link) {
                if ($occurrence->isDirty($link) && $occurrence->getRawOriginal($link) !== null
                    && ($occurrence->{$link} === null || $link === 'matched_transaction_id')) {
                    Transaction::query()->find($occurrence->getRawOriginal($link))?->ensureNoPlanRefund();
                }
            }
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

    public function scopeWithoutArchivedFutureComponents(Builder $query): Builder
    {
        return $query->where(function (Builder $visible): void {
            $visible->whereRaw('COALESCE(due_date, expected_date) < ?', [today()->toDateString()])
                ->orWhereDoesntHave('recurringEntry.compoundComponent', fn (Builder $component) => $component->whereNotNull('archived_at'));
        });
    }

    public function scopeOpenObligations(Builder $query): Builder
    {
        $allocated = RecurringOccurrencePaymentAllocation::query()->valid()
            ->selectRaw('COALESCE(SUM(amount_cents), 0)')
            ->whereColumn('occurrence_id', $this->qualifyColumn('id'));

        $external = SharedExpenseLedgerEntry::query()->recorded()->where('paid_by', 'counterparty')
            ->selectRaw('COALESCE(SUM(total_cents), 0)')->whereColumn('recurring_entry_occurrence_id', $this->qualifyColumn('id'));
        $personal = Transaction::query()->where('status', 'confirmed')->whereIn('kind', ['manual', 'scheduled'])
            ->where(function (Builder $transactions): void {
                $transactions->whereColumn('recurring_entry_occurrence_id', $this->qualifyColumn('id'))
                    ->orWhereColumn('transactions.id', $this->qualifyColumn('converted_transaction_id'))
                    ->orWhereColumn('transactions.id', $this->qualifyColumn('matched_transaction_id'));
            })->whereDoesntHave('occurrencePaymentAllocations', fn (Builder $allocations) => $allocations->whereColumn('occurrence_id', $this->qualifyColumn('id')))
            ->selectRaw("COALESCE(SUM(ROUND(transactions.amount * 100) - COALESCE((SELECT ROUND(refunds.amount * 100) FROM transactions AS refunds WHERE refunds.refunded_transaction_id = transactions.id AND refunds.status = 'confirmed' AND refunds.deleted_at IS NULL), 0)), 0)");

        return $query->where(function (Builder $history): void {
            $history->whereHas('recurringEntry', fn (Builder $entry) => $entry->whereColumn('start_date', '<=', $this->qualifyColumn('expected_date')))
                ->orWhereDoesntHave('convertedTransaction.refundTransaction', fn (Builder $refund) => $refund->where('status', 'confirmed'));
        })
            ->whereIn('status', ['pending', 'generated', 'refunded'])
            ->whereRaw('ROUND('.$this->qualifyColumn('expected_amount').' * 100) > ('.$allocated->toSql().') + ('.$external->toSql().') + ('.$personal->toSql().')', [...$allocated->getBindings(), ...$external->getBindings(), ...$personal->getBindings()])
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

    public function settlementTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recurring_entry_occurrence_id');
    }

    public function personalSettlementTransactions(): Builder
    {
        return Transaction::query()->where(function (Builder $query): void {
            $query->where('recurring_entry_occurrence_id', $this->id)
                ->orWhereIn('id', array_filter([$this->converted_transaction_id, $this->matched_transaction_id]));
        })->whereIn('kind', ['manual', 'scheduled'])->where('status', 'confirmed')
            ->whereDoesntHave('occurrencePaymentAllocations', fn (Builder $query) => $query->where('occurrence_id', $this->id));
    }

    public function personalPaymentCents(): int
    {
        return (int) $this->personalSettlementTransactions()->with('refundTransaction')->get()->sum(fn (Transaction $transaction): int => $transaction->activeAmountCents());
    }

    public function remainingPaymentCents(): int
    {
        return max(0, (int) round((float) $this->expected_amount * 100)
            - $this->allocatedPaymentCents() - $this->externalPaymentCents() - $this->personalPaymentCents());
    }

    public function isRetiredRefundedHistory(): bool
    {
        return $this->convertedTransaction?->refundTransaction?->status?->value === 'confirmed'
            && $this->expected_date->lt($this->recurringEntry->firstBillingDate());
    }

    public function synchronizeSettlementState(): void
    {
        $this->unsetRelation('sharedExpenseLedgerEntry');
        if (in_array($this->status, [RecurringOccurrenceStatusEnum::SKIPPED, RecurringOccurrenceStatusEnum::CANCELLED], true)) {
            return;
        }
        $settled = $this->remainingPaymentCents() === 0 && (float) $this->expected_amount > 0;
        $this->forceFill([
            'status' => $settled ? RecurringOccurrenceStatusEnum::COMPLETED : RecurringOccurrenceStatusEnum::PENDING,
            'automatic_posting_blocked_at' => $settled ? $this->automatic_posting_blocked_at : now(),
        ])->save();
        $entry = $this->recurringEntry;
        if (! $settled && $entry !== null) {
            if ($entry->status === RecurringEntryStatusEnum::COMPLETED) {
                $entry->status = $entry->is_active ? RecurringEntryStatusEnum::ACTIVE : RecurringEntryStatusEnum::PAUSED;
            }
            $date = $this->due_date ?? $this->expected_date;
            if ($entry->next_occurrence_date === null || $date->lt($entry->next_occurrence_date)) {
                $entry->next_occurrence_date = $date;
            }
            $entry->save();
        }
    }

    public function salaryDetail(): HasOne
    {
        return $this->hasOne(SalaryOccurrenceDetail::class, 'recurring_entry_occurrence_id');
    }
}
