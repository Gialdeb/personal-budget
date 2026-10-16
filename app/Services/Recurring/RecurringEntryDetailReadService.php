<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntry;
use App\Services\UserYearCreationPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Read projections only. Accounting and mutation services remain authoritative. */
class RecurringEntryDetailReadService
{
    public const PAGE_SIZE = 12;

    public function __construct(private RecurringOccurrenceReadService $payments) {}

    /** @return array<string, mixed> */
    public function summary(RecurringEntry $entry): array
    {
        $summary = ['total_occurrences' => 0, 'settled_occurrences' => 0, 'pending_occurrences' => 0,
            'converted_occurrences' => 0, 'converted_amount' => 0, 'remaining_amount' => 0,
            'paid_count' => 0, 'paid_amount' => 0, 'paid_year' => $entry->entry_type?->value === 'installment' ? null : today()->year,
            'nearest_occurrence' => null, 'next_date' => null, 'next_amount' => null, 'generated_until' => null, 'overdue_count' => 0,
            'can_realign_refunded_schedule' => false, 'overdue_by_year' => []];
        $seen = [];
        $nearestRank = null;
        $this->visibleOccurrences($entry)->orderByRaw('COALESCE(due_date, expected_date)')->orderBy('sequence_number')->orderBy('id')
            ->with(['convertedTransaction.refundTransaction', 'paymentAllocations.transaction.refundTransaction', 'sharedExpenseLedgerEntry'])
            ->chunk(200, function (Collection $rows) use ($entry, &$summary, &$seen, &$nearestRank): void {
                $rows->each(fn ($row) => $row->setRelation('recurringEntry', $entry));
                $this->payments->prepare($rows);
                foreach ($rows as $row) {
                    $remaining = $this->payments->remaining($row);
                    $summary['total_occurrences']++;
                    $date = ($row->due_date ?? $row->expected_date)->toDateString();
                    $summary['generated_until'] = max($summary['generated_until'] ?? $date, $date);
                    if (! in_array($row->status?->value, ['skipped', 'cancelled'], true) && ! $row->isRetiredRefundedHistory()) {
                        $due = $row->due_date ?? $row->expected_date;
                        $rank = [abs((int) today()->diffInDays($due, false)), $remaining > 0 ? 0 : 1, $due->lt(today()) ? 1 : 0, $row->sequence_number, $row->id];
                        if ($nearestRank === null || $rank < $nearestRank) {
                            $nearestRank = $rank;
                            $summary['nearest_occurrence'] = ['uuid' => $row->uuid, 'date' => $date];
                        }
                    }
                    if (($row->externalPaymentCents() > 0 || $row->converted_transaction_id !== null || $row->matched_transaction_id !== null || $this->payments->allocated($row) > 0) && $remaining === 0) {
                        $summary['settled_occurrences']++;
                    }
                    if (in_array($row->status?->value, ['pending', 'generated', 'refunded'], true) && $remaining > 0 && ! $row->isRetiredRefundedHistory()) {
                        $summary['pending_occurrences']++;
                        $summary['remaining_amount'] += $remaining;
                        $due = $row->due_date ?? $row->expected_date;
                        if ($due->gte(today()) && $summary['next_date'] === null) {
                            $summary['next_date'] = $due->toDateString();
                            $summary['next_amount'] = $remaining / 100;
                        }
                        if ($due->lt(today())) {
                            $summary['overdue_count']++;
                            $year = $due->year;
                            $summary['overdue_by_year'][$year] ??= ['overdue_count' => 0, 'overdue_amount' => 0];
                            $summary['overdue_by_year'][$year]['overdue_count']++;
                            $summary['overdue_by_year'][$year]['overdue_amount'] += $remaining / 100;
                        }
                    }
                    if ($row->converted_transaction_id !== null) {
                        $summary['converted_occurrences']++;
                        $summary['converted_amount'] += (int) round((float) $row->expected_amount * 100);
                    }
                    $summary['can_realign_refunded_schedule'] = $summary['can_realign_refunded_schedule'] || $row->convertedTransaction?->refundTransaction !== null;
                    $paid = 0;
                    foreach ($row->personalReadTransactions as $transaction) {
                        if (! isset($seen[$transaction->id]) && $this->inPeriod($transaction->transaction_date, $summary['paid_year'])) {
                            $paid += $transaction->activeAmountCents();
                            $seen[$transaction->id] = true;
                        }
                    }
                    if ($row->externalPaymentCents() > 0 && $this->inPeriod($row->sharedExpenseLedgerEntry->entry_date, $summary['paid_year'])) {
                        $paid += $row->externalPaymentCents();
                    }
                    foreach ($row->paymentAllocations as $allocation) {
                        $transaction = $allocation->transaction;
                        if ($transaction && $transaction->status?->value === 'confirmed' && $transaction->refundTransaction?->status?->value !== 'confirmed' && $this->inPeriod($transaction->transaction_date, $summary['paid_year'])) {
                            $paid += $allocation->amount_cents;
                        }
                    }
                    $summary['paid_amount'] += $paid;
                    if ($paid > 0 && $remaining === 0) {
                        $summary['paid_count']++;
                    }
                }
            });
        foreach (['converted_amount', 'remaining_amount', 'paid_amount'] as $key) {
            $summary[$key] /= 100;
        }

        return $summary;
    }

