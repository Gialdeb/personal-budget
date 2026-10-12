<?php

namespace App\Services\Salary;

use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\SalaryEmployment;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\UserYearService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryEmploymentRevisionService
{
    public function __construct(protected RecurringEntryManagementService $management, protected RecurringEntryOccurrenceGeneratorService $generator) {}

    /** @param array<string, mixed> $data */
    public function update(User $user, SalaryEmployment $employment, array $data): void
    {
        DB::transaction(function () use ($user, $employment, $data): void {
            $employment = SalaryEmployment::query()->lockForUpdate()->findOrFail($employment->id);
            abort_unless($employment->user_id === $user->id, 404);
            $effective = CarbonImmutable::parse($data['effective_from'])->startOfDay();
            if (! $effective->isAfter(today()) || ($employment->end_date && $effective->gt($employment->end_date))) {
                throw ValidationException::withMessages(['effective_from' => __('salary.revision_future')]);
            }
            app(UserYearService::class)->ensureDateYearIsOpen($user, $effective->toDateString(), 'effective_from');
            $scheduleIds = collect($data['payments'])->pluck('schedule_id')->filter();
            if ($scheduleIds->unique()->count() !== $scheduleIds->count()) {
                throw ValidationException::withMessages(['payments' => __('salary.revision_ordinary')]);
            }
            $current = $employment->schedules()->whereNull('valid_to')->with('recurringEntry')->lockForUpdate()->get()->keyBy('id');
            $editable = app(AccessibleAccountsQuery::class)->editableIds($user);
            foreach ($current as $schedule) {
                $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($schedule->recurring_entry_id);
                abort_unless(in_array((int) $entry->account_id, $editable, true), 403);
                if ($schedule->valid_from && $schedule->valid_from->gt($effective)) {
                    throw ValidationException::withMessages(['effective_from' => __('salary.revision_future')]);
                }
                $future = $entry->occurrences()->whereDate('expected_date', '>=', $effective->toDateString())->whereNotIn('status', ['cancelled', 'skipped', 'refunded'])->with('salaryDetail.components')->lockForUpdate()->get();
                foreach ($future as $occurrence) {
                    if ($occurrence->converted_transaction_id || $occurrence->matched_transaction_id || $occurrence->amount_confirmed_at || $occurrence->automatic_posting_blocked_at || $occurrence->account_id !== null || ($occurrence->due_date && ! $occurrence->due_date->equalTo($occurrence->expected_date)) || $occurrence->notes !== $entry->notes
                        || $occurrence->salaryDetail?->is_overridden || $occurrence->salaryDetail?->components->isNotEmpty() || $occurrence->paymentAllocations()->exists()) {
                        throw ValidationException::withMessages(['payments' => __('salary.revision_protected', ['date' => $occurrence->expected_date->toDateString()])]);
                    }
                }
                $entry->forceFill(['end_mode' => 'until_date', 'end_date' => $effective->subDay()->toDateString()])->save();
                $entry->occurrences()->whereDate('expected_date', '>=', $effective->toDateString())->whereNull('converted_transaction_id')->whereNull('matched_transaction_id')->whereIn('status', ['pending', 'generated'])->update(['status' => 'cancelled']);
                $schedule->update(['valid_to' => $effective->subDay()->toDateString()]);
            }
            if (! collect($data['payments'])->contains('kind', 'ordinary')) {
                throw ValidationException::withMessages(['payments' => __('salary.revision_ordinary')]);
            }
            foreach ($data['payments'] as $payment) {
                $old = filled($payment['schedule_id'] ?? null) ? $current->get((int) $payment['schedule_id']) : null;
                if (filled($payment['schedule_id'] ?? null) && ! $old) {
                    abort(404);
                }
                $account = app(AccessibleAccountsQuery::class)->editable($user)->where('accounts.uuid', $payment['account_uuid'])->firstOrFail();
                $category = Category::query()->where('uuid', $payment['category_uuid'])->firstOrFail();
                $reference = filled($payment['reference_uuid'] ?? null) ? TrackedItem::query()->where('uuid', $payment['reference_uuid'])->firstOrFail() : null;
                $date = CarbonImmutable::parse($payment['start_date']);
                $sameCalendar = $old && $this->generator->nextDateOnOrAfter($old->recurringEntry, $date)->equalTo($date) && $old->recurringEntry->recurrence_type->value === $payment['recurrence_type'];
                if ($sameCalendar) {
                    $date = $this->generator->nextDateOnOrAfter($old->recurringEntry, $effective);
                    $rule = $old->recurringEntry->recurrence_rule;
                    $interval = $old->recurringEntry->recurrence_interval;
                } else {
                    $rule = match ($payment['recurrence_type']) {
                        'yearly' => ['mode' => 'month_day', 'month' => $date->month, 'day' => $date->day],
                        'monthly','quarterly' => ['mode' => 'day_of_month', 'day' => $date->day], default => [],
                    };
                    $interval = 1;
                    $probe = new RecurringEntry(['start_date' => $date->toDateString(), 'recurrence_type' => $payment['recurrence_type'], 'recurrence_rule' => $rule, 'recurrence_interval' => $interval]);
                    $date = $this->generator->nextDateOnOrAfter($probe, $effective);
                }
                if ($employment->end_date && $date->gt($employment->end_date)) {
                    throw ValidationException::withMessages(['payments' => __('salary.outside_employment')]);
                }
                $attributes = $old ? $old->recurringEntry->getAttributes() : $current->first()->recurringEntry->getAttributes();
                unset($attributes['id'],$attributes['uuid'],$attributes['created_at'],$attributes['updated_at']);
                $this->management->store($user, [...$attributes,
                    'title' => $payment['label'], 'description' => $payment['label'], 'account_id' => $account->id, 'account_uuid' => null,
                    'category_id' => $category->id, 'category_uuid' => null, 'tracked_item_id' => $reference?->id, 'tracked_item_uuid' => null, 'merchant_id' => null,
                    'currency' => '', 'start_date' => $date->toDateString(), 'expected_amount' => $payment['amount'], 'recurrence_type' => $payment['recurrence_type'],
                    'recurrence_rule' => $rule, 'recurrence_interval' => $interval, 'end_mode' => $employment->end_date ? 'until_date' : 'never', 'end_date' => $employment->end_date?->toDateString(),
                    'is_active' => $old?->recurringEntry->is_active ?? $current->first()->recurringEntry->is_active, 'status' => $old?->recurringEntry->status->value ?? $current->first()->recurringEntry->status->value, 'is_amount_variable' => true, 'auto_create_transaction' => $data['automatic'],
                    'trial_duration' => null, 'trial_unit' => null, 'next_occurrence_date' => null,
                ], function (RecurringEntry $entry) use ($employment, $payment, $old, $effective): void {
                    $employment->schedules()->create(['recurring_entry_id' => $entry->id, 'kind' => $payment['kind'], 'label' => $payment['label'], 'parent_schedule_id' => $old?->id, 'valid_from' => $effective->toDateString()]);
                });
            }
            if (filled($data['name'] ?? null)) {
                $employment->update(['name' => trim($data['name'])]);
            }
        });
    }
}
