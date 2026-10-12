<?php

namespace App\Services\Salary;

use App\Models\RecurringEntry;
use App\Models\SalaryEmployment;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\RecurringEntryManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryEmploymentService
{
    public function __construct(protected RecurringEntryManagementService $management) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): RecurringEntry
    {
        return DB::transaction(function () use ($user, $data): RecurringEntry {
            $salary = $data['salary'];
            if (($data['direction'] ?? null) !== 'income' || ($data['entry_type'] ?? null) !== 'recurring' || ($data['is_shared_expense'] ?? false)) {
                throw ValidationException::withMessages(['salary' => __('salary.must_be_variable_income')]);
            }
            $employment = filled($salary['employment_uuid'] ?? null)
                ? SalaryEmployment::query()->where('user_id', $user->id)->where('uuid', $salary['employment_uuid'])->lockForUpdate()->firstOrFail()
                : SalaryEmployment::query()->create([
                    'user_id' => $user->id, 'merchant_id' => $data['merchant_id'] ?? null,
                    'name' => filled($salary['name'] ?? null) ? trim($salary['name']) : (TrackedItem::query()->where('user_id', $user->id)->find($data['tracked_item_id'] ?? null)?->name ?? $data['title']), 'start_date' => $data['start_date'],
                    'end_date' => null,
                    'annual_gross_salary' => $salary['annual_gross_salary'] ?? null,
                    'annual_gross_salary_currency' => $salary['annual_gross_salary_currency'] ?? null,
                ]);
            if (CarbonImmutable::parse($data['start_date'])->lt($employment->start_date)
                || ($employment->end_date && CarbonImmutable::parse($data['start_date'])->gt($employment->end_date))) {
                throw ValidationException::withMessages(['start_date' => __('salary.outside_employment')]);
            }
            $entry = $this->createSchedule($user, $employment, $data, $salary['kind'] ?? 'ordinary', $data['title']);
            foreach ($salary['additional_schedules'] ?? [] as $additional) {
                $date = CarbonImmutable::parse($additional['start_date']);
                if ($date->lt($employment->start_date) || ($employment->end_date && $date->gt($employment->end_date))) {
                    throw ValidationException::withMessages(['salary.additional_schedules' => __('salary.outside_employment')]);
                }
                $this->createSchedule($user, $employment, [
                    ...$data,
                    'title' => $additional['label'],
                    'start_date' => $additional['start_date'],
                    'expected_amount' => $additional['amount'],
                    'account_id' => filled($additional['account_uuid'] ?? null) ? app(AccessibleAccountsQuery::class)->editable($user)->where('accounts.uuid', $additional['account_uuid'])->value('accounts.id') : $data['account_id'],
                    'account_uuid' => null,
                    'currency' => '',
                    'recurrence_type' => $additional['recurrence_type'],
                    'recurrence_interval' => $additional['recurrence_interval'] ?? 1,
                    'recurrence_rule' => $additional['recurrence_rule'] ?? [],
                    'auto_create_transaction' => $data['auto_create_transaction'] ?? false,
                ], 'additional', $additional['label']);
            }

            $this->management->synchronizeSalaryRegistration($user, $entry, (bool) ($data['auto_create_transaction'] ?? false));

            return $entry;
        });
    }

    /** @param array<string, mixed> $data */
    protected function createSchedule(User $user, SalaryEmployment $employment, array $data, string $kind, string $label): RecurringEntry
    {
        unset($data['salary']);
        $data = [...$data, 'direction' => 'income', 'entry_type' => 'recurring', 'is_amount_variable' => true,
            'merchant_id' => $employment->merchant_id, 'is_shared_expense' => false,
            'trial_duration' => null, 'trial_unit' => null,
            'end_mode' => $employment->end_date ? 'until_date' : 'never',
            'end_date' => $employment->end_date?->toDateString()];

        return $this->management->store($user, $data, function (RecurringEntry $entry) use ($employment, $kind, $label): void {
            $employment->schedules()->create(['recurring_entry_id' => $entry->id, 'kind' => $kind, 'label' => $label]);
        });
    }

    public function end(SalaryEmployment $employment, string $endDate): void
    {
        DB::transaction(function () use ($employment, $endDate): void {
            $employment = SalaryEmployment::query()->lockForUpdate()->findOrFail($employment->id);
            if (CarbonImmutable::parse($endDate)->lt($employment->start_date)) {
                throw ValidationException::withMessages(['end_date' => __('salary.end_before_start')]);
            }
            $employment->update(['end_date' => $endDate]);
            foreach ($employment->schedules()->with('recurringEntry')->get() as $schedule) {
                $entry = $schedule->recurringEntry;
                $entry->forceFill(['end_mode' => 'until_date', 'end_date' => $schedule->valid_to && $schedule->valid_to->lt(CarbonImmutable::parse($endDate)) ? $schedule->valid_to->toDateString() : $endDate])->save();
                $entry->occurrences()->whereDate('expected_date', '>', $endDate)->whereNull('converted_transaction_id')->whereNull('matched_transaction_id')->whereDoesntHave('paymentAllocations')->whereIn('status', ['pending', 'generated'])->update(['status' => 'cancelled']);
            }
        });
    }
}
