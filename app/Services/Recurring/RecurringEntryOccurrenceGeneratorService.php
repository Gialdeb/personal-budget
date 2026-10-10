<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringEntryOccurrenceGeneratorService
{
    public function __construct(
        protected InstallmentAmountAllocatorService $allocator
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
            $entry->refresh();
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

                $created->push(
                    $entry->occurrences()->create($item)
                );
            }

            if ($updateNextOccurrenceDate) {
                $this->updateNextOccurrenceDate($entry);
            }

            return $created;
        });
    }

    /**
     * Preserve occurrence overrides and history when the parent plan changes.
     */
    public function synchronizeFutureOccurrences(RecurringEntry $entry, RecurringEntry $original): ?string
    {
        $occurrences = $entry->occurrences()->lockForUpdate()->get();
        $limit = max(1, (int) $occurrences->max('sequence_number'));
        $before = collect($original->entry_type === RecurringEntryTypeEnum::INSTALLMENT
            ? $this->installmentSchedule($original)
            : $this->recurringSchedule($original, null, $limit))->keyBy('sequence_number');
        $after = collect($entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
            ? $this->installmentSchedule($entry)
            : $this->recurringSchedule($entry, null, $limit))->keyBy('sequence_number');

        $mutable = collect();
        foreach ($occurrences as $occurrence) {
            $baseline = $before->get($occurrence->sequence_number);
            $replacement = $after->get($occurrence->sequence_number);
            if ($baseline === null || $replacement === null
                || ! $occurrence->expected_date->isAfter(today())
                || ! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED], true)
                || $occurrence->converted_transaction_id !== null || $occurrence->matched_transaction_id !== null
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
        $firstChangedAmountDate = null;
        foreach ($mutable as $occurrence) {
            $replacement = $after->get($occurrence->sequence_number);
            if ($protectedDates->contains($replacement['expected_date'])) {
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
        }

        return $firstChangedAmountDate;
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

        return collect($this->buildRecurringDates($entry, $endDate, $maxOccurrences))
            ->filter(fn (CarbonImmutable $date): bool => $date->betweenIncluded($startDate, $endDate))
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
     * @return array<int, array<string, mixed>>
     */
    protected function recurringSchedule(RecurringEntry $entry, ?CarbonImmutable $throughDate, ?int $maxOccurrences): array
    {
        $dates = $this->buildRecurringDates($entry, $throughDate, $maxOccurrences);
        $historyOffset = $this->refundedHistoryOffset($entry);

        return array_map(function (CarbonImmutable $date, int $index) use ($entry, $historyOffset): array {
            return [
                'sequence_number' => $historyOffset + $index + 1,
                'expected_date' => $date->toDateString(),
                'due_date' => $date->toDateString(),
                'expected_amount' => $entry->expected_amount,
                ...$this->sharedExpenseAllocationForAmount($entry, (string) $entry->expected_amount),
                'status' => RecurringOccurrenceStatusEnum::PENDING->value,
                'notes' => $entry->notes,
            ];
        }, $dates, array_keys($dates));
    }

    protected function refundedHistoryOffset(RecurringEntry $entry): int
    {
        if ($entry->entry_type !== RecurringEntryTypeEnum::RECURRING) {
            return 0;
        }
        $hasRefundedHistory = $entry->occurrences()->where('status', RecurringOccurrenceStatusEnum::REFUNDED)
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
    protected function sharedExpenseAllocationForAmount(RecurringEntry $entry, string $amount): array
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
    protected function buildRecurringDates(RecurringEntry $entry, ?CarbonImmutable $throughDate, ?int $maxOccurrences): array
    {
        $startDate = $entry->firstBillingDate();
        $effectiveMaxOccurrences = $entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
            ? (int) $entry->installments_count
            : ($maxOccurrences ?? $this->resolveMaxOccurrences($entry, $throughDate));
        $effectiveThroughDate = $this->resolveThroughDate($entry, $throughDate);
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
            return 1000;
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
        $occurrences = [];
        $cursor = $startDate;

        while (count($occurrences) < $sequenceNumber) {
            $weeksSinceStart = intdiv($cursor->startOfWeek()->diffInDays($startDate->startOfWeek()), 7);

            if ($weeksSinceStart % max(1, (int) $entry->recurrence_interval) === 0
                && $weekdays->contains($cursor->dayOfWeekIso)
                && $cursor->greaterThanOrEqualTo($startDate)) {
                $occurrences[] = $cursor;
            }

            $cursor = $cursor->addDay();
        }

        return $occurrences[$sequenceNumber - 1];
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
