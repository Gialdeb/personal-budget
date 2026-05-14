<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\CreditDebtTypeEnum;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\DeviceToken;
use App\Models\NotificationTopic;
use App\Models\OutboundMessage;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ReminderDelivery;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Push\PushNotificationService;
use App\Services\Reminders\DailyCreditDebtReminderService;
use App\Services\Reminders\DailyRecurringReminderService;
use Carbon\Carbon;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 08:00:00', config('app.timezone')));
    config()->set('reminders.enabled', true);
    config()->set('reminders.due_soon_days', 3);
    config()->set('reminders.overdue_repeat_daily', true);
    config()->set('features.credits_debts.enabled', true);
    config()->set('features.push_notifications.enabled', false);

    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sends an in-app localized reminder for a credit due today and dedupes the second run', function () {
    $user = User::factory()->create(['locale' => 'it', 'base_currency_code' => 'EUR']);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    $item = CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Fattura cliente',
        'total_amount' => '80.00',
        'due_date' => '2026-05-14',
    ]);

    $firstRun = app(DailyCreditDebtReminderService::class)->run();
    $secondRun = app(DailyCreditDebtReminderService::class)->run();

    expect($firstRun)
        ->toMatchArray(['scanned' => 1, 'notified' => 1, 'duplicates' => 0])
        ->and($secondRun)
        ->toMatchArray(['scanned' => 1, 'notified' => 0, 'duplicates' => 1])
        ->and(OutboundMessage::query()->where('channel', CommunicationChannelEnum::DATABASE->value)->count())->toBe(1)
        ->and(OutboundMessage::query()->where('channel', CommunicationChannelEnum::MAIL->value)->count())->toBe(0)
        ->and(ReminderDelivery::query()->count())->toBe(1);

    $message = OutboundMessage::query()->firstOrFail();

    expect($message->title_resolved)->toBe('Credito da incassare')
        ->and($message->body_resolved)->toContain('Fattura cliente')
        ->and($message->cta_url_resolved)->toContain('/credits-debts')
        ->and($message->cta_url_resolved)->toContain('highlight='.$item->uuid)
        ->and($message->payload_snapshot['kind'])->toBe('credit_debt_due_reminder')
        ->and($message->payload_snapshot['item_uuid'])->toBe($item->uuid)
        ->and($message->payload_snapshot['remaining_amount'])->toBe('80.00');
});

it('skips credits and debts when the feature flag is disabled', function () {
    config()->set('features.credits_debts.enabled', false);
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    CreditDebtItem::factory()->forAccount($account)->create(['due_date' => '2026-05-14']);

    $result = app(DailyCreditDebtReminderService::class)->run();

    expect($result)->toMatchArray(['scanned' => 0, 'notified' => 0])
        ->and(OutboundMessage::query()->count())->toBe(0);
});

it('notifies an overdue partial debt with the remaining amount and ignores settled debts', function () {
    $user = User::factory()->create(['locale' => 'en', 'base_currency_code' => 'EUR']);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    $partialDebt = CreditDebtItem::factory()->debit()->forAccount($account)->create([
        'description' => 'Loan payment',
        'total_amount' => '100.00',
        'due_date' => '2026-05-10',
    ]);
    reminderCreditDebtPayment($partialDebt, $account, ['amount' => '40.00']);
    $settledDebt = CreditDebtItem::factory()->debit()->forAccount($account)->create([
        'total_amount' => '100.00',
        'due_date' => '2026-05-10',
    ]);
    reminderCreditDebtPayment($settledDebt, $account, ['amount' => '100.00']);

    $result = app(DailyCreditDebtReminderService::class)->run();

    expect($result)->toMatchArray(['scanned' => 2, 'notified' => 1, 'skipped' => 1]);

    $message = OutboundMessage::query()->firstOrFail();

    expect($message->title_resolved)->toBe('Overdue debt')
        ->and($message->body_resolved)->toContain('You still need to pay 60,00 EUR')
        ->and($message->payload_snapshot['item_type'])->toBe('debit')
        ->and($message->payload_snapshot['status'])->toBe('overdue');
});

it('does not notify credit debt items without due date or beyond the reminder window', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    CreditDebtItem::factory()->forAccount($account)->create(['due_date' => null]);
    CreditDebtItem::factory()->forAccount($account)->create(['due_date' => '2026-05-18']);

    $result = app(DailyCreditDebtReminderService::class)->run();

    expect($result)->toMatchArray(['scanned' => 0, 'notified' => 0])
        ->and(OutboundMessage::query()->count())->toBe(0);
});

