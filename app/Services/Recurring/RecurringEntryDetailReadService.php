<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringPlanRefundPayment;
use App\Models\Transaction;
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
    public function summary(RecurringEntry $entry, ?Collection $owners = null): array
    {
        $progressOffset = $owners !== null ? null : $entry->periods()->where('kind', 'reactivated')->reorder('id', 'desc')->first()?->sequence_offset;
        $summary = ['current_period_settled_occurrences' => $progressOffset === null ? null : 0, 'total_occurrences' => 0, 'settled_occurrences' => 0, 'pending_occurrences' => 0,
            'converted_occurrences' => 0, 'converted_amount' => 0, 'remaining_amount' => 0,
            'paid_count' => 0, 'paid_amount' => 0, 'paid_year' => $entry->entry_type?->value === 'installment' ? null : today()->year,
            'nearest_occurrence' => null, 'next_date' => null, 'next_amount' => null, 'generated_until' => null, 'overdue_count' => 0,
            'can_realign_refunded_schedule' => false, 'overdue_by_year' => []];
        $seen = [];
        $nearestRank = null;
        $this->visibleOccurrences($entry, $owners)->orderByRaw('COALESCE(due_date, expected_date)')->orderBy('sequence_number')->orderBy('id')
            ->with(['convertedTransaction.refundTransaction', 'paymentAllocations.transaction.refundTransaction', 'sharedExpenseLedgerEntry'])
            ->chunk(200, function (Collection $rows) use ($entry, $owners, $progressOffset, &$summary, &$seen, &$nearestRank): void {
                $rows->each(fn ($row) => $row->setRelation('recurringEntry', $owners?->firstWhere('id', $row->recurring_entry_id) ?? $entry));
                $this->payments->prepare($rows);
                foreach ($rows as $row) {
                    $remaining = $this->payments->remaining($row);
                    $summary['total_occurrences']++;
                    $date = ($row->due_date ?? $row->expected_date)->toDateString();
                    $archivedFuture = $row->recurringEntry->isArchivedComponent() && $date >= today()->toDateString();
                    $summary['generated_until'] = max($summary['generated_until'] ?? $date, $date);
                    if (! $archivedFuture && ! in_array($row->status?->value, ['skipped', 'cancelled'], true) && ! $row->isRetiredRefundedHistory()) {
                        $due = $row->due_date ?? $row->expected_date;
                        $rank = [abs((int) today()->diffInDays($due, false)), $remaining > 0 ? 0 : 1, $due->lt(today()) ? 1 : 0, $row->sequence_number, $row->id];
                        if ($nearestRank === null || $rank < $nearestRank) {
                            $nearestRank = $rank;
                            $summary['nearest_occurrence'] = ['uuid' => $row->uuid, 'date' => $date];
                        }
                    }
                    if (($row->externalPaymentCents() > 0 || $row->converted_transaction_id !== null || $row->matched_transaction_id !== null || $this->payments->allocated($row) > 0) && $remaining === 0) {
                        $summary['settled_occurrences']++;
                        if ($progressOffset !== null && $row->sequence_number > $progressOffset) {
                            $summary['current_period_settled_occurrences']++;
                        }
                    }
                    if (! $archivedFuture && in_array($row->status?->value, ['pending', 'generated', 'refunded'], true) && $remaining > 0 && ! $row->isRetiredRefundedHistory()) {
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

    /** @return array<int, array{transaction_uuid: string, date: string, amount: float}> */
    public function costTrend(RecurringEntry $entry): array
    {
        if ($entry->entry_type?->value !== 'recurring') {
            return [];
        }

        $points = [];
        $this->visibleOccurrences($entry)
            ->whereNotIn('status', ['skipped', 'cancelled'])
            ->with('paymentAllocations.transaction.refundTransaction')
            ->chunkById(200, function (Collection $rows) use ($entry, &$points): void {
                $rows->each(fn ($row) => $row->setRelation('recurringEntry', $entry));
                $this->payments->prepare($rows);
                foreach ($rows as $row) {
                    if ($this->payments->remaining($row) !== 0) {
                        continue;
                    }
                    $transactions = $row->personalReadTransactions
                        ->concat($row->paymentAllocations->pluck('transaction')->filter())
                        ->unique('id')
                        ->filter(fn (Transaction $transaction): bool => $transaction->status?->value === 'confirmed'
                            && $transaction->refundTransaction?->status?->value !== 'confirmed'
                            && $transaction->transaction_date !== null
                            && $transaction->transaction_date->lte(today())
                            && $transaction->currency === $entry->currency
                            && $transaction->activeAmountCents() > 0);
                    if ($transactions->isEmpty()) {
                        continue;
                    }
                    foreach ($transactions as $transaction) {
                        $points[$transaction->uuid] = [
                            'transaction_uuid' => $transaction->uuid,
                            'date' => $transaction->transaction_date->toDateString(),
                            'amount' => $transaction->activeAmountCents() / 100,
                        ];
                    }
                }
            });

        return collect($points)->sortBy('date')->values()->all();
    }

    private function visibleOccurrences(RecurringEntry $entry, ?Collection $owners = null): Builder
    {
        $maximumYear = app(UserYearCreationPolicy::class)->maximumCreatableYear($entry->user);

        return RecurringEntryOccurrence::query()->whereIn('recurring_entry_id', $owners?->modelKeys() ?? [$entry->id])->whereRaw('COALESCE(due_date, expected_date) < ?', [($maximumYear + 1).'-01-01']);
    }

    private function inPeriod(CarbonInterface $date, ?int $year): bool
    {
        return $date->lte(today()) && ($year === null || $date->year === $year);
    }

    /** @return array<int, array{year: int, count: int}> */
    public function years(RecurringEntry $entry, ?Collection $owners = null): array
    {
        $yearExpression = $entry->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', COALESCE(due_date, expected_date)) AS INTEGER)"
            : 'EXTRACT(YEAR FROM COALESCE(due_date, expected_date))';

        return $this->visibleOccurrences($entry, $owners)->selectRaw($yearExpression." as occurrence_year, COUNT(*) as aggregate, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->groupBy('occurrence_year')->orderBy('occurrence_year')->get()
            ->map(fn ($row): array => ['year' => (int) $row->occurrence_year, 'count' => (int) $row->aggregate, 'cancelled_count' => (int) $row->cancelled_count])->all();
    }

    /** @return array{rows: Collection, offset: int, next_offset: int|null} */
    public function page(RecurringEntry $entry, int $year, ?int $offset = null, ?string $highlight = null, ?Collection $owners = null, bool $includeCancelled = false): array
    {
        $includeCancelled = $includeCancelled || ($highlight !== null && $this->visibleOccurrences($entry, $owners)->where('uuid', $highlight)->where('status', 'cancelled')->exists());
        $query = $this->visibleOccurrences($entry, $owners)->whereRaw('COALESCE(due_date, expected_date) >= ? AND COALESCE(due_date, expected_date) < ?', [$year.'-01-01', ($year + 1).'-01-01'])
            ->when(! $includeCancelled, fn (Builder $query) => $query->where('status', '!=', 'cancelled'))
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
        $refundedPayments = RecurringPlanRefundPayment::query()->whereIn('occurrence_id', $rows->modelKeys())
            ->get(['occurrence_id', 'transaction_id'])->groupBy('occurrence_id');
        foreach ($rows as $row) {
            $row->setAttribute('read_aggregate_refund_transactions', ($refundedPayments->get($row->id) ?? collect())->pluck('transaction_id')->map(fn ($id): int => (int) $id)->unique()->all());
        }
        $owners ??= new Collection([$entry]);
        foreach ($owners as $owner) {
            $ownedRows = $rows->where('recurring_entry_id', $owner->id);
            if ($ownedRows->isEmpty()) {
                continue;
            }
            $openIds = $owner->occurrences()->openObligations()->whereKey($ownedRows->modelKeys())->pluck('id');
            $latest = $owner->occurrences()->whereNotNull('converted_transaction_id')->orderByRaw('COALESCE(due_date, expected_date) desc')->orderByDesc('sequence_number')->orderByDesc('id')->value('id');
            foreach ($ownedRows as $row) {
                $row->setRelation('recurringEntry', $owner);
                $row->setAttribute('read_can_register_payment', $openIds->contains($row->id));
                $row->setAttribute('read_can_refund', $latest === $row->id);
            }
        }
        $this->payments->prepare($rows);

        return ['show_cancelled' => $includeCancelled, 'rows' => $rows, 'offset' => $offset, 'next_offset' => $hasMore ? $offset + self::PAGE_SIZE : null];
    }
}
