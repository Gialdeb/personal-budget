<?php

namespace App\Services\Salary;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\SalaryOccurrenceDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\UserYearService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryOccurrenceService
{
    public function snapshot(RecurringEntryOccurrence $occurrence): void
    {
        $schedule = $occurrence->recurringEntry->salarySchedule;
        if ($schedule === null) {
            return;
        }
        $date = $occurrence->expected_date;
        $occurrence->salaryDetail()->firstOrCreate([], [
            'salary_employment_id' => $schedule->salary_employment_id,
            'salary_schedule_id' => $schedule->id,
            'pay_period_start' => $date->copy()->startOfMonth()->toDateString(),
            'pay_period_end' => $date->copy()->endOfMonth()->toDateString(),
            'base_expected_amount' => $occurrence->expected_amount,
            'currency' => $occurrence->recurringEntry->currency,
        ]);
    }

    /** @param array{base_expected_amount: numeric-string|float, pay_period_start: string, pay_period_end: string, components: array<int, array{label: string, amount: numeric-string|float, type?: string|null}>} $data */
    public function update(SalaryOccurrenceDetail $detail, User $actor, array $data): void
    {
        DB::transaction(function () use ($detail, $actor, $data): void {
            $occurrence = RecurringEntryOccurrence::query()->lockForUpdate()->findOrFail($detail->recurring_entry_occurrence_id);
            $detail = SalaryOccurrenceDetail::query()->lockForUpdate()->findOrFail($detail->id);
            if ($occurrence->converted_transaction_id || $occurrence->matched_transaction_id
                || ! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED], true)
                || $occurrence->paymentAllocations()->exists()) {
                throw ValidationException::withMessages(['salary' => __('salary.composition_locked')]);
            }
            $baseCents = (int) round((float) $data['base_expected_amount'] * 100);
            $componentCents = collect($data['components'])->sum(fn (array $row): int => (int) round((float) $row['amount'] * 100));
            $totalCents = $baseCents + $componentCents;
            if ($totalCents <= 0 || $totalCents > 99999999999999) {
                throw ValidationException::withMessages(['salary' => __('salary.total_positive')]);
            }
            $detail->components()->delete();
            foreach ($data['components'] as $index => $row) {
                $detail->components()->create([...$row, 'sort_order' => $index]);
            }
            // expected_amount is the single authoritative final expected total, used by the existing posting engine.
            app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $actor, $totalCents / 100);
            $detail->update([
                'base_expected_amount' => $baseCents / 100, 'is_overridden' => true,
                'pay_period_start' => $data['pay_period_start'], 'pay_period_end' => $data['pay_period_end'],
            ]);
        });
    }

    public function explain(SalaryOccurrenceDetail $detail, User $actor, string $label, ?string $type = null): void
    {
        DB::transaction(function () use ($detail, $actor, $label, $type): void {
            $occurrence = RecurringEntryOccurrence::query()->lockForUpdate()->findOrFail($detail->recurring_entry_occurrence_id);
            $detail = SalaryOccurrenceDetail::query()->with('components')->lockForUpdate()->findOrFail($detail->id);
            $transaction = $occurrence->convertedTransaction ?? $occurrence->matchedTransaction;
            if ($transaction) {
                $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            }
            if (! $transaction || $transaction->currency !== $detail->currency || $transaction->status?->value !== 'confirmed' || $transaction->refundTransaction()->exists()) {
                throw ValidationException::withMessages(['label' => __('salary.explain_unavailable')]);
            }
            app(UserYearService::class)->ensureDateYearIsOpen($actor, $transaction->transaction_date->toDateString(), 'label');
            $difference = (int) round((float) $transaction->amount * 100) - (int) round($detail->expectedTotal() * 100);
            if ($difference === 0) {
                throw ValidationException::withMessages(['label' => __('salary.explain_unavailable')]);
            }
            $detail->components()->create(['label' => $label, 'type' => $type, 'amount' => $difference / 100, 'sort_order' => $detail->components->count()]);
            $detail->update(['is_overridden' => true]);
            $occurrence->forceFill(['expected_amount' => (float) $transaction->amount])->save();
        });
    }

    public function isBeyondEmployment(RecurringEntryOccurrence $occurrence): bool
    {
        $schedule = $occurrence->recurringEntry->salarySchedule;
        $employment = $schedule?->employment;

        return ($employment !== null && $employment->end_date !== null && $occurrence->expected_date->gt($employment->end_date))
            || ($schedule?->valid_to !== null && $occurrence->expected_date->gt($schedule->valid_to))
            || ($schedule?->valid_from !== null && $occurrence->expected_date->lt($schedule->valid_from));
    }
}
