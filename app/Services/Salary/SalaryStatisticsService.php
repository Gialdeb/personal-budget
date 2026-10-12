<?php

namespace App\Services\Salary;

use App\Models\SalaryEmployment;
use App\Models\SalaryOccurrenceDetail;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class SalaryStatisticsService
{
    /** @return array<string, mixed> */
    public function comparison(SalaryEmployment $employment, string $period): array
    {
        $currentDate = CarbonImmutable::parse($period.'-01');
        $previousDate = $currentDate->subYear();
        $current = $this->period($employment, $currentDate);
        $previous = $this->period($employment, $previousDate);
        $comparable = $current !== null && $previous !== null
            && $current['currencies'] === $previous['currencies'] && count($current['currencies']) === 1
            && $current['ordinary_schedule_ids'] === $previous['ordinary_schedule_ids']
            && $current['ordinary_periods'] === $previous['ordinary_periods']
            && $current['ordinary_count'] === $previous['ordinary_count'] && $previous['base'] > 0;

        return ['current' => $current, 'previous' => $previous, 'period' => $currentDate->format('Y-m'),
            'previous_period' => $previousDate->format('Y-m'),
            'base_percentage' => $comparable ? round(($current['base'] - $previous['base']) / $previous['base'] * 100, 2) : null];
    }

    /** @return array<string, mixed> */
    public function annual(SalaryEmployment $employment, int $year): array
    {
        $details = $employment->details()->with(['components', 'schedule', 'occurrence.convertedTransaction', 'occurrence.matchedTransaction', 'occurrence.paymentAllocations' => fn ($query) => $query->valid()->with('transaction')])->get();
        $received = 0;
        $count = 0;
        $currencies = collect();
        $seen = [];
        foreach ($details as $detail) {
            $occurrence = $detail->occurrence;
            if ($occurrence->status->value === 'refunded') {
                continue;
            }
            $transaction = $occurrence->convertedTransaction ?? $occurrence->matchedTransaction;
            if ($transaction && $transaction->status?->value === 'confirmed' && $transaction->transaction_date->year === $year && ! isset($seen[$transaction->id])) {
                $received += (int) round((float) $transaction->amount * 100);
                $count++;
                $currencies->push($detail->currency);
                $seen[$transaction->id] = true;
            }
            if (! $transaction) {
                foreach ($occurrence->paymentAllocations as $allocation) {
                    if ($allocation->transaction?->status?->value === 'confirmed' && $allocation->transaction->transaction_date->year === $year) {
                        $received += $allocation->amount_cents;
                        $count++;
                        $currencies->push($detail->currency);
                    }
                }
            }
        }
        $yearDetails = $details->filter(fn ($detail) => $detail->pay_period_start->year === $year && ! in_array($detail->occurrence->status->value, ['cancelled', 'skipped', 'refunded'], true));
        $currencies = $currencies->merge($yearDetails->pluck('currency'))->unique()->values();
        $single = $currencies->count() === 1;

        return ['year' => $year, 'currency' => $single ? $currencies->first() : null, 'received' => $single && $count ? $received / 100 : null, 'average' => $single && $count ? round($received / 100 / $count, 2) : null,
            'base' => $single ? round((float) $yearDetails->filter(fn ($detail) => $detail->schedule->kind === 'ordinary')->sum('base_expected_amount'), 2) : null,
            'components' => $single ? round((float) $yearDetails->sum(fn ($detail) => $detail->components->sum('amount')), 2) : null];
    }

    /** @return array<string, mixed>|null */
    protected function period(SalaryEmployment $employment, CarbonImmutable $month): ?array
    {
        $details = $employment->details()->whereBetween('pay_period_start', [$month->startOfMonth(), $month->endOfMonth()])
            ->whereHas('occurrence', fn ($query) => $query->whereNotIn('status', ['cancelled', 'skipped', 'refunded']))
            ->with(['schedule', 'components', 'occurrence.convertedTransaction', 'occurrence.matchedTransaction', 'occurrence.paymentAllocations' => fn ($query) => $query->valid()->with('transaction')])->get();
        if ($details->isEmpty()) {
            return null;
        }
        $currencies = $details->pluck('currency')->unique()->sort()->values()->all();
        $ordinary = $details->filter(fn (SalaryOccurrenceDetail $detail): bool => $detail->schedule->kind === 'ordinary');
        $actual = 0;
        $hasActual = false;
        foreach ($details as $detail) {
            $occurrence = $detail->occurrence;
            $transaction = $occurrence->convertedTransaction ?? $occurrence->matchedTransaction;
            if ($transaction instanceof Transaction && $transaction->status?->value === 'confirmed') {
                $actual += (int) round((float) $transaction->amount * 100);
                $hasActual = true;
            } else {
                foreach ($occurrence->paymentAllocations as $allocation) {
                    if ($allocation->transaction?->status?->value === 'confirmed' && $allocation->transaction?->deleted_at === null) {
                        $actual += $allocation->amount_cents;
                        $hasActual = true;
                    }
                }
            }
        }
        $singleCurrency = count($currencies) === 1;

        return [
            'currencies' => $currencies,
            'base' => $singleCurrency ? round((float) $ordinary->sum('base_expected_amount'), 2) : null,
            'additional' => $singleCurrency ? round((float) $details->reject(fn ($detail) => $detail->schedule->kind === 'ordinary')->sum('base_expected_amount'), 2) : null,
            'components' => $singleCurrency ? round((float) $details->sum(fn ($detail) => $detail->components->sum('amount')), 2) : null,
            'expected' => $singleCurrency ? round((float) $details->sum(fn ($detail) => $detail->expectedTotal()), 2) : null,
            'difference' => $singleCurrency && $hasActual ? round($actual / 100 - (float) $details->filter(fn ($detail) => $detail->occurrence->convertedTransaction || $detail->occurrence->matchedTransaction || $detail->occurrence->paymentAllocations->isNotEmpty())->sum(fn ($detail) => $detail->expectedTotal()), 2) : null,
            'actual' => $singleCurrency && $hasActual ? (float) ($actual / 100) : null,
            'ordinary_schedule_ids' => $ordinary->map(fn ($detail) => $detail->schedule->logicalId())->unique()->sort()->values()->all(),
            'ordinary_count' => $ordinary->count(),
            'ordinary_periods' => $ordinary->map(fn ($detail): string => $detail->schedule->logicalId().':'.$detail->pay_period_start->format('m-d').':'.($detail->pay_period_end->isLastOfMonth() ? $detail->pay_period_end->format('m').'-last' : $detail->pay_period_end->format('m-d')))->sort()->values()->all(),
        ];
    }
}
