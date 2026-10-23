<?php

use App\Models\NotificationTopic;
use App\Models\OutboundMessage;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ReminderDelivery;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Reminders\DailyRecurringReminderService;
use Carbon\Carbon;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 08:00:00', config('app.timezone')));
    config()->set('reminders.enabled', true);
    config()->set('features.push_notifications.enabled', false);
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);

    $this->user = User::factory()->create(['locale' => 'it', 'base_currency_code' => 'EUR']);
    $this->account = createTestAccount($this->user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    $entry = RecurringEntry::query()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'title' => 'Pagamento da verificare',
        'direction' => 'expense',
        'expected_amount' => '19.90',
        'currency' => 'EUR',
        'entry_type' => 'recurring',
        'status' => 'active',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'start_date' => '2026-04-01',
        'next_occurrence_date' => '2026-05-01',
        'end_mode' => 'never',
        'auto_create_transaction' => true,
        'is_active' => true,
    ]);
    $this->occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-04-30',
        'due_date' => '2026-04-30',
        'expected_amount' => '19.90',
        'status' => 'pending',
        'payment_management_state' => 'pending',
        'payment_reminder_date' => '2026-05-13',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('follows up an unresolved previous-month occurrence once without creating transactions or changing balances', function () {
    $balance = $this->account->current_balance;
    $transactionCount = Transaction::query()->count();
    $first = app(DailyRecurringReminderService::class)->run();
    $second = app(DailyRecurringReminderService::class)->run();
    Carbon::setTestNow(Carbon::parse('2026-05-15 08:00:00', config('app.timezone')));
    $third = app(DailyRecurringReminderService::class)->run();

    expect($first)->toMatchArray(['scanned' => 1, 'notified' => 1])
        ->and($second)->toMatchArray(['notified' => 0, 'duplicates' => 1])
        ->and($third)->toMatchArray(['notified' => 0, 'duplicates' => 1])
        ->and(OutboundMessage::query()->count())->toBe(1)
        ->and(ReminderDelivery::query()->firstOrFail()->reminder_type)->toBe('occurrence_payment_followup')
        ->and(ReminderDelivery::query()->firstOrFail()->delivery_date->toDateString())->toBe('2026-05-13')
        ->and(OutboundMessage::query()->firstOrFail()->payload_snapshot['occurrence_uuid'])->toBe($this->occurrence->uuid)
        ->and(Transaction::query()->count())->toBe($transactionCount)
        ->and($this->account->fresh()->current_balance)->toBe($balance);
});

it('stops followups when the occurrence is resolved', function () {
    app(DailyRecurringReminderService::class)->run();
    $this->occurrence->update(['payment_resolved_at' => now(), 'payment_management_state' => 'paid']);
    $result = app(DailyRecurringReminderService::class)->run();

    expect($result)->toMatchArray(['scanned' => 0, 'notified' => 0])
        ->and(OutboundMessage::query()->count())->toBe(1);
});

it('does not remind cancelled or unscheduled occurrences', function (array $attributes) {
    $this->occurrence->update($attributes);

    expect(app(DailyRecurringReminderService::class)->run())->toMatchArray(['scanned' => 0, 'notified' => 0]);
})->with([
    'cancelled' => [['payment_management_state' => 'cancelled']],
    'no reminder selected' => [['payment_reminder_date' => null]],
    'future reminder' => [['payment_reminder_date' => '2026-05-16']],
    'resolved timestamp' => [['payment_resolved_at' => '2026-05-14 07:00:00']],
]);

it('respects notification preferences even for an explicitly requested followup', function () {
    UserNotificationPreference::query()->create([
        'user_id' => $this->user->id,
        'notification_topic_id' => NotificationTopic::query()->where('key', 'recurring_due_reminders')->firstOrFail()->id,
        'email_enabled' => true,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);

    expect(app(DailyRecurringReminderService::class)->run())->toMatchArray(['scanned' => 1, 'notified' => 0, 'skipped' => 1])
        ->and(OutboundMessage::query()->count())->toBe(0)
        ->and(ReminderDelivery::query()->count())->toBe(0);
});

it('excludes managed occurrences from the normal due reminders', function () {
    $this->occurrence->update(['expected_date' => '2026-05-14', 'due_date' => '2026-05-14']);

    expect(app(DailyRecurringReminderService::class)->run())->toMatchArray(['scanned' => 1, 'notified' => 1])
        ->and(ReminderDelivery::query()->count())->toBe(1)
        ->and(ReminderDelivery::query()->firstOrFail()->reminder_type)->toBe('occurrence_payment_followup');
});

it('does not resend the same followup after the expected date changes', function () {
    app(DailyRecurringReminderService::class)->run();
    $this->occurrence->update(['expected_date' => '2026-05-01', 'due_date' => '2026-05-01']);

    expect(app(DailyRecurringReminderService::class)->run())->toMatchArray(['notified' => 0, 'duplicates' => 1])
        ->and(ReminderDelivery::query()->count())->toBe(1)
        ->and(OutboundMessage::query()->count())->toBe(1);
});