it('sends a manual recurring occurrence reminder and skips generated automatic occurrences', function () {
    $user = User::factory()->create(['locale' => 'it', 'base_currency_code' => 'EUR']);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    $manualEntry = reminderRecurringEntry($user, $account, [
        'title' => 'Affitto',
        'auto_create_transaction' => false,
    ]);
    $automaticEntry = reminderRecurringEntry($user, $account, [
        'title' => 'Abbonamento',
        'auto_create_transaction' => true,
    ]);

    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $manualEntry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-05-14',
        'due_date' => '2026-05-14',
        'expected_amount' => '750.00',
        'status' => 'pending',
    ]);
    RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $automaticEntry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-05-14',
        'due_date' => '2026-05-14',
        'expected_amount' => '12.00',
        'status' => 'generated',
    ]);

    $firstRun = app(DailyRecurringReminderService::class)->run();
    $secondRun = app(DailyRecurringReminderService::class)->run();

    expect($firstRun)->toMatchArray(['scanned' => 1, 'notified' => 1])
        ->and($secondRun)->toMatchArray(['scanned' => 1, 'duplicates' => 1]);

    $message = OutboundMessage::query()->firstOrFail();

    expect($message->title_resolved)->toBe('Ricorrenza da registrare')
        ->and($message->cta_url_resolved)->toBe(route('recurring-entries.show', $manualEntry->uuid, false))
        ->and($message->payload_snapshot['recurring_entry_uuid'])->toBe($manualEntry->uuid)
        ->and($message->payload_snapshot['occurrence_uuid'])->toBe($occurrence->uuid)
        ->and($message->payload_snapshot['plan_type'])->toBe('manual');
});

it('respects in-app preferences and does not send email for reminders', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    $topic = NotificationTopic::query()->where('key', 'credits_debts_due_reminders')->firstOrFail();
    UserNotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_topic_id' => $topic->id,
        'email_enabled' => true,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);
    CreditDebtItem::factory()->forAccount($account)->create(['due_date' => '2026-05-14']);

    $result = app(DailyCreditDebtReminderService::class)->run();

    expect($result)->toMatchArray(['scanned' => 1, 'notified' => 0, 'skipped' => 1])
        ->and(OutboundMessage::query()->count())->toBe(0);
});

it('sends push only when enabled and an active device token exists', function () {
    config()->set('features.push_notifications.enabled', true);
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $user->settings()->create([
        'active_year' => 2026,
        'base_currency' => 'EUR',
        'settings' => ['notifications' => ['push' => ['enabled' => true]]],
    ]);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    DeviceToken::factory()->for($user)->create(['token' => 'active-token']);
    CreditDebtItem::factory()->forAccount($account)->create(['due_date' => '2026-05-14']);

    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andReturn([
        'eligible_users_count' => 1,
        'target_tokens_count' => 1,
        'sent_count' => 1,
        'failed_count' => 0,
        'invalidated_count' => 0,
    ]);
    $this->instance(PushNotificationService::class, $push);

    $result = app(DailyCreditDebtReminderService::class)->run();

    expect($result)->toMatchArray(['notified' => 1, 'pushed' => 1]);
});

it('commands output reminder counts', function () {
    Artisan::call('reminders:daily');

    expect(Artisan::output())->toContain('Recurring reminders:')
        ->toContain('- scanned: 0')
        ->toContain('Credits/debts reminders:');

    $this->artisan('reminders:recurring-due')->expectsOutput('Recurring reminders:')->assertSuccessful();
    $this->artisan('reminders:credits-debts-due')->expectsOutput('Credits/debts reminders:')->assertSuccessful();
});

function reminderCreditDebtPayment(CreditDebtItem $item, mixed $account, array $attributes = []): CreditDebtPayment
{
    return CreditDebtPayment::query()->create([
        'user_id' => $item->user_id,
        'credit_debt_item_id' => $item->id,
        'account_id' => $account->id,
        'transaction_id' => null,
        'amount' => '25.00',
        'currency_code' => $item->currency_code,
        'paid_at' => '2026-05-14',
        'note' => null,
        ...$attributes,
    ]);
}

function reminderRecurringEntry(User $user, mixed $account, array $attributes = []): RecurringEntry
{
    return RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'title' => 'Recurring reminder item',
        'direction' => 'expense',
        'expected_amount' => '19.90',
        'currency' => 'EUR',
        'entry_type' => 'recurring',
        'status' => 'active',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 14],
        'start_date' => '2026-05-01',
        'next_occurrence_date' => '2026-05-14',
        'end_mode' => 'never',
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        ...$attributes,
    ]);
}
