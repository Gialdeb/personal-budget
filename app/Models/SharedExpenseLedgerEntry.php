<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\Counterparties\CounterpartySourceGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedExpenseLedgerEntry extends Model
{
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::updating(fn (self $ledger) => app(CounterpartySourceGuard::class)->assertChange($ledger));
        static::deleting(fn (self $ledger) => app(CounterpartySourceGuard::class)->assertChange($ledger, true));
        static::saved(function (self $ledger): void {
            $ledger->synchronizeOccurrence();
            if ($ledger->wasChanged('recurring_entry_occurrence_id')) {
                $originalId = $ledger->getRawOriginal('recurring_entry_occurrence_id');
                if ($originalId !== null) {
                    $ledger->synchronizeOccurrence((int) $originalId);
                }
            }
        });
        static::deleted(fn (self $ledger) => $ledger->synchronizeOccurrence());
    }

    protected function synchronizeOccurrence(?int $occurrenceId = null): void
    {
        $occurrenceId ??= $this->recurring_entry_occurrence_id;
        if ($occurrenceId === null) {
            return;
        }
        $occurrence = RecurringEntryOccurrence::withHistoricalPlan()->lockForUpdate()->find($occurrenceId);
        $occurrence?->synchronizeSettlementState();
    }

    protected $fillable = ['user_id', 'counterparty_id', 'recurring_entry_occurrence_id', 'entry_date', 'title', 'currency', 'total_cents', 'paid_by', 'my_share_cents', 'counterparty_share_cents', 'status', 'transaction_context'];

    protected $casts = ['entry_date' => 'date', 'total_cents' => 'integer', 'my_share_cents' => 'integer', 'counterparty_share_cents' => 'integer', 'transaction_context' => 'array'];

    public function scopeRecorded(Builder $query): Builder
    {
        return $query->whereIn('status', ['recorded', 'posted']);
    }

    public function isRecorded(): bool
    {
        return in_array($this->status, ['recorded', 'posted'], true);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'counterparty_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringEntryOccurrence::class, 'recurring_entry_occurrence_id')->withoutGlobalScope(RecurringEntryOccurrence::ACTIVE_PLAN_SCOPE);
    }
}