    private function visibleOccurrences(RecurringEntry $entry): Builder
    {
        $maximumYear = app(UserYearCreationPolicy::class)->maximumCreatableYear($entry->user);

        return $entry->occurrences()->getQuery()->whereRaw('COALESCE(due_date, expected_date) < ?', [($maximumYear + 1).'-01-01']);
    }

    private function inPeriod(CarbonInterface $date, ?int $year): bool
    {
        return $date->lte(today()) && ($year === null || $date->year === $year);
    }

    /** @return array<int, array{year: int, count: int}> */
    public function years(RecurringEntry $entry): array
    {
        $yearExpression = $entry->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', COALESCE(due_date, expected_date)) AS INTEGER)"
            : 'EXTRACT(YEAR FROM COALESCE(due_date, expected_date))';

        return $this->visibleOccurrences($entry)->selectRaw($yearExpression.' as occurrence_year, COUNT(*) as aggregate')
            ->groupBy('occurrence_year')->orderBy('occurrence_year')->get()
            ->map(fn ($row): array => ['year' => (int) $row->occurrence_year, 'count' => (int) $row->aggregate])->all();
    }

    /** @return array{rows: Collection, offset: int, next_offset: int|null} */
    public function page(RecurringEntry $entry, int $year, ?int $offset = null, ?string $highlight = null): array
    {
        $query = $this->visibleOccurrences($entry)->whereRaw('COALESCE(due_date, expected_date) >= ? AND COALESCE(due_date, expected_date) < ?', [$year.'-01-01', ($year + 1).'-01-01'])
            ->orderByRaw('COALESCE(due_date, expected_date)')->orderBy('sequence_number')->orderBy('id');
        if ($highlight !== null) {
            $target = (clone $query)->where('uuid', $highlight)->first();
            if ($target) {
                $date = ($target->due_date ?? $target->expected_date)->toDateString();
                $before = (clone $query)->where(function ($builder) use ($target, $date): void {
                    $builder->whereRaw('COALESCE(due_date, expected_date) < ?', [$date])
                        ->orWhere(function ($sameDate) use ($target, $date): void {
                            $sameDate->whereRaw('COALESCE(due_date, expected_date) = ?', [$date])
                                ->where(fn ($sequence) => $sequence->where('sequence_number', '<', $target->sequence_number)->orWhere(fn ($tie) => $tie->where('sequence_number', $target->sequence_number)->where('id', '<', $target->id)));
                        });
                })->count();
                $offset = intdiv($before, self::PAGE_SIZE) * self::PAGE_SIZE;
            }
        }
        if ($offset === null) {
            $beforeToday = (clone $query)->whereRaw('COALESCE(due_date, expected_date) < ?', [today()->toDateString()])->count();
            $offset = max(0, min($beforeToday, max(0, (clone $query)->count() - 1)) - 2);
        }
        $rows = $query->offset($offset)->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;
        $rows = new Collection($rows->take(self::PAGE_SIZE)->all());
        $rows->load(['account', 'sharedExpenseLedgerEntry.counterparty', 'convertedTransaction.refundTransaction', 'matchedTransaction.refundTransaction',
            'paymentAllocations.transaction.account', 'paymentAllocations.transaction.trackedItem', 'paymentAllocations.transaction.refundTransaction', 'settlementTransactions.refundTransaction']);
        $openIds = $entry->occurrences()->openObligations()->whereKey($rows->modelKeys())->pluck('id');
        $latest = $entry->occurrences()->whereNotNull('converted_transaction_id')->orderByRaw('COALESCE(due_date, expected_date) desc')->orderByDesc('sequence_number')->orderByDesc('id')->value('id');
        foreach ($rows as $row) {
            $row->setRelation('recurringEntry', $entry);
            $row->setAttribute('read_can_register_payment', $openIds->contains($row->id));
            $row->setAttribute('read_can_refund', $latest === $row->id);
        }
        $this->payments->prepare($rows);

        return ['rows' => $rows, 'offset' => $offset, 'next_offset' => $hasMore ? $offset + self::PAGE_SIZE : null];
    }
}
