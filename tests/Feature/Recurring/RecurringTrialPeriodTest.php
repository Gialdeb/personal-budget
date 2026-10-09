<?php

use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\RecurringEntryLifecycleService;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Reminders\DailyRecurringReminderService;
use App\Services\Reminders\ReminderNotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00'));
    $this->trialUser = User::factory()->create();
    UserYear::query()->create(['user_id' => $this->trialUser->id, 'year' => 2026, 'is_closed' => false]);
    $this->trialAccount = createTestAccount($this->trialUser, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $category = Category::query()->create(['user_id' => $this->trialUser->id, 'name' => 'Subscription', 'slug' => 'subscription', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->trialPayload = ['title' => 'Trial subscription', 'account_id' => $this->trialAccount->id, 'category_id' => $category->id, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 9], 'start_date' => '2026-10-09', 'end_mode' => 'never', 'expected_amount' => 15, 'is_amount_variable' => false, 'auto_generate_occurrences' => true, 'auto_create_transaction' => true, 'is_active' => true, 'reminder_days_before' => [7], 'trial_duration' => 7, 'trial_unit' => 'days'];
});

test('trial starts real monthly occurrences on the first billing day without free economic entries', function () {
    $balance = $this->trialAccount->current_balance;
    $entry = app(RecurringEntryManagementService::class)->store($this->trialUser, $this->trialPayload);
    expect($entry->firstBillingDate()->toDateString())->toBe('2026-10-16');
    $dates = app(RecurringEntryOccurrenceGeneratorService::class)->previewDatesWithinRange($entry, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-12-31'))->map->toDateString()->all();
    expect($dates)->toBe(['2026-10-16', '2026-11-16', '2026-12-16']);
    expect($entry->occurrences()->whereDate('expected_date', '<', '2026-10-16')->exists())->toBeFalse();
    expect($entry->occurrences()->where('expected_amount', 0)->exists())->toBeFalse();
    app(RecurringEntryLifecycleService::class)->synchronize($entry, CarbonImmutable::parse('2026-10-15'));
    expect(Transaction::query()->count())->toBe(0)->and($this->trialAccount->fresh()->current_balance)->toBe($balance);
    expect($entry->occurrences()->openObligations()->exists())->toBeFalse();
    expect(fn () => app(RecurringEntryPostingService::class)->post($entry->occurrences()->orderBy('sequence_number')->first()))->toThrow(ValidationException::class);
});

test('trial can be rescheduled before billing but not after a real automatic posting', function () {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->trialUser, $this->trialPayload);
    $entry = $service->update($this->trialUser, $entry, [...$this->trialPayload, 'trial_duration' => 14]);
    expect($entry->firstBillingDate()->toDateString())->toBe('2026-10-23');
    $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00'));
    app(RecurringEntryLifecycleService::class)->synchronize($entry, CarbonImmutable::parse('2026-10-23'));
    expect(Transaction::query()->count())->toBe(1);
    expect(fn () => $service->update($this->trialUser, $entry, [...$this->trialPayload, 'trial_duration' => 21]))->toThrow(ValidationException::class);
    expect($entry->fresh()->trial_duration)->toBe(14);
    $entry = $service->update($this->trialUser, $entry, [...$this->trialPayload, 'trial_duration' => 14, 'title' => 'Updated title']);
    expect($entry->title)->toBe('Updated title')->and(Transaction::query()->count())->toBe(1);
});

test('first paid occurrence uses the existing seven day advance reminder', function () {
    $entry = app(RecurringEntryManagementService::class)->store($this->trialUser, $this->trialPayload);
    $this->mock(ReminderNotificationDispatcher::class, function ($mock) use ($entry) {
        $mock->shouldReceive('dispatch')->once()->withArgs(function (...$args) use ($entry): bool {
            return $args[6] === 'recurring_advance_7' && $args[7]->toDateString() === '2026-10-16'
                && $args[12]['recurring_entry_uuid'] === $entry->uuid;
        })->andReturn(['status' => 'notified', 'pushed' => 0]);
    });
    $result = app(DailyRecurringReminderService::class)->run();
    expect($result['scanned'])->toBe(1);
});

test('weeks and months calculate trial end without overflowing calendar months', function () {
    $model = new RecurringEntry(['entry_type' => 'recurring', 'start_date' => '2026-01-31', 'trial_duration' => 1, 'trial_unit' => 'months']);
    expect($model->firstBillingDate()->toDateString())->toBe('2026-02-28');
    $model->trial_unit = 'weeks';
    $model->trial_duration = 2;
    expect($model->firstBillingDate()->toDateString())->toBe('2026-02-14');
});

test('trial is rejected for installment plans', function () {
    expect(fn () => app(RecurringEntryManagementService::class)->store($this->trialUser, [...$this->trialPayload, 'entry_type' => 'installment', 'total_amount' => 150, 'installments_count' => 10]))->toThrow(ValidationException::class);
});
