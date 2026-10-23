<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryAmountChange;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringEntryPeriod;
use App\Services\Salary\SalaryOccurrenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringEntryOccurrenceGeneratorService
{
    public function __construct(
        protected InstallmentAmountAllocatorService $allocator,
        protected RecurringEntryAmountResolver $amountResolver,
    ) {}

    /**
     * @return Collection<int, RecurringEntryOccurrence>
     */
    public function generate(RecurringEntry $entry, ?CarbonImmutable $throughDate = null, ?int $maxOccurrences = null, bool $updateNextOccurrenceDate = true): Collection
    {
        if ($entry->status !== RecurringEntryStatusEnum::ACTIVE || ! $entry->is_active) {
            return collect();
        }

        return DB::transaction(function () use ($entry, $throughDate, $maxOccurrences, $updateNextOccurrenceDate): Collection {
            $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status !== RecurringEntryStatusEnum::ACTIVE || ! $entry->is_active) {
                return collect();
            }
            $period = app(RecurringEntryPeriodService::class)->current($entry);
            if ($period?->kind === 'reactivated') {
                return $this->generatePeriod($entry, $period, $throughDate, $maxOccurrences, $updateNextOccurrenceDate);
            }

            $schedule = $entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
                ? $this->installmentSchedule($entry)
                : $this->recurringSchedule($entry, $throughDate, $maxOccurrences);

            $created = collect();
            $existingSequences = $entry->occurrences()
                ->pluck('id', 'sequence_number');
            $protectedDates = $this->refundedHistoryOffset($entry) > 0
                ? $entry->occurrences()->where('status', '!=', RecurringOccurrenceStatusEnum::CANCELLED)
                    ->pluck('expected_date')->map->toDateString()
                : collect();

            foreach ($schedule as $item) {
                if ($existingSequences->has($item['sequence_number']) || $protectedDates->contains($item['expected_date'])) {
                    continue;
                }

                $occurrence = $entry->occurrences()->create($item);
                $occurrence->setRelation('recurringEntry', $entry);
                app(SalaryOccurrenceService::class)->snapshot($occurrence);
                $created->push($occurrence);
            }

            if ($updateNextOccurrenceDate) {
                $this->updateNextOccurrenceDate($entry);
            }

            return $created;
        });
    }

    protected function generatePeriod(RecurringEntry $entry, RecurringEntryPeriod $period, ?CarbonImmutable $throughDate, ?int $maxOccurrences, bool $updateNextOccurrenceDate): Collection
    {
        $schedule = app(RecurringEntryPeriodService::class)->schedule($entry, $period);
        $changes = $entry->amountChanges()->reorder('effective_from')->orderBy('id')->get();
        $rows = $this->recurringSchedule($schedule, $throughDate, $maxOccurrences, $changes, $entry);
        $existing = $entry->occurrences()->get();
        $sequences = $existing->pluck('sequence_number')->all();
        $protectedDates = $existing->where('status', '!=', RecurringOccurrenceStatusEnum::CANCELLED)->map(fn ($row): string => $row->expected_date->toDateString())->all();
        $created = collect();
        foreach ($rows as $row) {
            $row['sequence_number'] += $period->sequence_offset;
            if (in_array($row['sequence_number'], $sequences, true) || in_array($row['expected_date'], $protectedDates, true)) {
                continue;
            }
            if ($period->ends_on && $row['expected_date'] >= $period->ends_on->toDateString()) {
                continue;
            }
            $created->push($entry->occurrences()->create($row));
        }
        if ($updateNextOccurrenceDate) {
            $last = max(0, (int) $entry->occurrences()->max('sequence_number') - $period->sequence_offset);
            $next = $this->resolveDateForSequence($schedule, $last + 1);
            $finished = ($schedule->end_mode === RecurringEndModeEnum::AFTER_OCCURRENCES && $last >= (int) $schedule->occurrences_limit)
                || ($schedule->end_mode === RecurringEndModeEnum::UNTIL_DATE && $next->gt($schedule->end_date));
            $entry->update(['next_occurrence_date' => $finished ? null : $next->toDateString()]);
        }

        return $created;
    }

    /**
     * Preserve occurrence overrides and history when the parent plan changes.
     */
    public function synchronizeFutureOccurrences(RecurringEntry $entry, RecurringEntry $original): ?string
    {
        $firstChangedAmountDate = null;
        foreach ($this->futureOccurrenceReplacements($entry, $original, true) as $plan) {
            $occurrence = $plan['occurrence'];
            $replacement = $plan['replacement'];
            if ($plan['cancel']) {
                $occurrence->forceFill(['status' => RecurringOccurrenceStatusEnum::CANCELLED])->save();

                continue;
            }
            if ((int) round((float) $occurrence->expected_amount * 100) !== (int) round((float) $replacement['expected_amount'] * 100)) {
                $date = $replacement['expected_date'];
                $firstChangedAmountDate = $firstChangedAmountDate === null ? $date : min($firstChangedAmountDate, $date);
            }
            $occurrence->fill(Arr::only($replacement, [
                'expected_date', 'due_date', 'expected_amount',
                'shared_expense_paid_by', 'shared_expense_my_share_cents', 'shared_expense_counterparty_share_cents',
            ]))->save();
            $occurrence->salaryDetail?->update(['base_expected_amount' => $occurrence->expected_amount]);
        }

        return $firstChangedAmountDate;
    }

    /** @return array{changes: array<int, array<string, mixed>>, confirmation: string|null} */
    public function promotionImpact(RecurringEntry $entry, RecurringEntry $original, bool $lock = false): array
    {
        $changes = $this->futureOccurrenceReplacements($entry, $original, $lock)
            ->filter(fn (array $plan): bool => ! $plan['cancel'] && (int) round((float) $plan['occurrence']->expected_amount * 100) !== (int) round((float) $plan['replacement']['expected_amount'] * 100))
            ->map(fn (array $plan): array => [
                'uuid' => $plan['occurrence']->uuid,
                'previous_date' => $plan['occurrence']->expected_date->toDateString(),
                'date' => $plan['replacement']['expected_date'],
                'previous_amount' => (float) $plan['occurrence']->expected_amount,
                'amount' => (float) $plan['replacement']['expected_amount'],
                'currency' => $entry->currency,
            ])->sortBy('uuid')->values()->all();
        $terms = Arr::only($entry->attributesToArray(), ['promotion', 'expected_amount', 'currency', 'start_date', 'trial_duration', 'trial_unit', 'recurrence_type', 'recurrence_interval', 'recurrence_rule', 'end_mode', 'end_date', 'occurrences_limit']);

        return ['changes' => $changes, 'confirmation' => $changes === [] ? null : hash_hmac('sha256', json_encode([$original->uuid, $original->promotion, $terms, $changes, today()->toDateString()], JSON_THROW_ON_ERROR), (string) config('app.key'))];
    }

    /** @return Collection<int, array{occurrence: RecurringEntryOccurrence, replacement: array<string, mixed>, cancel: bool}> */
    protected function futureOccurrenceReplacements(RecurringEntry $entry, RecurringEntry $original, bool $lock): Collection
    {
        $occurrences = $entry->occurrences()->with('salaryDetail.components')->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $period = app(RecurringEntryPeriodService::class)->current($entry);
        $offset = $period?->kind === 'reactivated' ? $period->sequence_offset : 0;
        $amountEntry = $entry;
        $originalAmountEntry = $original;
        $beforeChanges = $original->promotion !== null ? $original->amountChanges()->reorder('effective_from')->orderBy('id')->get() : null;
        $afterChanges = $entry->promotion !== null ? $entry->amountChanges()->reorder('effective_from')->orderBy('id')->get() : null;
        if ($afterChanges !== null && $entry->expected_amount !== $original->expected_amount) {
            $afterChanges->push(new RecurringEntryAmountChange([
                'previous_amount' => $original->expected_amount, 'new_amount' => $entry->expected_amount,
                'effective_from' => CarbonImmutable::tomorrow()->toDateString(),
            ]));
            $afterChanges = $afterChanges->sortBy(fn (RecurringEntryAmountChange $change): string => $change->effective_from->toDateString())->values();
        }
        if ($period?->kind === 'reactivated') {
            $newAmount = $entry->expected_amount;
            $entry = app(RecurringEntryPeriodService::class)->schedule($entry, $period);
            $entry->expected_amount = $newAmount;
            $original = app(RecurringEntryPeriodService::class)->schedule($original, $period);
        }
        $limit = max(1, (int) $occurrences->max('sequence_number') - $offset);
        $before = collect($original->entry_type === RecurringEntryTypeEnum::INSTALLMENT
            ? $this->installmentSchedule($original)
            : $this->recurringSchedule($original, null, $limit, $beforeChanges, $originalAmountEntry))->keyBy('sequence_number');
        $after = collect($entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
            ? $this->installmentSchedule($entry)
            : $this->recurringSchedule($entry, null, $limit, $afterChanges, $amountEntry))->keyBy('sequence_number');

        $mutable = collect();
        foreach ($occurrences as $occurrence) {
            $baseline = $before->get($occurrence->sequence_number - $offset);
            $replacement = $after->get($occurrence->sequence_number - $offset);
            if ($occurrence->sequence_number <= $offset || $baseline === null || $replacement === null
                || ! $occurrence->expected_date->isAfter(today())
                || ! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED], true)
                || $occurrence->converted_transaction_id !== null || $occurrence->matched_transaction_id !== null
                || $occurrence->salaryDetail?->is_overridden || $occurrence->salaryDetail?->components->isNotEmpty()
                || $occurrence->amount_confirmed_at !== null || $occurrence->account_id !== null
                || $occurrence->automatic_posting_blocked_at !== null
                || $occurrence->paymentAllocations()->exists() || $occurrence->sharedExpenseLedgerEntry()->exists()
                || $occurrence->expected_date->toDateString() !== $baseline['expected_date']
                || $occurrence->due_date?->toDateString() !== $baseline['due_date']
                || (int) round((float) $occurrence->expected_amount * 100) !== (int) round((float) $baseline['expected_amount'] * 100)
                || $occurrence->notes !== $baseline['notes']) {
                continue;
            }
            $mutable->push($occurrence);
        }
        $protectedDates = $occurrences->reject(fn (RecurringEntryOccurrence $occurrence): bool => $mutable->contains('id', $occurrence->id))
            ->reject(fn (RecurringEntryOccurrence $occurrence): bool => $occurrence->status === RecurringOccurrenceStatusEnum::CANCELLED)
            ->map(fn (RecurringEntryOccurrence $occurrence): string => $occurrence->expected_date->toDateString());

        return $mutable->map(function (RecurringEntryOccurrence $occurrence) use ($after, $offset, $protectedDates): array {
            $replacement = $after->get($occurrence->sequence_number - $offset);

            return ['occurrence' => $occurrence, 'replacement' => $replacement, 'cancel' => $protectedDates->contains($replacement['expected_date'])];
        })->values();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function previewDatesWithinRange(
        RecurringEntry $entry,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?int $maxOccurrences = null,
    ): Collection {
        if ($entry->status !== RecurringEntryStatusEnum::ACTIVE || ! $entry->is_active) {
            return collect();
        }

        $period = app(RecurringEntryPeriodService::class)->current($entry);
        if ($period?->kind === 'reactivated') {
            $entry = app(RecurringEntryPeriodService::class)->schedule($entry, $period);
        }

        return collect($this->buildRecurringDates($entry, $endDate, $maxOccurrences))
            ->filter(fn (CarbonImmutable $date): bool => $date->betweenIncluded($startDate, $endDate))
            ->values();
    }

    /**
     * Preview planned recurring rows without persisting or replacing occurrence overrides.
     * This is not a list of mutable occurrences; synchronization retains that responsibility.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function previewScheduleWithinRange(
        RecurringEntry $entry,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?int $maxOccurrences = null,
    ): Collection {
        if ($entry->entry_type !== RecurringEntryTypeEnum::RECURRING
            || $entry->status !== RecurringEntryStatusEnum::ACTIVE || ! $entry->is_active) {
            return collect();
        }

        $period = app(RecurringEntryPeriodService::class)->current($entry);
        $reactivated = $period?->kind === 'reactivated';
        $schedule = $reactivated ? app(RecurringEntryPeriodService::class)->schedule($entry, $period) : $entry;
        $changes = $reactivated ? $entry->amountChanges()->reorder('effective_from')->orderBy('id')->get() : null;

        return collect($this->recurringSchedule($schedule, $endDate, $maxOccurrences, $changes, $entry))
            ->filter(fn (array $row): bool => $row['expected_date'] >= $startDate->toDateString()
                && $row['expected_date'] <= $endDate->toDateString()
                && (! $reactivated || ! $period->ends_on || $row['expected_date'] < $period->ends_on->toDateString()))
            ->map(function (array $row) use ($reactivated, $period): array {
                $row['sequence_number'] += $reactivated ? $period->sequence_offset : 0;

                return $row;
            })
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function installmentSchedule(RecurringEntry $entry): array
    {
        $count = (int) $entry->installments_count;
        $amounts = $this->allocator->allocate((float) $entry->total_amount, $count);
        $dates = $this->buildRecurringDates($entry, null, $count);
        $schedule = [];

        foreach ($dates as $index => $date) {
            $schedule[] = [
                'sequence_number' => $index + 1,
                'expected_date' => $date->toDateString(),
                'due_date' => $date->toDateString(),
                'expected_amount' => $amounts[$index],
                ...$this->sharedExpenseAllocationForAmount($entry, (string) $amounts[$index]),
                'status' => RecurringOccurrenceStatusEnum::PENDING->value,
                'notes' => $entry->notes,
            ];
        }

        return $schedule;
    }

    /**
     * @param  Collection<int, RecurringEntryAmountChange>|null  $manualChanges
     * @return array<int, array<string, mixed>>
     */
    protected function recurringSchedule(RecurringEntry $entry, ?CarbonImmutable $throughDate, ?int $maxOccurrences, ?Collection $manualChanges = null, ?RecurringEntry $amountEntry = null): array
    {
        $dates = $this->buildRecurringDates($entry, $throughDate, $maxOccurrences);
        $historyOffset = $this->refundedHistoryOffset($entry);
        $source = $amountEntry ?? $entry;
        if ($manualChanges === null && $source->promotion !== null) {
            $manualChanges = $source->amountChanges()->reorder('effective_from')->orderBy('id')->get();
        }

        return array_map(function (CarbonImmutable $date, int $index) use ($entry, $historyOffset, $manualChanges, $amountEntry): array {
            $amount = $this->amountResolver->resolve($amountEntry ?? $entry, $date, $manualChanges);

            return [
                'sequence_number' => $historyOffset + $index + 1,
                'expected_date' => $date->toDateString(),
                'due_date' => $date->toDateString(),
                'expected_amount' => $amount,
                ...$this->sharedExpenseAllocationForAmount($entry, (string) $amount),
                'status' => RecurringOccurrenceStatusEnum::PENDING->value,
                'notes' => $entry->notes,
            ];
        }, $dates, array_keys($dates));
    }

    public function billingDateForPayment(RecurringEntry $entry, int $paymentNumber): CarbonImmutable
    {
        $schedule = clone $entry;
        $schedule->end_mode = RecurringEndModeEnum::NEVER;
        $schedule->setRelation('salarySchedule', null);

        return $this->buildRecurringDates($schedule, null, $paymentNumber)[$paymentNumber - 1];
    }

    protected function refundedHistoryOffset(RecurringEntry $entry): int
    {
        if ($entry->getAttribute('period_schedule') || $entry->entry_type !== RecurringEntryTypeEnum::RECURRING) {
            return 0;
        }
        $hasRefundedHistory = $entry->occurrences()
            ->whereDate('expected_date', '<', $entry->firstBillingDate())
            ->whereHas('convertedTransaction.refundTransaction')->exists();

        return $hasRefundedHistory ? (int) $entry->occurrences()
            ->whereNotNull('converted_transaction_id')
            ->whereDate('expected_date', '<', $entry->firstBillingDate())
            ->max('sequence_number') : 0;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function sharedExpenseAllocationForAmount(RecurringEntry $entry, string $amount): array
    {
        if ($entry->shared_expense_counterparty_id === null) {
            return ['shared_expense_paid_by' => null, 'shared_expense_my_share_cents' => null, 'shared_expense_counterparty_share_cents' => null];
        }

        $totalCents = (int) round((float) $amount * 100);
        $planTotalCents = (int) round((float) ($entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT ? $entry->total_amount : $entry->expected_amount) * 100);
        $myShareCents = $planTotalCents > 0 ? intdiv($totalCents * (int) $entry->shared_expense_my_share_cents, $planTotalCents) : intdiv($totalCents, 2);

        return ['shared_expense_paid_by' => $entry->shared_expense_paid_by ?? 'self', 'shared_expense_my_share_cents' => $myShareCents, 'shared_expense_counterparty_share_cents' => $totalCents - $myShareCents];
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    public function nextDateOnOrAfter(RecurringEntry $entry, CarbonImmutable $date): CarbonImmutable
    {
        $period = app(RecurringEntryPeriodService::class)->current($entry);
        if ($period?->kind === 'reactivated') {
            $entry = app(RecurringEntryPeriodService::class)->schedule($entry, $period);
        }
        for ($sequence = 1; $sequence <= 10000; $sequence++) {
            $candidate = $this->resolveDateForSequence($entry, $sequence);
            if ($candidate->gte($date) && $candidate->gte($entry->firstBillingDate())) {
                return $candidate;
            }
        }
        throw new \RuntimeException('No future recurring date found.');
    }

    protected function buildRecurringDates(RecurringEntry $entry, ?CarbonImmutable $throughDate, ?int $maxOccurrences): array
    {
        $startDate = $entry->firstBillingDate();
        $effectiveMaxOccurrences = $entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
            ? (int) $entry->installments_count
            : ($maxOccurrences ?? $this->resolveMaxOccurrences($entry, $throughDate));
        $effectiveThroughDate = $this->resolveThroughDate($entry, $throughDate);
        $salarySchedule = $entry->salarySchedule;
        if ($salarySchedule?->valid_from && $salarySchedule->valid_from->gt($startDate)) {
            $startDate = CarbonImmutable::parse($salarySchedule->valid_from);
        }
        $employmentEnd = $salarySchedule?->employment?->end_date;
        if ($salarySchedule?->valid_to && ($employmentEnd === null || $salarySchedule->valid_to->lt($employmentEnd))) {
            $employmentEnd = $salarySchedule->valid_to;
        }
        if ($employmentEnd !== null && ($effectiveThroughDate === null || $employmentEnd->lt($effectiveThroughDate))) {
            $effectiveThroughDate = CarbonImmutable::parse($employmentEnd);
        }
        $dates = [];
        $candidateSequence = 1;

        while (count($dates) < $effectiveMaxOccurrences) {
            $nextDate = $this->resolveDateForSequence($entry, $candidateSequence);
            $candidateSequence++;

            if ($nextDate->lessThan($startDate)) {
                continue;
            }

            if ($effectiveThroughDate !== null && $nextDate->greaterThan($effectiveThroughDate)) {
                break;
            }

            $dates[] = $nextDate;

            if ($entry->end_mode === RecurringEndModeEnum::UNTIL_DATE
                && $nextDate->greaterThanOrEqualTo($effectiveThroughDate)) {
                break;
            }
        }

        return $dates;
    }

    protected function resolveThroughDate(RecurringEntry $entry, ?CarbonImmutable $throughDate): ?CarbonImmutable
    {
        if ($entry->usesRollingAnnualHorizon() && $entry->end_mode === RecurringEndModeEnum::UNTIL_DATE && $throughDate !== null) {
            return CarbonImmutable::parse($entry->end_date)->min($throughDate);
        }

        return match ($entry->end_mode) {
            RecurringEndModeEnum::UNTIL_DATE => CarbonImmutable::parse($entry->end_date),
            default => $throughDate,
        };
    }

    protected function resolveMaxOccurrences(RecurringEntry $entry, ?CarbonImmutable $throughDate): int
    {
        return match ($entry->end_mode) {
            RecurringEndModeEnum::AFTER_OCCURRENCES => (int) $entry->occurrences_limit,
            RecurringEndModeEnum::UNTIL_DATE => 1000,
            default => max(1, $this->estimateOpenEndedOccurrences($entry, $throughDate)),
        };
    }

    protected function estimateOpenEndedOccurrences(RecurringEntry $entry, ?CarbonImmutable $throughDate): int
    {
        if ($throughDate instanceof CarbonImmutable) {
            return max(1000, (int) $entry->firstBillingDate()->diffInDays($throughDate, false) + 1);
        }

        return match ($entry->recurrence_type) {
            RecurringEntryRecurrenceTypeEnum::DAILY => 30,
            RecurringEntryRecurrenceTypeEnum::WEEKLY => 26,
            default => 12,
        };
    }

    protected function resolveDateForSequence(RecurringEntry $entry, int $sequenceNumber): CarbonImmutable
    {
        return match ($entry->recurrence_type) {
            RecurringEntryRecurrenceTypeEnum::DAILY => $entry->firstBillingDate()
                ->addDays(($sequenceNumber - 1) * max(1, (int) $entry->recurrence_interval)),
            RecurringEntryRecurrenceTypeEnum::WEEKLY => $this->resolveWeeklyDate($entry, $sequenceNumber),
            RecurringEntryRecurrenceTypeEnum::MONTHLY => $this->resolveMonthlyDate($entry, $sequenceNumber, 1),
            RecurringEntryRecurrenceTypeEnum::QUARTERLY => $this->resolveMonthlyDate($entry, $sequenceNumber, 3),
            RecurringEntryRecurrenceTypeEnum::YEARLY => $this->resolveYearlyDate($entry, $sequenceNumber),
            default => throw new \InvalidArgumentException('Unsupported recurrence type.'),
        };
    }

    protected function resolveWeeklyDate(RecurringEntry $entry, int $sequenceNumber): CarbonImmutable
    {
        $startDate = $entry->firstBillingDate();
        $weekdays = collect($entry->recurrence_rule['weekdays'] ?? [$this->weekdayCode($startDate)])
            ->map(fn (string $weekday): int => $this->weekdayNumber($weekday))
            ->sort()
            ->values();
        $weekdays = $weekdays->unique()->values();
        if ($weekdays->isEmpty()) {
            throw new \InvalidArgumentException('A weekly recurrence requires at least one weekday.');
        }
        $firstWeekdays = $weekdays->filter(fn (int $day): bool => $day >= $startDate->dayOfWeekIso)->values();
        if ($sequenceNumber <= $firstWeekdays->count()) {
            return $startDate->startOfWeek()->addDays($firstWeekdays[$sequenceNumber - 1] - 1);
        }
        $index = $sequenceNumber - $firstWeekdays->count() - 1;
        $week = (intdiv($index, $weekdays->count()) + 1) * max(1, (int) $entry->recurrence_interval);

        return $startDate->startOfWeek()->addWeeks($week)->addDays($weekdays[$index % $weekdays->count()] - 1);
    }

    protected function resolveMonthlyDate(RecurringEntry $entry, int $sequenceNumber, int $monthMultiplier): CarbonImmutable
    {
        $baseDate = $entry->firstBillingDate();
        $monthOffset = ($sequenceNumber - 1) * max(1, (int) $entry->recurrence_interval) * $monthMultiplier;
        $targetMonth = $baseDate->startOfMonth()->addMonthsNoOverflow($monthOffset);
        $rule = $entry->recurrence_rule ?? [];
        $mode = $rule['mode'] ?? 'day_of_month';

        if ($mode === 'ordinal_weekday') {
            return $this->ordinalWeekdayDate(
                $targetMonth,
                (string) $rule['ordinal'],
                (string) $rule['weekday']
            );
        }

        $day = (int) ($rule['day'] ?? $entry->due_day ?? $baseDate->day);

        return $targetMonth->day(min($day, $targetMonth->endOfMonth()->day));
    }

    protected function resolveYearlyDate(RecurringEntry $entry, int $sequenceNumber): CarbonImmutable
    {
        $baseDate = $entry->firstBillingDate();
        $rule = $entry->recurrence_rule ?? [];
        $mode = $rule['mode'] ?? 'month_day';
        $yearOffset = ($sequenceNumber - 1) * max(1, (int) $entry->recurrence_interval);
        $targetYear = $baseDate->addYears($yearOffset)->year;
        $month = (int) ($rule['month'] ?? $baseDate->month);

        if ($mode === 'ordinal_weekday') {
            return $this->ordinalWeekdayDate(
                CarbonImmutable::create($targetYear, $month, 1)->startOfMonth(),
                (string) $rule['ordinal'],
                (string) $rule['weekday']
            );
        }

        $day = (int) ($rule['day'] ?? $baseDate->day);
        $targetMonth = CarbonImmutable::create($targetYear, $month, 1)->startOfMonth();

        return $targetMonth->day(min($day, $targetMonth->endOfMonth()->day));
    }

    protected function ordinalWeekdayDate(CarbonImmutable $month, string $ordinal, string $weekday): CarbonImmutable
    {
        $weekdayNumber = $this->weekdayNumber($weekday);

        if ($ordinal === 'last') {
            $cursor = $month->endOfMonth();

            while ($cursor->dayOfWeekIso !== $weekdayNumber) {
                $cursor = $cursor->subDay();
            }

            return $cursor;
        }

        $index = [
            'first' => 1,
            'second' => 2,
            'third' => 3,
            'fourth' => 4,
        ][$ordinal];

        $cursor = $month->startOfMonth();
        $matches = 0;

        while ($cursor->month === $month->month) {
            if ($cursor->dayOfWeekIso === $weekdayNumber) {
                $matches++;
            }

            if ($matches === $index) {
                return $cursor;
            }

            $cursor = $cursor->addDay();
        }

        return $month->endOfMonth();
    }

    protected function weekdayCode(CarbonImmutable $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            default => 'sun',
        };
    }

    protected function weekdayNumber(string $weekday): int
    {
        return [
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
            'sun' => 7,
        ][$weekday];
    }

    protected function updateNextOccurrenceDate(RecurringEntry $entry): void
    {
        $lastOccurrence = $entry->occurrences()->orderByDesc('sequence_number')->first();

        if (! $lastOccurrence instanceof RecurringEntryOccurrence) {
            $entry->forceFill([
                'next_occurrence_date' => $entry->firstBillingDate(),
            ])->save();

            return;
        }

        if ($entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
            && $lastOccurrence->sequence_number >= (int) $entry->installments_count) {
            $entry->forceFill([
                'next_occurrence_date' => null,
            ])->save();

            return;
        }

        if ($entry->end_mode === RecurringEndModeEnum::AFTER_OCCURRENCES
            && $lastOccurrence->sequence_number >= (int) $entry->occurrences_limit) {
            $entry->forceFill([
                'next_occurrence_date' => null,
            ])->save();

            return;
        }

        $entry->forceFill([
            'next_occurrence_date' => $this->resolveDateForSequence($entry, $lastOccurrence->sequence_number + 1 - $this->refundedHistoryOffset($entry))->toDateString(),
        ])->save();
    }
}
