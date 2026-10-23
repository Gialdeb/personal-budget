<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringEntryPeriod;
use App\Models\User;
use App\Services\UserYearService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringEntryPeriodService
{
    public const TERM_FIELDS = ['expected_amount', 'recurrence_type', 'recurrence_interval', 'recurrence_rule', 'due_day', 'end_mode', 'end_date', 'occurrences_limit'];

    public function __construct(
        protected RecurringEntryAmountResolver $amountResolver,
        protected RecurringEntryOccurrenceGeneratorService $generator,
    ) {}

    /** @return array<string, mixed> */
    public function terms(RecurringEntry $entry): array
    {
        return [...Arr::only($entry->getAttributes(), self::TERM_FIELDS), 'recurrence_rule' => $entry->recurrence_rule, 'currency' => $entry->currency];
    }

    public function current(RecurringEntry $entry): ?RecurringEntryPeriod
    {
        return $entry->periods()->whereNull('ended_at')->reorder('id', 'desc')->first();
    }

    public function refreshTerms(RecurringEntry $entry): void
    {
        $period = $this->current($entry);
        if ($period?->kind === 'created') {
            $period->update([
                'terms' => [...$this->terms($entry), 'expected_amount' => $period->terms['expected_amount']],
                'starts_on' => $entry->firstBillingDate()->toDateString(),
            ]);
        }
    }

    public function close(RecurringEntry $entry, string $reason): void
    {
        $period = $this->current($entry);
        if ($period === null) {
            // Legacy plans have no reliable historical start of their current terms.
            $period = $entry->periods()->create(['starts_on' => null, 'terms' => $this->terms($entry)]);
        }
        $period->update(['ends_on' => max(CarbonImmutable::today()->toDateString(), $period->starts_on?->toDateString() ?? ''), 'ended_at' => now(), 'ended_reason' => $reason]);
    }

    public function canRetire(RecurringEntryOccurrence $row, RecurringEntry $entry): bool
    {
        $amount = $entry->expected_amount;
        $myShare = $entry->shared_expense_my_share_cents;
        $counterpartyShare = $entry->shared_expense_counterparty_share_cents;
        if ($entry->promotion !== null) {
            $entry->loadMissing('amountChanges');
            $changes = $entry->amountChanges->sortBy([
                ['effective_from', 'asc'], ['id', 'asc'],
            ])->values();
            $amount = $this->amountResolver->resolve($entry, CarbonImmutable::parse($row->expected_date), $changes);
            $allocation = $this->generator->sharedExpenseAllocationForAmount($entry, (string) $amount);
            $myShare = $allocation['shared_expense_my_share_cents'];
            $counterpartyShare = $allocation['shared_expense_counterparty_share_cents'];
        }

        return in_array($row->status?->value, ['pending', 'generated'], true)
            && $row->expected_date->isAfter(today()) && ($row->due_date ?? $row->expected_date)->isAfter(today())
            && $row->converted_transaction_id === null && $row->matched_transaction_id === null
            && $row->account_id === null && $row->amount_confirmed_at === null && $row->automatic_posting_blocked_at === null
            && ($row->due_date === null || $row->due_date->eq($row->expected_date))
            && $row->notes === $entry->notes
            && (int) round((float) $row->expected_amount * 100) === (int) round((float) $amount * 100)
            && ! $row->paymentAllocations()->exists() && ! $row->settlementTransactions()->withTrashed()->exists()
            && ($row->shared_expense_paid_by === null || $row->shared_expense_paid_by === $entry->shared_expense_paid_by)
            && ($row->shared_expense_my_share_cents === null || $row->shared_expense_my_share_cents === $myShare)
            && ($row->shared_expense_counterparty_share_cents === null || $row->shared_expense_counterparty_share_cents === $counterpartyShare)
            && ! $row->sharedExpenseLedgerEntry()->exists() && ! $row->salaryDetail()->exists();
    }

    /** @param array<string, mixed> $data */
    public function reactivate(User $actor, RecurringEntry $entry, array $data): RecurringEntry
    {
        return DB::transaction(function () use ($actor, $entry, $data): RecurringEntry {
            $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $entry->ensureComponentIsMutable();
            if ($entry->compoundComponent()->exists() && (($data['recurrence_type'] ?? $entry->recurrence_type->value) !== 'yearly' || (int) ($data['recurrence_interval'] ?? $entry->recurrence_interval) !== 1)) {
                throw ValidationException::withMessages(['entry' => __('compound_recurring.protected_component')]);
            }
            $start = CarbonImmutable::parse($data['start_date']);
            $current = $this->current($entry);
            if ($entry->status === RecurringEntryStatusEnum::ACTIVE && $current?->starts_on?->eq($start)
                && (int) round((float) $entry->expected_amount * 100) === (int) round((float) $data['expected_amount'] * 100)
                && $entry->recurrence_type->value === $data['recurrence_type']
                && $entry->recurrence_interval === (int) $data['recurrence_interval']
                && $entry->recurrence_rule === ($data['recurrence_rule'] ?? null)
                && $entry->end_mode->value === $data['end_mode']
                && $entry->end_date?->toDateString() === ($data['end_date'] ?? null)
                && $entry->occurrences_limit === ($data['occurrences_limit'] ?? null)
                && (! array_key_exists('auto_create_transaction', $data) || $entry->auto_create_transaction === (bool) $data['auto_create_transaction'])) {
                return $entry;
            }
            if (! in_array($entry->status, [RecurringEntryStatusEnum::CANCELLED, RecurringEntryStatusEnum::PAUSED], true)
                || $entry->entry_type !== RecurringEntryTypeEnum::RECURRING || $entry->salarySchedule !== null) {
                throw ValidationException::withMessages(['entry' => __('ui.restart_only_regular_recurring_plans')]);
            }
            $last = $entry->periods()->reorder('id', 'desc')->first();
            if ($start->isBefore(today()) || ($last?->ended_at && $start->isBefore($last->ended_at->startOfDay()))) {
                throw ValidationException::withMessages(['start_date' => __('ui.restart_must_follow_interruption')]);
            }
            $original = clone $entry;
            $normalized = app(RecurringEntryValidatorService::class)->validate($actor, [
                ...$entry->getAttributes(), ...Arr::only($data, [...self::TERM_FIELDS, 'auto_create_transaction']),
                'start_date' => $start->toDateString(), 'trial_duration' => null, 'trial_unit' => null,
                'status' => 'active', 'is_active' => true,
            ], $original);
            app(UserYearService::class)->ensureDateYearExistsAndIsOpen($entry->user, $start->toDateString(), 'start_date');
            // Preserve existing obligations and all overrides, including future ones.
            foreach ($entry->occurrences()->lockForUpdate()->get() as $row) {
                if ($this->canRetire($row, $entry)) {
                    $row->update(['status' => 'cancelled']);
                }
            }
            $entry->fill(Arr::only($normalized, [...self::TERM_FIELDS, 'auto_create_transaction', 'shared_expense_my_share_cents', 'shared_expense_counterparty_share_cents']));
            $entry->forceFill(['status' => RecurringEntryStatusEnum::ACTIVE, 'is_active' => true, 'updated_by_user_id' => $actor->id])->save();
            $current?->update(['ends_on' => $start->toDateString(), 'ended_at' => now(), 'ended_reason' => 'paused']);
            $entry->periods()->create([
                'kind' => 'reactivated', 'starts_on' => $start->toDateString(), 'sequence_offset' => (int) $entry->occurrences()->max('sequence_number'),
                'terms' => $this->terms($entry),
            ]);
            app(RecurringEntryAmountHistoryService::class)->record($entry, $original, $start->toDateString());
            app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry);

            return $entry->fresh();
        });
    }

    public function allowsAutomaticPosting(RecurringEntryOccurrence $occurrence): bool
    {
        $entry = $occurrence->recurringEntry;
        if ($entry->status !== RecurringEntryStatusEnum::ACTIVE || ! $entry->is_active) {
            return false;
        }
        $period = $this->current($entry);

        return $period?->kind !== 'reactivated' || ($occurrence->due_date ?? $occurrence->expected_date)->gte($period->starts_on);
    }

    public function schedule(RecurringEntry $entry, RecurringEntryPeriod $period): RecurringEntry
    {
        $schedule = clone $entry;
        $schedule->fill(Arr::except($period->terms, ['currency', 'expected_amount']));
        $schedule->start_date = $period->starts_on;
        $schedule->setAttribute('period_schedule', true);
        $schedule->trial_duration = null;
        $schedule->trial_unit = null;

        return $schedule;
    }
}
